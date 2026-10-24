<div>
    <div class="table-responsive">
        <table id="datatable"
            class="table table-thead-bordered table-align-middle card-table">
            <thead class="thead-light">
                <tr>
                    <th class="w--1 border-0">{{translate('SL')}}</th>
                    <th class="w--1 border-0">{{translate('messages.Trip ID')}}</th>
                    <th class="w--2 border-0">{{translate('Total trip amount')}}</th>
                    <th class="w--3 border-0">{{translate('messages.Provider earned')}}</th>
                    <th class="w--1 border-0">{{translate('messages.Admin earned')}}</th>
                    <th class="w--1 border-0">{{translate('Additional charge')}}</th>
                    <th class="w--1 border-0">{{translate('VAT/tax')}}</th>
                </tr>
            </thead>
            <tbody>
            @php($digitalTransaction = $transactions)
            @foreach($digitalTransaction as $key => $transaction)
                <tr>
                    <td scope="row">{{$key+$digitalTransaction->firstItem()}}</td>
                    <td><a href="{{route('admin.rental.trip.details',$transaction->trip_id)}}">{{$transaction->trip_id}}</a></td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($transaction->trip_amount)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($transaction->store_amount - $transaction->tax)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($transaction->admin_commission)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($transaction->additional_charge)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($transaction->tax)}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if(count($digitalTransaction) !== 0)
<hr>
@endif
<div class="page-area">
    {!! $digitalTransaction->links() !!}
</div>
@if(count($digitalTransaction) === 0)
<div class="empty--data">
    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
    <h5>
        {{translate('No data found')}}
    </h5>
</div>
@endif
