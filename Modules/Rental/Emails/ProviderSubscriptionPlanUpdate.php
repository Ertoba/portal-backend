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

class ProviderSubscriptionPlanUpdate extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'subscription-plan_upadte')->first();
        $user_link = DataSetting::where('key', 'store_login_url')->first()?->value ?? 'store';
        $url = route('login', [$user_link]);
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: translate('Subscription plan updates'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
