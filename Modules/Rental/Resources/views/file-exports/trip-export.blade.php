
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{ ($data['providerLabel'] ?? null) ? translate('Trips') . ': ' . $data['providerLabel'] : translate('Trips list') }}
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
                <th>{{ translate('Pro discount amount') }}</th>
                <th>{{ translate('Trip status') }}</th>
                <th>{{ translate('Payment status') }}</th>
                <th>{{ translate('Tax amount') }}</th>

            </thead>
            <tbody>
            @foreach($data['data'] as $key => $trip)
                <tr>
                    <td>{{ $loop->index+1}}</td>
                    <td>{{ $trip?->customer?->fullName ?? translate('messages.Guest user') }}</td>
                        <td>{{ $trip->provider->name  }}</td>
                    <td>{{ $trip->trip_amount }}</td>
                    <td>{{ $trip?->discount_on_trip }}</td>
                    <td>{{ $trip?->coupon_discount_amount }}</td>
                    <td>{{ $trip?->proDiscount?->amount_saved ?? 0 }}</td>
                    <td>{{ ucwords($trip?->trip_status) }}</td>
                    <td>{{ ucwords($trip?->payment_status) }}</td>
                    <td>{{ $trip?->tax_amount }}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
