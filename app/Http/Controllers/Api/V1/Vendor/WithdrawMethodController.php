<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Models\DisbursementWithdrawalMethod;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class WithdrawMethodController extends Controller
{
    private const KEEPZ_SPLIT_METHOD_NAME = 'Keepz Split Receiver';
    private const KEEPZ_RECEIVER_TYPES = ['BRANCH', 'IBAN'];

    public function get_disbursement_withdrawal_methods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];
        $store=  $vendor->stores[0];

        $key = explode(' ', $request['search']);
        $paginator = DisbursementWithdrawalMethod::where('store_id', $store['id'])
            ->when( isset($key) , function($query) use($key){
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('method_name', 'like', "%{$value}%");
                    }
                });
            }
            )
            ->latest()
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $datas =[];
        $userInputs=[];
        foreach ($paginator->items() as $k => $v) {
            $userInputs=[];
            foreach(json_decode($v->method_fields,true)as $key => $value){
                $userInput = [
                    'user_input' => $key,
                    'user_data' => $value,
                ];
                $userInputs[] = $userInput;
            }
            $v['method_fields'] = $userInputs;
            $datas[] = $v;
        }

        $data = [
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'methods' =>  $datas
        ];
        return response()->json($data, 200);
    }

    public function disbursement_withdrawal_method_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'withdraw_method_id' => 'required|integer|exists:withdrawal_methods,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];
        $store=  $vendor->stores[0];

        $method = WithdrawalMethod::where('is_active', 1)->find($request['withdraw_method_id']);

        if (!$method) {
            return response()->json(['errors' => [['code' => 'withdraw_method_id', 'message' => translate('messages.method_not_found')]]], 404);
        }

        if ($this->isKeepzSplitMethod($method)) {
            $this->normalizeKeepzRequest($request);
        }

        $fieldValidator = Validator::make($request->all(), $this->methodFieldRules($method));

        if ($fieldValidator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($fieldValidator)], 403);
        }

        $method_data = $this->methodDataFromRequest($method, $request);

        if ($this->isKeepzSplitMethod($method)) {
            $existingMethod = DisbursementWithdrawalMethod::firstOrNew([
                'store_id' => $store['id'],
                'withdrawal_method_id' => $method['id'],
            ]);

            $existingMethod->method_name = $method['method_name'];
            $existingMethod->method_fields = json_encode($method_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existingMethod->is_default = 1;
            $existingMethod->save();

            DisbursementWithdrawalMethod::where('store_id', $store['id'])
                ->where('id', '!=', $existingMethod->id)
                ->update(['is_default' => 0]);

            return response()->json(['message' => translate('messages.method_updated_successfully')], 200);
        }

        $data = [
            'store_id' => $store['id'],
            'withdrawal_method_id' => $method['id'],
            'method_name' => $method['method_name'],
            'method_fields' => json_encode($method_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('disbursement_withdrawal_methods')->insert($data);

        return response()->json(['message'=>'successfully added!'], 200);
    }

    public function disbursement_withdrawal_method_default(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'is_default' => 'required|in:0,1,true,false',
        ]);

        $vendor = $request['vendor'];
        $store=  $vendor->stores[0];

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $method = DisbursementWithdrawalMethod::where('id', $request->id)
            ->where('store_id', $store['id'])
            ->first();

        if (!$method) {
            return response()->json(['errors' => [['code' => 'id', 'message' => translate('messages.method_not_found')]]], 404);
        }

        $method->is_default = in_array((string) $request->is_default, ['1', 'true'], true);
        $method->save();
        if ($method->is_default) {
            DisbursementWithdrawalMethod::where('id', '!=', $request->id)->where('store_id',$store['id'])->update(['is_default' => 0]);
        }
        return response()->json(['message'=>translate('messages.method_updated_successfully')], 200);
    }

    public function disbursement_withdrawal_method_delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];
        $store=  $vendor->stores[0];

        $method = DisbursementWithdrawalMethod::where('id', $request->id)
            ->where('store_id', $store['id'])
            ->first();

        if (!$method) {
            return response()->json(['errors' => [['code' => 'id', 'message' => translate('messages.method_not_found')]]], 404);
        }

        $method->delete();
        return response()->json(['message'=>translate('messages.method_deleted_successfully')], 200);
    }
    public function withdraw_method_list(){
        $wi=WithdrawalMethod::where('is_active',1)->get();
        return response()->json($wi,200);
    }

    private function methodFieldRules(WithdrawalMethod $method): array
    {
        $rules = [];

        foreach ($method->method_fields ?? [] as $field) {
            $fieldName = $field['input_name'] ?? null;

            if (!$fieldName) {
                continue;
            }

            $rule = ((int) ($field['is_required'] ?? 0) === 1 ? 'required' : 'nullable') . '|string|max:191';
            $rules[$fieldName] = $rule;
        }

        if ($this->isKeepzSplitMethod($method)) {
            $rules['keepz_receiver_type'] = 'required|string|in:' . implode(',', self::KEEPZ_RECEIVER_TYPES);
            $rules['keepz_receiver_identifier'] = [
                'required',
                'string',
                'max:191',
                function ($attribute, $value, $fail) {
                    $type = strtoupper(trim((string) request('keepz_receiver_type')));
                    $identifier = $this->normalizeKeepzReceiverIdentifier($type, (string) $value);

                    if ($type === 'IBAN' && !preg_match('/^GE\d{2}[A-Z]{2}\d{16}$/', $identifier)) {
                        $fail(translate('messages.invalid_iban'));
                    }

                    if ($type === 'BRANCH' && !Str::isUuid($identifier)) {
                        $fail(translate('messages.invalid_receiver_id'));
                    }
                },
            ];
        }

        return $rules;
    }

    private function methodDataFromRequest(WithdrawalMethod $method, Request $request): array
    {
        $methodData = [];

        foreach ($method->method_fields ?? [] as $field) {
            $fieldName = $field['input_name'] ?? null;

            if (!$fieldName || !$request->has($fieldName)) {
                continue;
            }

            $methodData[$fieldName] = trim((string) $request->input($fieldName));
        }

        if ($this->isKeepzSplitMethod($method)) {
            $type = strtoupper(trim((string) $request->input('keepz_receiver_type')));
            $methodData['keepz_receiver_type'] = $type;
            $methodData['keepz_receiver_identifier'] = $this->normalizeKeepzReceiverIdentifier(
                $type,
                (string) $request->input('keepz_receiver_identifier')
            );
        }

        return $methodData;
    }

    private function isKeepzSplitMethod(WithdrawalMethod $method): bool
    {
        $fieldNames = array_column($method->method_fields ?? [], 'input_name');

        return $method->method_name === self::KEEPZ_SPLIT_METHOD_NAME
            || in_array('keepz_receiver_identifier', $fieldNames, true);
    }

    private function normalizeKeepzReceiverIdentifier(string $type, string $identifier): string
    {
        $identifier = trim($identifier);

        if ($type === 'IBAN') {
            return strtoupper(preg_replace('/\s+/', '', $identifier) ?? $identifier);
        }

        return strtolower($identifier);
    }

    private function normalizeKeepzRequest(Request $request): void
    {
        $type = strtoupper(trim((string) $request->input('keepz_receiver_type')));

        $request->merge([
            'keepz_receiver_type' => $type,
            'keepz_receiver_identifier' => $this->normalizeKeepzReceiverIdentifier(
                $type,
                (string) $request->input('keepz_receiver_identifier')
            ),
        ]);
    }
}
