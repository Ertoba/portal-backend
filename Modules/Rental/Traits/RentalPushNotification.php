<?php

namespace Modules\Rental\Traits;

use App\Models\NotificationMessage;
use App\Support\Notification\SendNotification;
use Modules\Rental\Support\TripNotificationMessages;
use App\Support\Notification\NotificationLinks;
use App\Support\Notification\NotificationText;

trait RentalPushNotification
{
    public static function getTripStatusMessage($status, $module_type = 'rental', $lang = 'en')
    {
        $statusMap = [
            'confirmed' => 'confirm',
            'completed' => 'complete',
            'canceled' => 'cancel'
        ];

        $status = $statusMap[$status] ?? $status;
        $status = 'trip_' . $status . '_message';
        $data = NotificationMessage::select(['id', 'message', 'status'])->with(['translations' => function ($query) use ($lang) {
            $query->where('locale', $lang)->limit(1);
        }])->where('module_type', $module_type)->where('key', $status)->first();
        if ($data?->status == 1) {
            return count($data->translations) > 0 ? $data->translations[0]->value : $data['message'];
        }
        return false;
    }

    public static function sendTripNotificationToAll($trip)
    {
        self::sendTripNotificationAdminPanel($trip);
        self::sendTripNotificationproviderPanel($trip);
        self::sendTripNotificationproviderApp($trip);
        self::sendTripNotificationCustomer($trip);
        return true;
    }
    public static function sendTripNotificationAdminPanel($trip)
    {
        $data = self::makeNotifyData(
            title: translate('Trip notification'),
            description: translate('messages.You have a new trip booking'),
            trip: $trip,
            order_type: 'trip',
            type: 'new_order'
        );
        SendNotification::pushToTopic($data, 'admin_message', 'order_request', url('/') . '/admin/rental/trip');
        return true;
    }

    public static function sendTripNotificationproviderPanel($trip)
    {
        $push_notification_status = SendNotification::rentalChannelEnabled('provider', 'provider_trip_notification', 'push_notification_status', $trip?->provider_id);

        if ($push_notification_status) {
            $data = self::makeNotifyData(
                title: translate('Trip notification'),
                description: translate('messages.You have a new trip booking'),
                trip: $trip,
                order_type: 'trip',
                type: 'new_order'
            );
            SendNotification::pushToTopic($data, "store_panel_{$trip->provider_id}_message", 'new_order', NotificationLinks::vendorTrips());
            if ($trip?->provider?->vendor?->firebase_token) {
                SendNotification::pushToVendor($trip->provider?->vendor_id, $trip?->provider?->vendor?->firebase_token, $data, 'trip');
            }
        }
        return true;
    }
    public static function sendTripNotificationproviderApp($trip)
    {
        $push_notification_status = SendNotification::rentalChannelEnabled('provider', 'provider_trip_notification', 'push_notification_status', $trip?->provider_id);
        if ($push_notification_status) {

            $data = self::makeNotifyData(
                title: translate('Trip notification'),
                description: translate('messages.You have a new trip booking'),
                trip: $trip,
                order_type: 'trip',
                type: 'new_order'
            );

            if ($trip?->provider?->vendor?->firebase_token) {
                SendNotification::pushToVendor($trip->provider?->vendor_id, $trip?->provider?->vendor?->firebase_token, $data, 'trip');
            }
        }
        return true;
    }

    public static function sendTripNotificationCustomer($trip) {

        if($trip->is_guest){
            $customer_details =$trip['user_info'];
            $customer_name = $customer_details['contact_person_name'];
            $value = self::getTripStatusMessage($trip->trip_status,'rental','en');
            $user_fcm = $trip?->guest?->fcm_token;
        }else{
            $customer_name = "{$trip->customer?->f_name} {$trip->customer?->l_name}";
            $value = self::getTripStatusMessage($trip->trip_status,'rental',$trip?->customer?->current_language_key??'en');
            $user_fcm = $trip?->customer?->cm_firebase_token;
        }

        $value = NotificationText::format(value:$value,store_name:$trip->provider?->name,order_id:$trip->id,user_name:$customer_name);

        if (SendNotification::rentalChannelEnabled('customer','customer_trip_notification','push_notification_status') &&  $value && $user_fcm) {
            $data = self::makeNotifyData(
                title: translate('Trip notification'),
                description: $value,
                trip: $trip,
                order_type: 'trip',
                type: 'trip_status'
            );
            SendNotification::pushToCustomer($trip->user_id, $user_fcm, $data, 'trip', isGuest: (bool) $trip->is_guest);
        }
        return true;
    }
    public static function sendTripPaymentNotificationCustomer($trip)
    {
        if ($trip->is_guest) {
            $user_fcm = $trip?->guest?->fcm_token;
        } else {
            $user_fcm = $trip?->customer?->cm_firebase_token;
        }
        $value = $trip->payment_status == 'paid' ? translate('Your transaction has been completed') : translate('Your payment has not been received yet');

        if (SendNotification::rentalChannelEnabled('customer', 'customer_trip_notification', 'push_notification_status') && $value && $user_fcm) {
            $data = TripNotificationMessages::forTripPayment($trip, $value);
            SendNotification::pushToCustomer($trip->user_id, $user_fcm, $data, 'trip', isGuest: (bool) $trip->is_guest);
        }

        return true;
    }

    public static function makeNotifyData($title, $description, $trip, $order_type, $type)
    {
        return TripNotificationMessages::forTrip($trip, $title, $description, $order_type, $type);
    }
}
