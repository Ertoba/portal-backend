<?php

namespace Modules\Rental\Http\Controllers\Web\Admin\Promotions;

use App\Rules\ImageFile;
use Exception;
use App\Models\Zone;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Modules\Rental\Exports\PushNotificationExport;
use Illuminate\Contracts\Foundation\Application;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Support\Notification\SendNotification;
use App\Support\Storage\FileStorage;



class NotificationController extends Controller
{

    public function __construct(private Notification $notification, private Zone $zone)
    {
        $this->notification = $notification;
        $this->zone = $zone;
    }

    public function list(Request $request)
    {
        $notifications = $this->getListData($request);
        $notifications =  $notifications->paginate(config('default_pagination'));
        $zones = $this->zone->where('status' , 1)->get(['id','name']);
        return view('rental::admin.push-notification.list', compact('notifications','zones'));
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validateRequest($request);
        try {
            DB::beginTransaction();
            $notification = $this->createnotification($request);
            SendNotification::pushToTopic($notification, $this->getTopic($request), 'general');
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.Failed to sent notification'));
            return back();
        }
        Toastr::success(translate('messages.Notification sent successfully'));
        return back();
    }

    public function edit(Notification $notification): View|Factory|Application|RedirectResponse
    {
        $notification->loadMissing('storage');

        $zones = $this->zone->where('status' , 1)->get(['id','name']);
        return view('rental::admin.push-notification.edit', compact('notification', 'zones'));
    }

    public function update(Notification $notification, Request $request): RedirectResponse
    {
        $this->validateRequest($request, false, $notification->id);
        try {
            DB::beginTransaction();
            $this->updatenotification($request, $notification);
            SendNotification::pushToTopic($notification, $this->getTopic($request), 'general');
            DB::commit();
            Toastr::success(translate('Updated successfully'));
            return to_route('admin.rental.notification.list');
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.Failed to update notification'));
            return back();
        }
    }

    public function status(Notification $notification): RedirectResponse
    {
        $notification->update(['status' => !$notification->status]);
        Toastr::success(translate('Updated successfully'));
        return back();
    }


    public function destroy(Notification $notification): RedirectResponse
    {
        if ($notification->image) {
            Helpers::check_and_delete('notification/' , $notification->image);
        }
        $notification?->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }


    public function export(Request $request): BinaryFileResponse
    {
        $notifications = $this->getListData($request);
        $notifications =  $notifications->get();

        $data = [
            'data' => $notifications,
            'search' => $request['search'] ?? null,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new PushNotificationExport($data), 'Notifications.csv');
        }
        return Excel::download(new PushNotificationExport($data), 'Notifications.xlsx');
    }

    private function validateRequest(Request $request): void
    {
        $request->validate([
                'notification_title' => 'required|max:191',
                'description' => 'required|max:1000',
                'tergat' => 'required',
                'zone'=>'required',
                'image' => ImageFile::rules('nullable'),
            ]);
    }

    private function createnotification(Request $request): Notification
    {
        $notification = $this->notification;
        return  $this->updatenotification($request, $notification, true);
    }
    private function updatenotification(Request $request, Notification $notification , $new_image = false): Notification
    {

        if ($request->hasFile('image')) {
            $notification->image = $new_image == true ? FileStorage::upload('notification/', $request->file('image')) :FileStorage::update('notification/', $notification->image , $request->file('image'));
        }

        $notification->title = $request->notification_title;
        $notification->description = $request->description;
        $notification->tergat= $request->tergat == 'store' ? 'provider': $request->tergat ;
        $notification->status = 1;
        $notification->zone_id = $request->zone=='all'?null:$request->zone;
        $notification->save();
        if($notification?->image){
            $notification->image = $notification->toArray()['image_full_url'];
        }
        return $notification;
    }

    private function getListData($request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $notifications = Notification::withStorage()->with('zone')
        ->when($request['search'], function ($q) use ($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('title', 'like', "%{$value}%");
                }
            });
        })->latest();
        return $notifications;
    }
    public function getTopic(Object $request): string
    {
        $topicAllZone =[
            'customer'=>'all_zone_customer',
            'store'=>'all_zone_store',
        ];

        $topicZoneWise=[
            'customer'=>'zone_'.$request->zone.'_customer',
            'store'=>'zone_'.$request->zone.'_store',
        ];

        return $request->zone == 'all'?$topicAllZone[$request->tergat]:$topicZoneWise[$request->tergat];
    }
}
