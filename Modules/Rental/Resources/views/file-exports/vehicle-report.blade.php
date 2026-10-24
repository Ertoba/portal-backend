<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Vehicle report') }}</h1></div>
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
            <th>{{ translate('SL') }}</th>
            <th>{{translate('messages.Vehicle image')}}</th>
            <th>{{translate('Vehicle information')}}</th>
            <th>{{translate('messages.Provider')}}</th>
            <th>{{translate('messages.Hourly rate')}}</th>
            <th>{{translate('messages.Distance wise rate')}}</th>
            <th>{{translate('messages.Day wise rate')}}</th>
            <th>{{translate('messages.Total trip count')}}</th>
            <th>{{translate('messages.Total trip vehicles')}}</th>
            <th>{{translate('Total trip amount')}}</th>
            <th>{{translate('Total discount given')}}</th>
            <th>{{translate('Average trip value')}}</th>
            <th>{{translate('messages.Total reviews')}}</th>
            <th>{{translate('messages.Average ratings')}}</th>
        </thead>
        <tbody>
        @foreach($data['vehicles'] as $key => $vehicle)
            <tr>
                <td>{{ $key+1}}</td>
                <td></td>
                <td>{{$vehicle['name']}}</td>
                <td>
                    @if($vehicle->provider)
                    {{ $vehicle->provider->name }}
                    @else
                    {{translate('messages.Provider deleted')}}
                    @endif
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($vehicle->hourly_price) }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($vehicle->distance_price) }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($vehicle->day_wise_price) }}
                </td>
                <td>
                    {{$vehicle->trips_count ?? 0}}
                </td>
                <td>
                    {{$vehicle->trip_details_sum_quantity ?? 0}}
                </td>

                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($vehicle->trips_sum_price) }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($vehicle->total_discount) }}
                <td>
                    {{ $vehicle->trips_count>0? \App\CentralLogics\Helpers::format_currency(($vehicle->trips_sum_price-$vehicle->total_discount)/($vehicle->trip_details_sum_quantity ?? 0) ) :0 }}
                </td>
                <td>{{ $vehicle->total_reviews }}</td>
                <td>{{ round($vehicle->avg_rating,1) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
