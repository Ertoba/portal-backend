
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Banner list')}}
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
            <th>{{ translate('Banner title') }}</th>
            <th>{{ translate('Banner type') }}</th>
            <th>{{ translate('Provider') }}</th>
            <th>URL</th>
            <th>{{ translate('featured') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $brand)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $brand->title }}</td>
        <td>{{ $brand->type == 'store_wise' ? translate('Provider wise') :translate('Default')  }}</td>

        <td>{{ $brand->type == 'store_wise' ?  $brand?->store?->name ?? "----------" : "-------------" }}</td>

        <td>{{ $brand?->default_link ?? '-------------' }}</td>

        <td>{{ $brand->featured == 1 ? translate('messages.Yes') : translate('messages.No')  }}</td>
        <td>{{ $brand->status == 1 ? translate('messages.Active') : translate('messages.Inactive')  }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
