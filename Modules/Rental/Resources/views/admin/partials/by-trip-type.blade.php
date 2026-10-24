<div class="position-relative pie-chart">
    <div id="dognut-pie"></div>
    <div class="total--orders">
        <h3 class="text-uppercase mb-xxl-2">
            {{ $totalCount }}</h3>
        <span class="text-capitalize">{{ translate('Total trip') }}</span>
    </div>
</div>
<div class="d-flex flex-wrap justify-content-center mt-4">
    <div class="chart--label">
        <span class="indicator chart-bg-1"></span>
        <span class="info">
            {{ translate('Hourly trip') }} {{ $hourlyCount }}
        </span>
    </div>
    <div class="chart--label">
        <span class="indicator chart-bg-3"></span>
        <span class="info">
            {{ translate('Distance wise trip') }} {{ $distanceWiseCount }}
        </span>
    </div>
    <div class="chart--label">
        <span class="indicator chart-bg-4"></span>
        <span class="info">
            {{ translate('Per day trip') }} {{ $daywiseCount }}
        </span>
    </div>
</div>


