<?php

namespace App\Http\Controllers\Admin\System;

use App\Models\Module;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\View\View;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;

class AddonController extends Controller
{
    public function __construct(){
        if (is_dir('Modules\Gateways\Traits') && trait_exists('Modules\Gateways\Traits\SmsGateway')) {
            $this->extendWithSmsGatewayTrait();
        }
    }

    private function extendWithSmsGatewayTrait()
    {
        $extendedControllerClass = $this->generateExtendedControllerClass();
        eval($extendedControllerClass);
    }

    private function generateExtendedControllerClass()
    {
        $baseControllerClass = get_class($this);
        $traitClassName = 'Modules\Gateways\Traits\SmsGateway';

        $extendedControllerClass = "
            class ExtendedController extends $baseControllerClass {
                use $traitClassName;
            }
        ";

        return $extendedControllerClass;
    }

    public function index(): Factory|View|Application
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            if($directory !== 'TaxModule'){
                $sub_dirs = self::getDirectories('Modules/' . $directory);
                if (in_array('Addon', $sub_dirs)) {
                    $addons[] = 'Modules/' . $directory;
                }
            }
        }
        return view('admin-views.system.addon.index', compact('addons'));
    }

    public function publish(Request $request): JsonResponse|int
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        $full_data = include($request['path'] . '/Addon/info.php');
        $full_data['is_published'] = $full_data['is_published'] ? 0 : 1;
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        if ($full_data['name'] == 'Rental') {
            $this->rentalPublish($full_data['is_published']);
        }

        if ($full_data['name'] == 'RideShare') {
            $this->rideSharePublish($full_data['is_published']);
        }

        return response()->json([
            'status' => 'success',
            'message'=> 'status_updated_successfully'
        ]);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_upload' => 'required|mimes:zip'
        ]);

        if ($validator->errors()->count() > 0) {
            $error = Helpers::error_processor($validator);
            return response()->json(['status' => 'error', 'message' => $error[0]['message']]);
        }

        $file = $request->file('file_upload');
        try {
            Helpers::validateFile($file);
        } catch (\App\Exceptions\InvalidUploadException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
        $filename = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp', $filename);
        $zip = new \ZipArchive();

        if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {
            // Extract the contents to a directory
            $extractPath = base_path('Modules/');
            if (!File::isWritable($extractPath)) {
                        $status = 'error';
                        $message = translate('messages.File is not writable. Please check your file permissions.');
                        return response()->json(['status' => $status, 'message' => $message]);
                    }
            $zip->extractTo($extractPath);
            $zip->close();
            if(File::exists($extractPath.'/'.explode('.', $filename)[0].'/Addon/info.php')){
                File::chmod($extractPath.'/'.explode('.', $filename)[0].'/Addon', 0777);
                Toastr::success(translate('file_upload_successfully!'));
                $status = 'success';
                $message = translate('file_upload_successfully!');
            }else{
                File::deleteDirectory($extractPath.'/'.explode('.', $filename)[0]);
                $status = 'error';
                $message = translate('invalid_file!');
            }
        }else{
            $status = 'error';
            $message = translate('file_upload_fail!');
        }

        Storage::delete($tempPath);

        return response()->json([
            'status' => $status,
            'message'=> $message
        ]);
    }

    public function delete_theme(Request $request){
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        $path = $request->path;

        $full_path = base_path($path);

        if(File::deleteDirectory($full_path)){
            return response()->json([
                'status' => 'success',
                'message'=> translate('file_delete_successfully')
            ]);
        }else{
            return response()->json([
                'status' => 'error',
                'message'=> translate('file_delete_fail')
            ]);
        }

    }

    //helper functions
    function getDirectories(string $path): array
    {
        $fullPath = base_path($path);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];

        foreach (scandir($fullPath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($fullPath . DIRECTORY_SEPARATOR . $item)) {
                $directories[] = $item;
            }
        }

        return $directories;
    }

    private function rentalPublish(int|bool $is_published): bool
    {
        try {
            $module = Module::firstOrNew(
                ['module_type' => 'rental'],
                ['module_name' => 'Rental']
            );

            if ($is_published) {
                Artisan::call('migrate', ['--force' => true]);
                $module->status = 1;
            } else {
                $module->status = 0;
            }

            $module->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function rideSharePublish(int|bool $is_published): bool
    {
        try {
            $module = Module::firstOrNew(
                ['module_type' => 'ride-share'],
                ['module_name' => 'RideShare']
            );

            if ($is_published) {
                Artisan::call('migrate', ['--force' => true]);
                $module->status = 1;
            } else {
                $module->status = 0;
            }

            $module->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
