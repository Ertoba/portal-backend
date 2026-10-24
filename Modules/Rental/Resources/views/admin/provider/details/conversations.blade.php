@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.conversation'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">
    <link href="{{asset('Modules/Rental/public/assets/css/admin/provider-conversation.css')}}" rel="stylesheet">
@endpush

@section('content')
<div class="content container-fluid">
    @include('rental::admin.provider.details.partials._header',['store'=>$store])
    <div class="tab-content">
        <div class="tab-pane fade show active" id="product">
            <div class="row pt-2">
                <div class="content container-fluid">
                    <div class="page-header">
                        <h1 class="page-header-title">
                            <i class="tio-chat"></i>
                            <span>
                                {{ translate('messages.Conversation list') }}
                            </span>
                        </h1>
                        <p class="page-header-desc">{{ translate('Every chat this provider has had with a customer or with your team.') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <div class="card">
                                <div class="card-header border-0">
                                    <div class="input-group input---group">
                                        <div class="input-group-prepend border-inline-end-0">
                                            <span class="input-group-text border-inline-end-0" id="basic-addon1"><i class="tio-search"></i></span>
                                        </div>
                                        <input type="text" class="form-control border-inline-start-0 pl-1" id="serach" placeholder="{{translate('Search')}}" aria-label="Username"
                                            aria-describedby="basic-addon1" autocomplete="off">
                                    </div>
                                </div>
                                <input type="hidden" id="vendor_id" value="{{ $store->id }}">
                                <div class="card-body p-0 conversation-list-scroll" id="vendor-conversation-list">
                                    <div class="border-bottom"></div>
                                    @include('admin-views.vendor.view.partials._conversation_list')
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8 col-nd-6" id="vendor-view-conversation">
                            <div class="text-center mt-2">
                                <h4 class="initial-29">{{ translate('messages.View conversation') }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>

<div class="d-none" id="data-set"
     data-message-list-url="{{ route('admin.store.message-list') }}"
        data-view-conv-url="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'conversations']) }}"
     >
</div>
@endsection

@push('script_2')
<script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provi-conversation.js')}}"></script>
@endpush
