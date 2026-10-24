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

class ProviderRegistration extends Mailable implements ShouldQueue
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
        $data = RentalEmailTemplate::where('type', 'admin')->where('email_type', 'provider_registration')->first();
        $url = '';
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 1,
            subject: translate('Provider registration'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
