
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Review list')}}
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
            <th>{{ translate('Provider name') }}</th>
            <th>{{ translate('Trip ID') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('Vehicle name') }}</th>
            <th>{{ translate('Rating') }}</th>
            <th>{{ translate('Comment') }}</th>
            <th>{{ translate('Reply') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $review)
            <tr>
                <td>{{ $loop->index+1}}</td>
                <td>{{ $review->provider->name }}</td>
                <td>{{ $review->trip->id }}</td>
                <td>{{ $review?->customer?->fullName }}</td>
                <td>{{ $review?->vehicle?->name }}</td>
                <td>{{ $review?->rating }}</td>
                <td>{{ $review?->comment }}</td>
                <td>{{ $review?->reply }}</td>
                 <td>{{ $review->status == 1 ? translate('messages.Active') : translate('messages.Inactive')  }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
