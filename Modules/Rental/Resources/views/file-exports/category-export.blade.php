
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Category list')}}
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
            <th>{{ translate('Category name') }}</th>
            <th>{{ translate('Category ID') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $category)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $category->name }}</td>
        <td>{{ $category->id }}</td>
        <td>{{ $category->status == 1 ? translate('messages.Active') : translate('messages.Inactive')  }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
