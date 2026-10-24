@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('Discount'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">

@endpush

@section('content')
<div class="content container-fluid">
    @include('rental::admin.provider.details.partials._header',['store'=>$store])
    <div class="tab-content">
        <div class="tab-pane fade show active" id="vendor">
            <div class="card">
                <div class="card-header">
                    <div>

                        <h5 class="card-title">
                            <i class="tio-info"></i>
                            <span>{{translate('Discount information')}}</span>
                        </h5>
                        <div class="text--info mb-3">
                            {{'* ' . translate('When this discount is available, is applied on all the vehicles in this provider.')}}
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        @if($store->discount)
                        <button type="button" class="btn-sm btn--primary" data-toggle="modal" data-target="#updatesettingsmodal">
                            <i class="tio-open-in-new"></i> {{translate('Update')}}
                        </button>
                        <button type="button" data-id="discount-{{$store->id}}" data-message="{{ translate('Want to remove discount?') }}" class="btn btn--danger text-white form-alert"><i class="tio-delete-outlined"></i>  {{translate('messages.Delete')}}</button>
                        @else
                        <button type="button" class="btn-sm btn--primary" data-toggle="modal" data-target="#updatesettingsmodal">
                            <i class="tio-add"></i> {{translate('Add discount')}}
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($store->discount)
                    <div class="row gy-3">
                        <div class="col-md-4 align-self-center text-center">

                            <div class="discount-item text-center">
                                <h5 class="subtitle">{{translate('Discount amount')}}</h5>
                                <h4 class="amount">{{$store->discount?round($store->discount->discount):0}}%</h4>
                            </div>

                        </div>
                        <div class="col-md-4 text-center text-md-left">
                            <div class="discount-item">
                                <h5 class="subtitle">{{translate('messages.Duration')}}</h5>
                                <ul class="list-unstyled list-unstyled-py-3 text-dark">
                                    <li class="p-0 pt-1 justify-content-center justify-content-md-start">
                                        <span>{{translate('Start date')}} :</span>
                                        <strong>{{$store->discount?date('Y-m-d',strtotime($store->discount->start_date)):''}} {{$store->discount?date(config('timeformat'), strtotime($store->discount->start_time)):''}}</strong>
                                    </li>
                                    <li class="p-0 pt-1 justify-content-center justify-content-md-start">
                                        <span>{{translate('End date')}} :</span>
                                        <strong>{{$store->discount?date('Y-m-d', strtotime($store->discount->end_date)):''}} {{$store->discount?date(config('timeformat'), strtotime($store->discount->end_time)):''}}</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4 text-center text-md-left">

                            <h5 class="subtitle">{{translate('Purchase conditions')}}</h5>

                            <ul class="list-unstyled list-unstyled-py-3 text-dark">
                                <li class="p-0 pt-1 justify-content-center justify-content-md-start">
                                    <span>{{translate('messages.Max discount')}} :</span>
                                    <strong>{{\App\CentralLogics\Helpers::format_currency($store->discount?$store->discount->max_discount:0)}}</strong>
                                </li>
                                <li class="p-0 pt-1 justify-content-center justify-content-md-start">
                                    <span>{{translate('messages.Min purchase')}} :</span>
                                    <strong>{{\App\CentralLogics\Helpers::format_currency($store->discount?$store->discount->min_purchase:0)}}</strong>
                                </li>
                            </ul>

                        </div>
                    </div>
                    @else
                    <div class="text-center">
                        <span class="card-subtitle">{{translate('No discount created yet')}}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="updatesettingsmodal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header shadow py-3">
        <h5 class="modal-title" id="exampleModalCenterTitle">{{$store->discount?translate('Update'):translate('Add discount')}}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pb-4 pt-4">
        <form id="discount-form" method="POST">
            @csrf
            <div class="row gx-2">
                <div class="col-md-4 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('Discount amount')}} (%)</label>
                        <input type="number" min="0" max="100" step="0.01" name="discount" class="form-control" required value="{{$store->discount?$store->discount->discount:'0'}}">
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('messages.Min purchase')}} ({{\App\CentralLogics\Helpers::currency_symbol()}})</label>
                        <input type="number" name="min_purchase" step="0.01" min="0" max="999999999" class="form-control" placeholder="100" value="{{$store->discount?$store->discount->min_purchase:'0'}}">
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('messages.Max discount')}} ({{\App\CentralLogics\Helpers::currency_symbol()}})</label>
                        <input type="number" min="0" max="999999999" step="0.01" name="max_discount" class="form-control" value="{{$store->discount?$store->discount->max_discount:'0'}}">
                    </div>
                </div>
            </div>
            <div class="row gx-2">
                <div class="col-md-6 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('Start date')}}</label>
                        <input type="date" id="date_from" class="form-control" required name="start_date" value="{{$store->discount?date('Y-m-d',strtotime($store->discount->start_date)):''}}">
                    </div>
                </div>
                <div class="col-md-6 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('End date')}}</label>
                        <input type="date" id="date_to" class="form-control" required name="end_date" value="{{$store->discount?date('Y-m-d', strtotime($store->discount->end_date)):''}}">
                    </div>

                </div>
                <div class="col-md-6 col-6">
                    <div class="form-group">
                        <label class="input-label" for="title">{{translate('messages.Start time')}}</label>
                        <input type="time" id="start_time" class="form-control" required name="start_time" value="{{$store->discount?date('H:i',strtotime($store->discount->start_time)):'00:00'}}">
                    </div>
                </div>
                <div class="col-md-6 col-6">
                    <label class="input-label" for="title">{{translate('messages.End time')}}</label>
                    <input type="time" id="end_time" class="form-control" required name="end_time" value="{{$store->discount?date('H:i', strtotime($store->discount->end_time)):'23:59'}}">
                </div>
            </div>
            <div class="btn--container justify-content-end">
                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                @if($store->discount)
                    <button type="submit" class="btn btn--primary"><i class="tio-open-in-new"></i> {{translate('Update')}}</button>
                @else
                    <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                @endif
            </div>
        </form>
        <form action="{{route('admin.store.clear-discount',[$store->id])}}" method="post" id="discount-{{$store->id}}">
            @csrf @method('delete')
        </form>
      </div>
    </div>
  </div>
</div>


<div class="d-none" id="data-set"
     data-discount-url="{{ route('admin.store.discount',$store->id) }}">
</div>
@endsection

@push('script_2')
<script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-discount.js')}}"></script>
@endpush
