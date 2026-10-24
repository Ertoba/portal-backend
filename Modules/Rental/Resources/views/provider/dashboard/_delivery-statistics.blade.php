<div class="row g-2" id="order_stats">
    <div class="col-sm-6 col-lg-3">
        <a class="resturant-card dashboard--card __dashboard-card card--bg-1" href="{{ route('vendor.trip.list', ['status'=>'confirmed']) }}">
            <h4 class="title">{{ $confirmedCount }}</h4>
            <span class="subtitle font-regular ">{{ translate('messages.confirmed') }}</span>
            <img src="{{ asset('public/assets/admin/img/rental/1.png') }}" alt="Img" class="resturant-icon top-50px">
        </a>
    </div>

    <div class="col-sm-6 col-lg-3">
        <a class="resturant-card dashboard--card __dashboard-card card--bg-2" href="{{ route('vendor.trip.list', ['status'=>'ongoing']) }}">
            <h4 class="title">{{ $ongoingCount }}</h4>
            <span class="subtitle font-regular ">{{ translate('Ongoing trip') }}</span>
            <img src="{{ asset('public/assets/admin/img/rental/2.png') }}" alt="Img" class="resturant-icon top-50px">
        </a>
    </div>

    <div class="col-sm-6 col-lg-3">
        <a class="resturant-card dashboard--card __dashboard-card card--bg-3"
            href="{{ route('vendor.trip.list', ['status'=>'completed']) }}">
            <h4 class="title">{{ $completedCount }}</h4>
            <span class="subtitle font-regular ">{{ translate('messages.Completed') }}</span>
            <img src="{{ asset('public/assets/admin/img/rental/3.png') }}" alt="Img" class="resturant-icon top-50px">
        </a>
    </div>

    <div class="col-sm-6 col-lg-3">
        <a class="resturant-card dashboard--card __dashboard-card card--bg-4"
            href="{{ route('vendor.trip.list',['status'=>'canceled'])  }}">
            <h4 class="title">{{ $canceledCount }}</h4>
            <span class="subtitle font-regular ">{{ translate('Canceled') }}</span>
            <img src="{{ asset('public/assets/admin/img/rental/4.png') }}" alt="Img" class="resturant-icon top-50px">
        </a>
    </div>


    <div class="col-12">
        <div class="row g-2">
            <div class="col-sm-6 col-lg-3">
                <a class="order--card badge--accepted h-100" href="{{ route('vendor.trip.list', ['status'=>'all']) }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                            <span>{{ translate('All') }}</span>
                        </h6>
                        <span class="card-title text-success">
                            {{ $totalCount }}
                        </span>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a class="order--card badge--accepted h-100" href="{{ route('vendor.trip.list',  ['status'=>'pending'])  }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                            <span>{{ translate('Pending') }}</span>
                        </h6>
                        <span class="card-title text-danger">
                            {{ $pendingCount }}
                        </span>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a class="order--card badge--accepted h-100" href="{{ route('vendor.trip.list', ['status'=>'scheduled']) }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                            <span>{{ translate('Scheduled') }}</span>
                        </h6>
                        <span class="card-title text-primary">
                            {{ $scheduledCount }}
                        </span>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a class="order--card badge--accepted h-100" href="{{ route('vendor.trip.list',  ['status'=>'instant']) }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                            <span>{{ translate('Instant booking') }}</span>
                        </h6>
                        <span class="card-title text-info">
                            {{ $instantCount }}
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </div>

</div>
