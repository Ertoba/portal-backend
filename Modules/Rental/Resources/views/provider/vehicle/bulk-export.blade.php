@extends('layouts.vendor.app')

@section('title', translate('messages.Vehicle bulk export'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._export', ['bulk' => [
        'title' => translate('messages.Export vehicles'),
        'subtitle' => translate('Download your vehicles as a spreadsheet you can edit and import back.'),
        'icon' => asset('public/assets/admin/img/outline/vehicle.svg'),
        'action' => url()->current(),
        'summary' => $summary,
        'count_label' => translate('Vehicles in your fleet'),
        'show_module' => false,
        'file_name' => 'Vehicles.xlsx',
        'preview_all' => translate('Exporting every vehicle in your fleet.'),
        'columns' => ['Id', 'Name', 'Description', 'Thumbnail', 'Images', 'ZoneId', 'ProviderId', 'BrandId', 'CategoryId', 'Model', 'Type', 'EngineCapacity', 'EnginePower', 'SeatingCapacity', 'AirCondition', 'FuelType', 'TransmissionType', 'MultipleVehicles', 'TripHourly', 'TripDistance', 'TripDayWise', 'HourlyPrice', 'DayWisePrice', 'DistancePrice', 'DiscountType', 'DiscountPrice', 'Tag', 'Documents', 'Status', 'NewTag'],
        'tips' => [
            translate('TripHourly, TripDistance and TripDayWise switch a trip type on, and each has its own price column.'),
            translate('Thumbnail, images and documents hold stored file paths, not the files themselves.'),
            translate('Keep the Id column untouched if you plan to import the file back as an update.'),
        ],
        'import_url' => route('vendor.vehicle.bulk_import'),
        'import_title' => translate('messages.Vehicle bulk import'),
        'import_desc' => translate('Upload a filled spreadsheet to add or update vehicles.'),
        'help_title' => translate('Exporting vehicles'),
        'help_steps' => [
            translate('Pick the scope — everything, a date range, or an ID range.'),
            translate('Fill in the range if you picked one. Use full range fills in the bounds shown above.'),
            translate('Press export and the spreadsheet downloads in .xlsx format.'),
            translate('Edit the rows you need and bring the file back through bulk import to update them.'),
        ],
    ]])
@endsection
