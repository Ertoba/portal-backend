
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Vehicle list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }}

                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Vehicle name') }}</th>
            <th>{{ translate('Vehicle ID') }}</th>
            <th>{{ translate('Vehicle category') }}</th>
            <th>{{ translate('Vehicle brand') }}</th>
            <th>{{ translate('Trip fare') }}</th>
            <th>{{ translate('New tag') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $vehicle)
            <tr>
                <td>{{ $loop->index+1}}</td>
                <td>{{ $vehicle->name }}</td>
                <td>{{ $vehicle->id }}</td>
                <td>{{ $vehicle?->category?->name }}</td>
                <td>{{ $vehicle?->brand?->name }}</td>
                <td>
                    @if($vehicle->trip_hourly)
                        <div>
                            <span class="opacity-lg">{{translate('Hourly')}}: </span>
                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['hourly_price'])}}</span>
                        </div>
                    @endif
                    @if($vehicle->trip_distance)
                        <div>
                            <span class="opacity-lg">{{translate('Distance wise')}}: </span>
                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['distance_price'])}}</span>
                        </div>
                    @endif
                    @if($vehicle->trip_day_wise)
                        <div>
                            <span class="opacity-lg">{{translate('Per day')}}: </span>
                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['day_wise_price'])}}</span>
                        </div>
                    @endif
                </td>
                <td>{{ $vehicle->new_tag == 1 ? translate('messages.Yes') : translate('messages.No')  }}</td>
                <td>{{ $vehicle->status == 1 ? translate('messages.Active') : translate('messages.Inactive')  }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
