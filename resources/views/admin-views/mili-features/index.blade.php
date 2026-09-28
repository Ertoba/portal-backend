@extends('layouts.admin.app')

@section('title', 'Mili Features')

@section('content')
<div class="content container-fluid">

    <div class="mb-4">
        <h2 class="title-clr mb-1">Mili Features</h2>
        <p class="text-muted mb-0">
            Local feature configuration for the Mili platform.
        </p>
    </div>

    @php
        $features = [
            [
                'key' => 'vendor_app',
                'name' => 'Vendor App',
                'description' => 'Vendor mobile application access.',
            ],
            [
                'key' => 'deliveryman_app',
                'name' => 'Deliveryman App',
                'description' => 'Courier and deliveryman application access.',
            ],
            [
                'key' => 'react_web',
                'name' => 'React Web',
                'description' => 'Customer React website access.',
            ],
            [
                'key' => 'customer_app',
                'name' => 'Customer App',
                'description' => 'Customer mobile application access.',
            ],
            [
                'key' => 'admin_panel',
                'name' => 'Admin Panel',
                'description' => 'Mili administration panel.',
            ],
            [
                'key' => 'taxi',
                'name' => 'Taxi',
                'description' => 'Mili taxi functionality.',
            ],
        ];
    @endphp

    <div class="d-flex flex-column gap-3">
        @foreach($features as $feature)
            @php($enabled = (bool) config('mili.features.' . $feature['key'], false))

            <div class="card">
                <div class="card-body p-20">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <h4 class="black-color mb-1">
                                {{ $feature['name'] }}
                            </h4>
                            <p class="fz-12 text-c mb-0">
                                {{ $feature['description'] }}
                            </p>
                        </div>

                        <div class="col-md-3 text-md-right mt-3 mt-md-0">
                            @if($enabled)
                                <span class="badge badge-soft-success px-3 py-2">
                                    Enabled
                                </span>
                            @else
                                <span class="badge badge-soft-danger px-3 py-2">
                                    Disabled
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection
