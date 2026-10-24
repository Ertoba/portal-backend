<?php

namespace Modules\Rental\Emails;

use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Rental\Entities\RentalEmailTemplate;
use Modules\Rental\Entities\Trips;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class TripBooking extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    protected $trip_id;

    public function __construct($trip_id)
    {
        $this->trip_id = $trip_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $trip_id = $this->trip_id;
        $trip = Trips::with('proDiscount')->where('id', $trip_id)->first();
        $url = route('admin.trip_invoice', ['id' => base64_encode($trip_id), 'type' => 'trip']);
        $data = RentalEmailTemplate::where('type', 'user')->where('email_type', 'new_order')->first();
        $user_name = $trip->customer->f_name.' '.$trip->customer->l_name;
        $store_name = $trip->provider->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 3,
            subject: translate('Trip booked'),
            placeholders: [
                'user_name' => $user_name ?? '',
                'store_name' => $store_name ?? '',
                'order_id' => $trip_id ?? '',
            ],
            viewData: ['trip' => $trip, 'url' => $url],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
