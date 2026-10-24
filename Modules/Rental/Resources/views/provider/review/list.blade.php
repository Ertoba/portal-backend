@extends('layouts.vendor.app')

@section('title',translate('Review list'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/star.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Customers reviews')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers said about your vehicles and drivers, newest first.') }}</p>
        </div>
        <div class="card">
            @php($store_review_reply = \App\CentralLogics\Helpers::get_business_settings('store_review_reply', false) ?? 0)
            <div class="card-header flex-wrap py-2 border-0">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h4 class="mb-0">{{ translate('Reviews') }}</h4>
                    <span class="badge badge-soft-dark rounded-circle">{{ $reviews->total() }}</span>
                </div>
                <div class="search--button-wrapper justify-content-end">

                    <form class="search-form">
                        <div class="input-group input--group">
                            <input name="search" type="search" value="{{ request()?->search }}" class="form-control h--40px" placeholder="{{ translate('Ex') . ' : ' . translate('Search by vehicle name') }}" aria-label="Search here">
                            <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                            href="javascript:;"
                            data-hs-unfold-options="{
                                &quot;target&quot;: &quot;#usersExportDropdown&quot;,
                                &quot;type&quot;: &quot;css-animation&quot;
                            }"
                            data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">

                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('vendor.vehicle.review.export', ['export_type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('vendor.vehicle.review.export', ['export_type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                    alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging": false
                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">#</th>
                        <th class="border-0">{{translate('Review ID')}}</th>
                        <th class="border-0">{{translate('messages.Vehicle')}}</th>
                        <th class="border-0">{{translate('messages.Reviewer')}}</th>
                        <th class="border-0">{{translate('messages.review')}}</th>
                        <th class="border-0">{{translate('messages.Date')}}</th>
                        <th class="border-0">{{translate('messages.Reply date')}}</th>
                        @if($store_review_reply == '1')
                            <th class="text-center">{{translate('messages.Action')}}</th>
                        @endif
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($reviews as $key=>$review)
                        <tr>
                            <td>{{$key+$reviews->firstItem()}}</td>
                            <td>{{$review->review_id}}</td>
                            <td>
                                @if ($review->vehicle)
                                    <div class="position-relative media align-items-center">
                                        <a class=" text-hover-primary absolute--link" href="{{route('vendor.vehicle.details',$review->vehicle_id)}}">
                                            <img class="avatar avatar-lg mr-3  onerror-image"  data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                                 src="{{ $review->vehicle->thumbnail_full_url }}" alt="{{$review?->vehicle?->name}} image">
                                        </a>
                                        <div class="media-body">
                                            <h5 class="text-hover-primary important--link mb-0">{{Str::limit($review?->vehicle?->name,10)}}</h5>
                                            <a href="{{route('vendor.trip.details',$review->trip_id)}}"  class="fz--12 text-body important--link">{{ translate('Trip ID') }} #{{$review->trip_id}}</a>
                                        </div>
                                    </div>
                                @else
                                    {{translate('messages.Vehicle deleted!')}}
                                @endif
                            </td>
                            <td>
                                @if($review->customer)
                                    <div>
                                        <h5 class="d-block text-hover-primary mb-1">{{Str::limit($review->customer['f_name']." ".$review->customer['l_name'])}} </h5>
                                        <span class="d-block font-size-sm text-body">{{Str::limit($review->customer->phone)}}</span>
                                    </div>
                                @else
                                    {{translate('No data found')}}
                                @endif
                            </td>
                            <td>
                                <div class="text-wrap w-18rem">
                                    <label class="rating">
                                        <i class="tio-star"></i>
                                        <span>{{$review->rating}}</span>
                                    </label>
                                    <p data-toggle="tooltip" data-placement="bottom"
                                       data-original-title="{{ $review?->comment }}" >
                                        {{Str::limit($review['comment'], 80)}}
                                    </p>
                                </div>
                            </td>
                            <td>
                                <span class="d-block">
                                    {{ \App\CentralLogics\Helpers::date_format($review->created_at)  }}
                                </span>
                                <span class="d-block"> {{ \App\CentralLogics\Helpers::time_format($review->created_at)  }}</span>
                            </td>
                            <td>
                                <p class="text-wrap" data-toggle="tooltip" data-placement="top"
                                   data-original-title="{{ $review?->reply }}">{!! $review->reply?Str::limit($review->reply, 50, '...'): translate('Not replied yet') !!}</p>
                            </td>
                            @if($store_review_reply == '1')
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a  class="btn btn-sm btn--primary {{ $review->reply ? 'btn-outline-primary' : ''}}" data-toggle="modal" data-target="#reply-{{$review->id}}" title="View Details">
                                            {{ $review->reply ? translate('View reply') : translate('Give reply')}}
                                        </a>
                                    </div>
                                </td>
                            @endif
                            <div class="modal fade" id="reply-{{$review->id}}">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header pb-4">
                                            <button type="button" class="payment-modal-close btn-close border-0 outline-0 bg-transparent" data-dismiss="modal">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="position-relative media align-items-center">
                                                <a class="absolute--link" href="{{route('vendor.vehicle.details',$review->vehicle_id)}}">
                                                </a>
                                                <img class="avatar avatar-lg mr-3  onerror-image"  data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                                     src="{{ $review?->vehicle?->thumbnail_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg') }}" alt="{{$review?->vehicle?->name}} image">
                                                <div>
                                                    <h5 class="text-hover-primary mb-0">{{ $review?->vehicle?->name ?? translate('Vehicle deleted!') }}</h5>
                                                    @if ($review?->vehicle?->avg_rating == 5)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 5 && $review?->vehicle?->avg_rating >= 4.5)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-half"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 4.5 && $review?->vehicle?->avg_rating >= 4)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 4 && $review?->vehicle?->avg_rating >= 3.5)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-half"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 3.5 && $review?->vehicle?->avg_rating >= 3)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 3 && $review?->vehicle?->avg_rating >= 2.5)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-half"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 2.5 && $review?->vehicle?->avg_rating > 2)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 2 && $review?->vehicle?->avg_rating >= 1.5)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-half"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 1.5 && $review?->vehicle?->avg_rating > 1)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating < 1 && $review?->vehicle?->avg_rating > 0)
                                                        <div class="rating">
                                                            <span><i class="tio-star-half"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating == 1)
                                                        <div class="rating">
                                                            <span><i class="tio-star"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @elseif ($review?->vehicle?->avg_rating == 0)
                                                        <div class="rating">
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                            <span><i class="tio-star-outlined"></i></span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="mt-2">
                                                @if($review->customer)
                                                    <div>
                                                        <h5 class="d-block text-hover-primary mb-1">{{Str::limit($review?->customer?->fullName)}} </h5>
                                                        <span class="d-block font-size-sm text-body">{{$review->comment}}</span>
                                                    </div>
                                                @else
                                                    {{translate('No data found')}}
                                                @endif
                                            </div>
                                            <div class="mt-3">
                                                <form action="{{route('vendor.rental.review.reply', $review->id)}}" method="POST">
                                                    @csrf
                                                    <textarea id="reply" name="reply" required class="form-control" cols="30" rows="3" placeholder="{{ translate('Write your reply here') }}">{{ $review->reply ?? '' }}</textarea>
                                                    <div class="mt-3 btn--container justify-content-end">
                                                        <button class="btn btn-primary">{{ $review->reply ? translate('Update reply') : translate('Send reply')}}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($reviews) !== 0)
                <hr>
                @endif
                <table>
                    <tfoot>
                    {!! $reviews->links() !!}
                    </tfoot>
                </table>
                @if(count($reviews) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
