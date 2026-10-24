resources/views/file-exports/vendor-wise-tax-report.blade.php<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Trip report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Provider' )}} - {{ $data['provider']??translate('All') }}
                    <br>
                    {{ translate('Customer' )}} - {{ $data['customer']??translate('All') }}
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
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Trip ID') }}</th>
                <th>{{ translate('Customer information') }}</th>
                <th>{{ translate('messages.Provider name') }}</th>
                <th>{{ translate('Total fare of vehicle') }}</th>
                <th>{{ translate('Discount on vehicle') }}</th>
                <th>{{ translate('Coupon discount') }}</th>
                <th>{{ translate('Referral discount') }}</th>
                <th>{{ translate('messages.Pro discount') }}</th>
                <th>{{ translate('messages.Total discounted amount') }}</th>
                <th>{{  \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge')  }}</th>
                <th>{{ translate('messages.tax') }}</th>
                <th>{{ translate('Total amount') }}</th>
                <th>{{ translate('Payment status') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['trips'] as $key => $trip)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $trip->id }}</td>
                <td>
                    @if ($trip->customer)
                        {{ $trip->customer['f_name'] . ' ' . $trip->customer['l_name'] }}
                    @else
                        {{ translate('No data found') }}
                    @endif
                </td>
                <td>
                    @if($trip->provider)
                        {{$trip->provider->name}}
                    @else
                        {{ translate('No data found') }}
                    @endif
                </td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['trip_amount'] - $trip->additional_charge-$trip['tax_amount']+$trip['coupon_discount_amount'] + $trip['discount_on_trip'] + $trip['ref_bonus_amount'] + ($trip->proDiscount?->amount_saved ?? 0) ) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short( $trip['discount_on_trip'] ) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['ref_bonus_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip->proDiscount?->amount_saved ?? 0) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount'] + $trip['discount_on_trip'] + $trip['ref_bonus_amount'] + ($trip->proDiscount?->amount_saved ?? 0) ) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['additional_charge']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['tax_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['trip_amount']) }}</td>
                <td>{{ translate($trip->payment_status) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
