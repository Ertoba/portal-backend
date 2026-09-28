@foreach($stores as $key => $dm)
<tr>
    <td>{{ $key + 1 }}</td>
    <td>
        <div class="inline--1">
            <img class="img--60 img--circle onerror-image"
                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                 src="{{ $dm['logo_full_url'] }}">
        </div>
    </td>
    <td>
        <span class="d-block font-size-sm text-body">
            {{ $dm->name }}
        </span>
    </td>
    <td>
        <span class="d-block font-size-sm text-body">
            {{ $dm->vendor ? trim($dm->vendor->f_name.' '.$dm->vendor->l_name) : translate('messages.not_found') }}
        </span>
    </td>
    <td>{{ $dm->email }}</td>
    <td>{{ $dm->phone }}</td>
    <td>
        <div class="inline--2 redirect-url"
             data-url="{{ route('admin.banner.campaign', [$bannerId, $dm->id]) }}">
            <span class="legend-indicator bg-danger"></span>
            {{ translate('messages.remove') }}
        </div>
    </td>
</tr>
@endforeach

@if($stores->isEmpty())
<tr>
    <td colspan="7" class="text-center">
        {{ translate('messages.no_data_found') }}
    </td>
</tr>
@endif

<script src="{{ asset('public/assets/admin') }}/js/view-pages/common.js"></script>
