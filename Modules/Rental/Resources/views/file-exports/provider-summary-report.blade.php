<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Provider summary reports') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('New registered provider')  }}- {{ $data['new_providers'] ??translate('N/A') }}
                    <br>
                    {{ translate('Total trips')  }}- {{ $data['trips'] ??translate('N/A') }}
                    <br>
                    {{ translate('Total trip amount')  }}- {{ $data['total_trip_amount'] ??translate('N/A') }}
                    <br>
                    {{ translate('Completed trips')  }}- {{ $data['total_completed'] ??translate('N/A') }}
                    <br>
                    {{ translate('Incomplete trips')  }}- {{ $data['total_ongoing'] ??translate('N/A') }}
                    <br>
                    {{ translate('Canceled trips')  }}- {{ $data['total_canceled'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Payment statistics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Cash payments')  }} - {{ $data['cash_payments'] ??translate('N/A') }}
                    <br>
                    {{ translate('Digital payments')  }} - {{ $data['digital_payments'] ??translate('N/A') }}
                    <br>
                    {{ translate('Wallet payments')  }} - {{ $data['wallet_payments'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Provider name')}}</th>
            <th>{{translate('Total amount')}}</th>
            <th>{{translate('Total trip')}}</th>
            <th>{{translate('Total completed trip')}}</th>
            <th>{{translate('Completion rate')}}</th>
            <th>{{translate('Ongoing rate')}}</th>
            <th>{{translate('Cancellation rate')}}</th>
        </thead>
        <tbody>
        @foreach($data['providers'] as $key => $provider)
        @php($completed = $provider->trips->where('trip_status', 'completed')->count())
        @php($canceled = $provider->trips->where('trip_status', 'canceled')->count())
        <tr>
            <td>{{$key+1}}</td>
            <td>
                {{  $provider->name  }}
            </td>
            <td>
                {{\App\CentralLogics\Helpers::number_format_short($provider->trips->where('trip_status','completed')->sum('trip_amount'))}}
            </td>
            <td>
                {{ $provider->trips->count() }}
            </td>
            <td>
                {{ $completed }}
            </td>
            <td>
                {{ ($provider->trips->count() > 0 && $completed > 0)? number_format((100*$completed)/$provider->trips->count(), config('round_up_to_digit')): 0 }}%
            </td>
            <td>
                {{ ($provider->trips->count() > 0 && $completed > 0)? number_format((100*($provider->trips->count()-($completed+$canceled)))/$provider->trips->count(), config('round_up_to_digit')): 0 }}%
            </td>
            <td>
                {{ ($provider->trips->count() > 0 && $canceled > 0)? number_format((100*$canceled)/$provider->trips->count(), config('round_up_to_digit')): 0 }}%
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
