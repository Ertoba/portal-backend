<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{translate('Trip transaction statement')}}</title>
    <meta http-equiv="Content-Type" content="text/html;"/>
    <meta charset="UTF-8">

    <link rel="stylesheet" href="{{asset('Modules/Rental/public/assets/css/admin/trip-transaction-statement.css')}}">
</head>

<link rel="stylesheet" href="{{asset('Modules/Rental/public/assets/css/admin/font-awesome.min.css')}}">
<link rel="stylesheet" href="{{asset('Modules/Rental/public/assets/css/admin/google-font.css')}}">

<body>
<div class="first">
    <table class="bs-0 mb-30 px-10">
        <tr>
            <th class="content-position-y text-left">
                <h2>{{translate('messages.Trip transaction statement')}}</h2>
                <p class="fz-14">{{translate('Date')}} : {{ date('d M Y ' . config('timeformat'), strtotime(now())) }}</p>
                <h4 class="text-uppercase mb-1 fz-14">
                    {{translate('statement')}}: #{{ $trip_transaction->trip->id }}
                </h4>
            </th>
            <th class="content-position-y text-right">
                <img height="50" src="{{ $company_web_logo }}" alt="">
            </th>
        </tr>
    </table>
</div>
<div class="">
    <section>
        <table class="content-position-y fz-12">
            <tr>
                <td class="p-1">
                    <table>
                        <tr>
                            <td>
                                <div class="">
                                    <p class="fz-14">{{translate('Date')}} :
                                        {{ date('d M Y ' . config('timeformat'), strtotime($trip_transaction->trip['created_at'])) }}
                                    </p>
                                    @if ($trip_transaction->trip->provider)
                                        <p class="fz-14 custom-class-1" >{{translate('Provider')}} : {{$trip_transaction->trip->provider->name}}</p>
                                        @else
                                        <p class="fz-14 custom-class-1" >{{translate('No data found')}}</p>
                                    @endif
                                    @if (isset($trip_transaction->trip->customer) )
                                        <p class="fz-14 custom-class-1">{{translate('Customer')}} : {{$trip_transaction->trip->customer['f_name'] . ' ' . $trip_transaction->trip->customer['l_name']}}</p>
                                    @endif
                                </div>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>

                <td>
                    <table>
                        <tr>
                            <td class="text-right">
                                <p class="fz-14">{{translate('Delivered by')}} : {{ucfirst($trip_transaction->received_by)}}</p>
                                <p class="fz-14">{{translate('Payment method')}} : {{ payment_method_label($trip_transaction->trip['payment_method']) }}</p>
                                <p class="fz-14">{{translate('Payment status')}} : {{$trip_transaction->status ? translate('Refunded') : translate('messages.Completed')}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>


    </section>
</div>

<br>

<div class="">
    <div class="content-position-y">
        <table class="customers bs-0">
            <thead>
                <tr>
                    <th class="custom-class-background">{{translate('SL')}}</th>
                    <th class="custom-class-background">{{translate('Details')}}</th>
                    <th class="custom-class-background">{{translate('Amount')}}</th>
                </tr>
            </thead>
            @php
            @endphp
            <tbody>
                <tr>
                    <td>1</td>
                    <td>{{translate('messages.Total vehicle amount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip->trip_details()->sum(DB::raw('calculated_price'))) }}</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>{{translate('Vehicle discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip->discount_on_trip) }}</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>{{translate('Total coupon discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip['coupon_discount_amount']) }}</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>{{translate('Referral discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip['ref_bonus_amount']) }}</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>{{translate('messages.Pro discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip?->proDiscount?->amount_saved ?? 0) }}</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td>{{translate('messages.Total discounted amount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip['coupon_discount_amount'] + $trip_transaction->trip->discount_on_trip +$trip_transaction->trip['ref_bonus_amount'] + ($trip_transaction->trip?->proDiscount?->amount_saved ?? 0)) }}</td>
                </tr>
                <tr>
                    <td>7</td>
                    <td>{{translate('Total VAT/tax')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->tax_amount) }}</td>
                </tr>
                <tr>
                    <td>8</td>
                    <td>{{\App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->additional_charge) }}</td>
                </tr>
                <tr>
                    <td>9</td>
                    <td>{{translate('Total trip amount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->trip_amount) }}</td>
                </tr>
            </tbody>
        </table>
        <br><br><br>
        <table class="customers bs-0">
            <thead>
                <tr>
                    <th class="custom-class-background-transparent">{{translate('Additional information')}}</th>
                    <th class="custom-class-background-transparent">{{translate('totals')}}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{translate('Admin discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->admin_expense) }}</td>
                </tr>
                <tr>
                    <td>{{translate('messages.Provider discount')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->store_expense) }}</td>
                </tr>
                <tr>
                    <td>{{translate('Admin commission')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency( $trip_transaction->admin_commission) }}</td>
                </tr>
                <tr>
                    <td>{{translate('messages.Admin net income')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->admin_net_income) }}</td>
                </tr>
                <tr>
                    <td>{{translate('messages.Provider net income')}}</td>
                    <td>{{ \App\CentralLogics\Helpers::format_currency($trip_transaction->store_amount - $trip_transaction->tax_amount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<br>
<br>

<div class="row">
    <section>
        <table class="">
            <tr>
                <th class="fz-12 font-normal pb-3">
                    {{ translate('If you require any assistance or have feedback or suggestions about our site, you can email us') }}: <a href="mailto:{{ $company_email }}">{{ $company_email }}</a>
                </th>
            </tr>
            <tr>
                <th class="content-position-y bg-light py-4">
                    <div class="d-flex justify-content-center gap-2">
                        <div class="mb-2">
                            <i class="fa fa-phone"></i>
                            {{translate('Phone')}}
                            : {{ $company_phone }}
                        </div>
                        <div class="mb-2">
                            <i class="fa fa-envelope" aria-hidden="true"></i>
                            {{translate('email')}}
                            : {{$company_email}}
                        </div>
                    </div>
                    <div class="mb-2">
                        {{url('/')}}
                    </div>
                    <div>
                        &copy; {{$company_name}}. <span
                    class="d-none d-sm-inline-block">{{$footer_text}}</span>
                    </div>
                </th>
            </tr>
        </table>
    </section>
</div>

</body>
</html>
