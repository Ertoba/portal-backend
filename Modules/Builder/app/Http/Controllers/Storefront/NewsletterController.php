<?php

namespace Modules\Builder\Http\Controllers\Storefront;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Builder\Contracts\NewsletterProvider;

class NewsletterController extends StorefrontController
{
    public function __construct(
        protected NewsletterProvider $newsletter,
    ) {
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
        ]);

        $result = $this->newsletter->subscribe($data['email']);

        // Flash (not withErrors) so the storefront's global FlashToaster shows
        // feedback as a toast on both success and failure/already-subscribed.
        if (! ($result['success'] ?? false)) {
            $message = $result['errors'][0]['message'] ?? (__('messages.subscription_failed') ?: 'Subscription failed.');
            return \back()->with('flash', ['error' => $message]);
        }

        return \back()->with('flash', [
            'success' => __('messages.subscription_successful') ?: 'Subscribed successfully!',
        ]);
    }
}
