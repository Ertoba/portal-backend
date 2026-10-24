<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('provider_vehicle_reports') }}</h1></div>
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
                    {{-- Reads the pre-aggregated row the query already computes, exactly as the
                         on-screen report does. It used to sum a `trips` collection this data set
                         no longer carries, so the export threw on every request. --}}
                    {{ translate('Total trip amount')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['trip_totals']->trip_amount) }}
                    <br>
                    {{ translate('Total tax')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['trip_totals']->tax_amount) }}
                    <br>
                    {{ translate('Total commission')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['trip_totals']->admin_commission - $data['trip_totals']->admin_expense) }}
                    <br>
                    {{ translate('Total provider earning')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['trip_totals']->store_amount) }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Vehicle image')}}</th>
            <th>{{ translate('Vehicle information') }}</th>
            <th>{{ translate('Total trip') }}</th>
            <th>
                {{ translate('Total trip amount') }}</th>
            <th>
                {{ translate('Discount given') }}</th>
        </thead>
        <tbody>
        @foreach($data['vehicles'] as $key => $item)
        <tr>
            <td>{{$key+1}}</td>
            <td></td>
            <td>{{  $item['name']  }}</td>
            <td>
                {{ $item->trips_count ?? 0 }}
            </td>
            <td>
                {{\App\CentralLogics\Helpers::format_currency($item->trips_sum_price) }}
            </td>
            <td>
                {{ \App\CentralLogics\Helpers::format_currency($item->total_discount) }}
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
