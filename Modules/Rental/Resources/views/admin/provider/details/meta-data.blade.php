@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.settings'))

@push('css_or_js')
    <!-- Custom styles for this page -->
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">

@endpush

@section('content')
<div class="content container-fluid">
    @include('rental::admin.provider.details.partials._header',['store'=>$store])
    <!-- Page Heading -->
    <div class="tab-content">
        <div class="tab-pane fade show active" id="vendor">
                <div class="card-body">
                    <form action="{{route('admin.store.update-meta-data',[$store['id']])}}" method="post"
                    enctype="multipart/form-data" class="col-12">
                    @csrf
                        @include('admin-views.business-settings.landing-page-settings.partial._meta_data',['submit' => true])
                    </form>
                </div>
            
        </div>
    </div>
</div>

@endsection

@push('script_2')
<script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-meta-data.js')}}"></script>
@endpush
