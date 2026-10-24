<?php

namespace Modules\Rental\Support;

class TripNotificationMessages
{
    public static function forTripPayment($trip, string $description): array
    {
        return [
            'title' => translate('Trip notification payment'),
            'description' => $description,
            'order_id' => $trip->id,
            'module_id' => $trip->module_id,
            'order_type' => 'trip',
            'image' => '',
            'type' => 'trip_status',
            'zone_id' => $trip->zone_id,
        ];
    }

    public static function forTrip($trip, string $title, string $description, string $orderType, string $type): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'order_id' => $trip->id,
            'module_id' => $trip->module_id,
            'order_type' => $orderType,
            'status' => $trip->trip_status,
            'image' => '',
            'type' => $type,
            'zone_id' => $trip->zone_id,
        ];
    }
}
