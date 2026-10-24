@php($data=[])
<?php
foreach ($store->schedules as $schedule)
{
    $data[$schedule->day][]=['id'=>$schedule->id,'start_time'=>$schedule->opening_time, 'end_time'=>$schedule->closing_time];
}
?>
<div class="schedule-item">
    <span class="btn">Monday :</span>
    <div class="schedult-date-content">
        @if(isset($data['1']) && count($data['1']))
            <span class="d-inline-flex align-items-center">
                @foreach ($data['1'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="1" data-day="Monday"><i class="tio-add"></i></span>
    </div>
</div>

<div class="schedule-item">
    <span class="btn">Tuesday :</span>
    <div class="schedult-date-content">
    @if(isset($data['2']) && count($data['2']))
            <span class="d-inline-flex align-items-center">
        @foreach ($data['2'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
    @else
        <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
    @endif
    <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="2" data-day="Tuesday"><i class="tio-add"></i></span>
</div>
</div>
<div class="schedule-item">
        <span class="btn">Wednesday :</span>
    <div class="schedult-date-content">
        @if(isset($data['3']) && count($data['3']))
            <span class="d-inline-flex align-items-center">
            @foreach ($data['3'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="3" data-day="Wednesday"><i class="tio-add"></i></span>
</div>
</div>

<div class="schedule-item">
        <span class="btn">Thursday :</span>
    <div class="schedult-date-content">
        @if(isset($data['4']) && count($data['4']))
            <span class="d-inline-flex align-items-center">
            @foreach ($data['4'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="4" data-day="Thursday"><i class="tio-add"></i></span>
</div>
</div>

<div class="schedule-item">
        <span class="btn">Friday :</span>
    <div class="schedult-date-content">
        @if(isset($data['5']) && count($data['5']))
            <span class="d-inline-flex align-items-center">
            @foreach ($data['5'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="5" data-day="Friday"><i class="tio-add"></i></span>
</div>
</div>

<div class="schedule-item">
        <span class="btn">Saturday :</span>
    <div class="schedult-date-content">
        @if(isset($data['6']) && count($data['6']))
            <span class="d-inline-flex align-items-center">
            @foreach ($data['6'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach
            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="6" data-day="Saturday"><i class="tio-add"></i></span>
    </div>
</div>

<div class="schedule-item">
    <span class="btn">Sunday :</span>
    <div class="schedult-date-content">
        @if(isset($data['0']) && count($data['0']))
            <span class="d-inline-flex align-items-center">
            @foreach ($data['0'] as $day)
                <span class="start--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Opening time')}}</span>
                        {{date(config('timeformat'), strtotime($day['start_time']))}}
                    </span>
                </span>
                <span class="end--time">
                    <span class="clock--icon">
                        <i class="tio-time"></i>
                    </span>
                    <span class="info">
                        <span>{{translate('Closing time')}}</span>
                        {{date(config('timeformat'), strtotime($day['end_time']))}}
                    </span>
                </span>
                <span class="dismiss--date delete-schedule" data-url="{{route('admin.store.remove-schedule',['store_schedule'=>$day['id']])}}">
                    <i class="tio-clear-circle-outlined"></i>
                </span>
                @endforeach

            </span>
        @else
            <span class="btn btn-sm btn-outline-danger m-1 disabled">{{translate('messages.Off day')}}</span>
        @endif
        <span class="btn add--primary" data-toggle="modal" data-target="#exampleModal" data-dayid="0" data-day="Sunday"><i class="tio-add"></i></span>
    </div>
</div>
