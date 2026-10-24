<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Trip transaction report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Provider' )}} - {{ $data['provider']??translate('All') }}
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Transaction analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total completed amount')  }} - {{ $data['totalAmount'] ??translate('N/A') }}
                    <br>
                    {{ translate('Admin earnings')  }} - {{ $data['adminEarned'] ??translate('N/A') }}
                    <br>
                    {{ translate('Provider earnings')  }} - {{ $data['providerEarned'] ??translate('N/A') }}
                    <br>
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th class="border-0">{{ translate('SL') }}</th>
            <th class="border-0">{{ translate('messages.Trip ID') }}</th>
            <th class="border-0">{{ translate('messages.Provider') }}</th>
            <th class="border-0">{{ translate('messages.Customer') }}</th>
            <th class="border-0 min-w-120">{{ translate('Total trip amount') }}</th>
            <th class="border-0">{{ translate('Discount on vehicle') }}</th>
            <th class="border-0">{{ translate('Coupon discount') }}</th>
            <th class="border-0">{{ translate('Referral discount') }}</th>
            <th class="border-0">{{ translate('messages.Pro discount') }}</th>
            <th class="border-0">{{ translate('messages.Total discounted amount') }}</th>
            <th class="border-0">{{ translate('VAT/tax') }}</th>
            <th class="border-0">{{ translate('Admin commission') }}</th>
            <th class="border-0">
                {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge') }}
            </th>
            <th class="border-0">{{ translate('Admin discount') }}</th>
            <th class="min-w-140 text-capitalize">{{ translate('Admin net income') }}</th>
            <th class="border-0 text-capitalize">{{ translate('messages.Provider discount') }}</th>
            <th class="min-w-140 text-capitalize">{{ translate('Provider net income') }}</th>
            <th class="border-0 min-w-120 text-capitalize">{{ translate('Total amount received by') }}</th>
            <th class="border-top border-bottom text-capitalize">
                {{ translate('messages.Payment method') }}</th>
            <th class="border-0 text-capitalize">{{ translate('Payment status') }}</th>
        </thead>
        <tbody>
        @foreach($data['tripTransactions'] as $key => $ot)
            <tr>
                <td>{{ $key+1}}</td>
                <td>{{ $ot->trip_id }}</td>
                <td >
                    {{Str::limit($ot->trip?->provider?->name ?? translate('No data found'),25,'...')}}
                </td>
                <td>
                    @if ($ot->trip->customer)
                    <strong>{{ $ot->trip->customer['f_name'] . ' ' . $ot->trip->customer['l_name'] }}</strong>

                @elseif($ot->trip?->user_info['contact_person_name'])
                <strong>{{ $ot->trip?->user_info['contact_person_name'] }}</strong>

                @else
                    {{ translate('messages.Guest user') }}
                @endif
                </td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->trip_amount) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->trip->discount_on_trip) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->trip['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->trip['ref_bonus_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->trip?->proDiscount?->amount_saved ?? 0) }}</td>
                <td>  {{ \App\CentralLogics\Helpers::format_currency($ot->trip['coupon_discount_amount'] + $ot->trip['ref_bonus_amount'] + $ot->trip->discount_on_trip + ($ot->trip?->proDiscount?->amount_saved ?? 0)) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->tax) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->admin_commission) }}</td>


                <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->additional_charge)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->admin_expense) }}</td>


                <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->admin_net_income)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->store_expense) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->store_amount - $ot->tax) }}</td>
                @if ($ot->received_by == 'admin')
                    <td>{{ translate('messages.admin') }}</td>
                @elseif ($ot->received_by == 'vendor')
                    <td>{{ translate('messages.Provider') }}</td>
                @endif
                <td>
                    {{ payment_method_label($ot->trip['payment_method']) }}
                </td>
                <td>
                    {{translate('messages.Completed')}}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
