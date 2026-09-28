@php
    $hide_source_column = $hide_source_column ?? false;
@endphp

@if(count($transactions) > 0)
    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-dark">
        <thead class="thead-light">
            <tr>
                <th class="border-0">{{ translate('SL') }}</th>
                <th class="table-column-pl-0 border-0">{{ translate('messages.Transaction_ID') }}</th>
                <th class="border-0">{{ translate('messages.Date') }}</th>
                @if(!$hide_source_column)
                    <th class="border-0">{{ translate('messages.Provider') }}</th>
                @endif
                <th class="border-0 text-center">{{ translate('messages.Earning_Source') }}</th>
                <th class="border-0 text-right">{{ translate('messages.Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
                <tr>
                    <td>
                        @if(!empty($t['breakdown']))
                            <span class="collapse-next-tr cursor-pointer">
                                <i class="tio-chevron-down"></i> {{ $loop->iteration + $transactions->firstItem() - 1 }}
                            </span>
                        @else
                            {{ $loop->iteration + $transactions->firstItem() - 1 }}
                        @endif
                    </td>
                    <td class="font-medium">{{ $t['transaction_id'] }}</td>
                    <td>
                        {{ \App\CentralLogics\Helpers::date_format($t['date']) }}
                        <br>
                        {{ \App\CentralLogics\Helpers::time_format($t['date']) }}
                    </td>
                    @if(!$hide_source_column)
                        <td>
                            <div class="mb-1">{{ $t['source'] ?? '' }}</div>
                            @if(isset($t['source_type']))
                                <div class="badge text-warning bg-warning bg-opacity-10 rounded-lg font-medium px-2">
                                    {{ translate($t['source_type']) }}
                                </div>
                            @endif
                        </td>
                    @endif
                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            @if(!empty($t['earning_from_badge']))
                                <div class="badge text-dark bg-info bg-opacity-10 rounded-lg font-regular px-2 mb-1">
                                    {{ translate($t['earning_from_badge']) }}
                                </div>
                            @endif

                            @if(!empty($t['trip_id']) && !empty($trip_details_route_name))
                                <a href="{{ route($trip_details_route_name, $t['trip_id']) }}" class="fs-12">
                                    {{ $t['earning_from'] }}
                                </a>
                            @endif
                        </div>
                    </td>
                    <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($t['amount']) }}</td>
                </tr>
                @if(!empty($t['breakdown']))
                    <tr class="collapsing-tr d-none bg-light2">
                        <td></td>
                        <td colspan="{{ $hide_source_column ? '3' : '4' }}" class="pr-0">
                            <div class="mb-2">{{ translate('Trip Commission') }}</div>
                            <div>{{ translate('Tax Amount') }}</div>
                        </td>
                        <td class="text-right pl-0">
                            <div class="mb-2">{{ \App\CentralLogics\Helpers::format_currency($t['breakdown']['store_amount_without_tax'] ?? 0) }}</div>
                            <div>+ {{ \App\CentralLogics\Helpers::format_currency($t['breakdown']['tax_amount'] ?? 0) }}</div>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    <div class="page-area px-4 pb-3">
        <div class="d-flex align-items-center justify-content-end">
            <div>{!! $transactions->links() !!}</div>
        </div>
    </div>
@else
    <div class="empty--data py-5 w-100">
        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="empty">
        <h5>{{ translate('no_data_found') }}</h5>
    </div>
    
@endif
