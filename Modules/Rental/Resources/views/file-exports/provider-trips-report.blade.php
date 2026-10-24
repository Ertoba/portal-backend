<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Provider trip reports') }}</h1></div>
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
                <th>{{ translate('Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total trips')  }}- {{ $data['total_trips'] }}
                    <br>
                    {{ translate('Total trip amount')  }}- {{ $data['total_trip_amount'] }}
                    <br>
                    {{ translate('Canceled trip')  }}- {{ $data['total_canceled_count'] }}
                    <br>
                    {{ translate('Completed trips')  }}- {{ $data['total_completed_count'] }}
                    <br>
                    {{ translate('Incomplete trips')  }}- {{ $data['total_ongoing_count'] }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('messages.Trip ID') }}</th>
            <th>{{ translate('Trip booking date') }}</th>
            <th>{{ translate('Trip schedule date') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('messages.Provider name') }}</th>
            <th>{{ translate('Total trip amount') }}</th>
            <th>{{ translate('Payment status') }}</th>
            <th>{{ translate('Discounted amount') }}</th>
            <th>{{ translate('messages.Pro discount') }}</th>
            <th>{{ translate('messages.tax') }}</th>
        </thead>
        <tbody>
            @foreach($data['trips'] as $key => $trip)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $trip->id }}</td>
                <td><div>
                    {{ date('d M Y', strtotime($trip['created_at'])) }}
                </div>
                <br>
                <div>
                    {{ date(config('timeformat'), strtotime($trip['created_at'])) }}
                </div></td>
                <td><div>
                    {{ date('d M Y', strtotime($trip['schedule_at'])) }}
                </div>
                <br>
                <div>
                    {{ date(config('timeformat'), strtotime($trip['schedule_at'])) }}
                </div></td>
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
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['trip_amount']) }}</td>
                <td>{{ translate($trip->payment_status) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount']  + $trip['ref_bonus_amount'] +  $trip['discount_on_trip'] + ($trip->proDiscount?->amount_saved ?? 0)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip->proDiscount?->amount_saved ?? 0) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($trip['tax_amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
