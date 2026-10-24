
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Driver trip list')}}
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
                <th>{{ translate('Customer') }}</th>
                <th>{{ translate('Provider') }}</th>
                <th>{{ translate('Trip amount') }}</th>
                <th>{{ translate('Discount on trip') }}</th>
                <th>{{ translate('Coupon discount amount') }}</th>
                <th>{{ translate('Trip status') }}</th>
                <th>{{ translate('Payment status') }}</th>
                <th>{{ translate('Tax amount') }}</th>

            </thead>
            <tbody>
            @foreach($data['data'] as $key => $driverTrip)
                <tr>
                    <td>{{ $loop->index+1}}</td>
                    <td>{{ $driverTrip?->trip?->customer?->fullName ?? translate('messages.Guest user') }}</td>
                    <td>{{ $driverTrip?->trip?->provider->name  }}</td>
                    <td>{{ $driverTrip?->trip?->trip_amount }}</td>
                    <td>{{ $driverTrip?->trip?->discount_on_trip }}</td>
                    <td>{{ $driverTrip?->trip?->coupon_discount_amount }}</td>
                    <td>{{ ucwords($driverTrip?->trip?->trip_status) }}</td>
                    <td>{{ ucwords($driverTrip?->trip?->payment_status) }}</td>
                    <td>{{ $driverTrip?->trip?->tax_amount }}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
