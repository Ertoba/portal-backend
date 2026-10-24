<?php

namespace Modules\Rental\Emails;

use App\Models\DataSetting;
use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Rental\Entities\RentalEmailTemplate;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class ProviderSubscriptionRenewOrShift extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $status;

    protected $name;

    public function __construct($status, $name)
    {
        $this->status = $status;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if ($this->status == 'renew') {
            $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'subscription-renew')->first();
        } else {
            $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'subscription-shift')->first();
        }
        $user_link = DataSetting::where('key', 'store_login_url')->first()?->value ?? 'store';
        $url = route('login', [$user_link]);
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: $this->status == 'renew' ? translate('Subscription renew successful') : translate('Subscription shift successful'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
