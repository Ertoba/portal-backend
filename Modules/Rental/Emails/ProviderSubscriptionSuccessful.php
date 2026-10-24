<?php

namespace Modules\Rental\Emails;

use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Rental\Entities\RentalEmailTemplate;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class ProviderSubscriptionSuccessful extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    protected $url;

    public function __construct($name, $url)
    {
        $this->name = $name;
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'subscription-successful')->first();
        $url = $this->url;
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: translate('Subscription successful'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url, 'type' => 'invoice'],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
