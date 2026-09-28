@php
    $hide_source_column = $hide_source_column ?? false;
@endphp

@if(count($transactions) > 0)
    <table
        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-dark">
        <thead class="thead-light">
            <tr>
                <th class="border-0">{{ translate('SL') }}</th>
                <th class="table-column-pl-0 border-0">{{ translate('messages.Transaction_ID') }}</th>
                <th class="border-0">{{ translate('messages.Date') }}</th>
                @if(!$hide_source_column)
                    <th class="border-0">{{ translate('messages.Source') }}</th>
                @endif
                <th class="border-0 text-center">{{ translate('messages.Expense_Source') }}</th>
                <th class="border-0 text-right">{{ translate('messages.Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
                <tr>
                    <td>{{ $loop->iteration + $transactions->firstItem() - 1 }}</td>
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
                            @if(!empty($t['expense_source_badge']))
                                <div class="badge text-dark bg-danger bg-opacity-10 rounded-lg font-regular px-2 mb-1">
                                    {{ translate($t['expense_source_badge']) }}
                                </div>
                            @endif
                            @if(!empty($t['trip_id']) && !empty($trip_details_route_name))
                                <a href="{{ route($trip_details_route_name, $t['trip_id']) }}" class="fs-12">
                                    {{ $t['expense_source'] }}
                                </a>
                            @endif
                        </div>
                    </td>
                    <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($t['amount']) }}</td>
                </tr>
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
