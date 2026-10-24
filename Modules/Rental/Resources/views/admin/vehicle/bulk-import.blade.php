@extends('layouts.admin.app')

@section('title', translate('messages.Vehicle bulk import'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._import', ['bulk' => [
        'title' => translate('messages.Vehicle bulk import'),
        'subtitle' => translate('Upload a spreadsheet to add new vehicles or update the ones you already have.'),
        'icon' => asset('public/assets/admin/img/outline/vehicle.svg'),
        'action' => url()->current(),
        'summary' => $summary,
        'count_label' => translate('Vehicles in this module'),
        'mode_add_title' => translate('Add new vehicles'),
        'mode_add_desc' => translate('Every row becomes a new vehicle.'),
        'mode_update_title' => translate('Update existing vehicles'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'note_import' => translate('The Id column is ignored — new ids are assigned automatically.'),
        'note_update' => translate('Your file must keep its Id column. Matching vehicles are overwritten and cannot be restored.'),
        'templates' => [
            [
                'label' => translate('Template with current data'),
                'hint' => translate('The heading row plus example rows, so you can see how each column is filled.'),
                'url' => asset('public/assets/vehicle_bulk_format.xlsx'),
            ],
            [
                'label' => translate('Empty template'),
                'hint' => translate('Only the heading row — start from a clean sheet.'),
                'url' => asset('public/assets/vehicle_bulk_format_nodata.xlsx'),
            ],
        ],
        'aside_subtitle' => translate('These columns must carry a value in every row.'),
        'columns' => ['Name', 'Description', 'Thumbnail', 'Images', 'ZoneId', 'ProviderId', 'BrandId', 'CategoryId',
                      'Model', 'Type', 'EngineCapacity', 'EnginePower', 'SeatingCapacity', 'AirCondition', 'FuelType',
                      'TransmissionType', 'MultipleVehicles', 'TripHourly'],
        'tips' => [
            translate('ProviderId, BrandId, CategoryId and ZoneId come from their own lists — the file is not checked against names.'),
            translate('Turn a trip type on with TripHourly, TripDistance or TripDayWise, and fill the price column that goes with it.'),
            translate('Thumbnail, images and documents take file paths from the gallery — upload the files there first.'),
        ],
        'export_url' => route('admin.rental.provider.vehicle.bulk-export-index'),
        'export_title' => translate('messages.Export vehicles'),
        'export_desc' => translate('Download your current vehicles as a ready-made file to edit.'),
        'help_title' => translate('Importing vehicles'),
        'help_steps' => [
            translate('Download a template, or export your vehicles to start from the data you already have.'),
            translate('Fill one row per vehicle and keep the column headings exactly as they come.'),
            translate('Pick whether the file adds new vehicles or updates existing ones.'),
            translate('Choose the file and press upload — the whole file is checked before anything is saved.'),
        ],
    ]])
@endsection
