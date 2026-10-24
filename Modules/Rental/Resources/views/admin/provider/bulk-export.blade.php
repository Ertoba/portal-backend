@extends('layouts.admin.app')

@section('title', translate('messages.Provider bulk export'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._export', ['bulk' => [
        'title' => translate('messages.Export providers'),
        'subtitle' => translate('Download your providers as a spreadsheet you can edit and import back.'),
        'icon' => asset('public/assets/admin/img/provider.png'),
        'action' => url()->current(),
        'summary' => $summary,
        'count_label' => translate('Providers in this module'),
        'count_hint' => translate('Counted by their owner account.'),
        'file_name' => 'Providers.xlsx',
        'preview_all' => translate('Exporting every provider in this module.'),
        'columns' => ['Id', 'OwnerId', 'OwnerFirstName', 'OwnerLastName', 'ProviderName', 'Phone', 'Email', 'Logo',
                      'CoverPhoto', 'Latitude', 'Longitude', 'Address', 'ZoneId', 'ModuleId', 'Comission', 'Tax',
                      'PickupTime', 'ScheduleTrip', 'Status', 'ReviewsSection'],
        'tips' => [
            translate('The id range filters on the owner account, which is what the Id column holds.'),
            translate('Logo and CoverPhoto hold stored file paths, not the pictures themselves.'),
            translate('Email and phone are what a re-import matches against, so keep them intact.'),
        ],
        'import_url' => route('admin.rental.provider.bulk_import'),
        'import_title' => translate('messages.Provider bulk import'),
        'import_desc' => translate('Upload a filled spreadsheet to add or update providers.'),
        'help_title' => translate('Exporting providers'),
        'help_steps' => [
            translate('Pick the scope — everything, a date range, or an ID range.'),
            translate('Fill in the range if you picked one. Use full range fills in the bounds shown above.'),
            translate('Press export and the spreadsheet downloads in .xlsx format.'),
            translate('Edit the rows you need and bring the file back through bulk import to update them.'),
        ],
    ]])
@endsection
