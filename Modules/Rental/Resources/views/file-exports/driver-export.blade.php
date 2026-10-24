
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Driver list')}}
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
            <th>{{ translate('Driver ID') }}</th>
            <th>{{ translate('Driver name') }}</th>
            <th>{{ translate('Driver provider') }}</th>
            <th>{{ translate('Driver email') }}</th>
            <th>{{ translate('Driver phone') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $driver)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $driver->id }}</td>
        <td>{{ $driver->fullName }}</td>
        <td>{{ $driver?->provider?->name }}</td>
        <td>{{ $driver->email }}</td>
        <td>{{ $driver->phone }}</td>
        <td>{{ $driver->status == 1 ? translate('messages.Active') : translate('messages.Inactive')  }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
