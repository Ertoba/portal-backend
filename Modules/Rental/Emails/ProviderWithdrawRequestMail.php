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

class ProviderWithdrawRequestMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $wallet;

    protected $status;

    public function __construct($status, $wallet)
    {
        $this->wallet = $wallet;
        $this->status = $status;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $status = $this->status;
        if ($status == 'approved') {
            $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'withdraw_approve')->first();
        } elseif ($status == 'denied') {
            $data = RentalEmailTemplate::where('type', 'provider')->where('email_type', 'withdraw_deny')->first();
        } else {
            $data = RentalEmailTemplate::where('type', 'admin')->where('email_type', 'withdraw_request')->first();
        }
        $wallet = $this->wallet;
        $store_name = $wallet->vendor->f_name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 6,
            subject: translate('Provider withdraw request'),
            placeholders: [
                'store_name' => $store_name ?? '',
                'transaction_id' => $wallet->id ?? '',
            ],
            viewData: ['wallet' => $wallet, 'transaction_id' => $wallet->id, 'time' => $wallet->created_at, 'amount' => $wallet->amount],
            viewPrefix: 'email-templates.rental.new-email-format-',
        );
    }
}
