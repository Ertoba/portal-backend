@foreach($items as $key => $item)
<tr>
    <td>{{ $key + 1 }}</td>
    <td>
        <a class="media align-items-center"
           href="{{ route('vendor.item.view', [$item['id']]) }}">
            <img class="avatar avatar-lg mr-3 onerror-image"
                 src="{{ $item['image_full_url'] }}"
                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                 alt="{{ $item->name }} image">
            <div class="media-body">
                <h5 class="text-hover-primary mb-0">
                    {{ Str::limit($item['name'], 20, '...') }}
                </h5>
            </div>
        </a>
    </td>
    <td>
        {{ Str::limit($item->category ? $item->category->name : translate('messages.category_deleted'), 20) }}
    </td>
    <td>
        {{ \App\CentralLogics\Helpers::format_currency($item['price']) }}
    </td>
    <td class="text-center">
        {{ $item['stock'] }}
    </td>
    <td>
        <div class="btn--container justify-content-center">
            <a class="btn btn-sm btn--primary btn-outline-primary action-btn update_quantity"
               href="javascript:"
               title="{{ translate('messages.edit_quantity') }}"
               data-id="{{ $item->id }}"
               data-toggle="modal"
               data-target="#update-quantity">
                <i class="tio-edit"></i>
            </a>
        </div>
    </td>
</tr>
@endforeach

@if($items->isEmpty())
<tr>
    <td colspan="6" class="text-center">
        {{ translate('messages.no_data_found') }}
    </td>
</tr>
@endif
