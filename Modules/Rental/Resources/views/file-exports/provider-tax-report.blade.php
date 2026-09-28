<div class="row">
    <div class="col-lg-12 text-center ">
        <h1>{{ translate('Provider_Tax_Report') }}</h1>
    </div>
    <div class="col-lg-12">



        <table>
            <thead>
                <tr>
                    <th>{{ translate('Summary') }}</th>
                    <th></th>
                    <th></th>
                    <th>

                        @if (isset($data['summary']))
                            {{-- <br>
                            {{ translate('total_orders') }} - {{ $data['summary']->total_orders ??0 }} --}}
                            <br>
                            {{ translate('total_order_amount') }} - {{ \App\CentralLogics\Helpers::format_currency($data['summary']->total_order_amount ?? 0) }}
                            <br>
                            {{ translate('total_tax') }} - {{\App\CentralLogics\Helpers::format_currency($data['summary']->total_tax ?? 0) }}
                        @endif
                        @if ($data['from'])
                            <br>
                            {{ translate('from') }} -
                            {{ $data['from'] ? Carbon\Carbon::parse($data['from'])->format('d M Y') : '' }}
                        @endif
                        @if ($data['to'])
                            <br>
                            {{ translate('to') }} -
                            {{ $data['to'] ? Carbon\Carbon::parse($data['to'])->format('d M Y') : '' }}
                        @endif
                        <br>
                        {{ translate('Search_Bar_Content') }}- {{ $data['search'] ?? translate('N/A') }}
                        <br>

                    </th>
                    <th> </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th class="border-0">{{ translate('sl') }}</th>
                    <th class="border-0">{{ translate('messages.Trip_id') }}</th>
                    <th class="border-0">{{ translate('messages.trip_amount') }}</th>
                    <th class="border-0">{{ translate('messages.tax_type') }}</th>
                    <th class="border-0">{{ translate('messages.tax_amount') }}</th>
            </thead>
            <tbody>
                @foreach ($data['orders'] as $key => $order)
                    <tr>
                        <td>
                            {{ $key + 1 }}
                        </td>
                        <td>
                            #{{ $order->id }}
                        </td>
                        <td>
                            {{ \App\CentralLogics\Helpers::format_currency($order->trip_amount) }}
                        </td>
                        <td>
                            {{ translate('messages.trip_wise') }}
                        </td>
                    <td>
                                        <div class="d-flex flex-column gap-1">
                                @if (count($order->orderTaxes) > 0)
                                    @php($sum_tax_amount = collect($order->orderTaxes)->sum('tax_amount'))
                                                <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                                    {{ translate('Sum of Taxes:') }} <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($sum_tax_amount) }}</span>
                                                </div> <br>

                                    @foreach ($order->orderTaxes as $tax)
                                                    <div class="d-flex fz-11 gap-3 align-items-center">
                                                        {{ $tax['tax_name'] }}:
                                            <span>{{ \App\CentralLogics\Helpers::format_currency($tax['tax_amount']) }}
                                                    </span>
                                                    </div> <br>
                                                @endforeach
                                @else
                                    <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                        {{ translate('Tax Amount:') }} <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($order->tax_amount) }}</span>
                                    </div>
                                            @endif
                                        </div>
                                    </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
