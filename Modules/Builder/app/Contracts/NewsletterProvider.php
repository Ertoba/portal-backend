<?php

namespace Modules\Builder\Contracts;

/**
 * Storefront footer "Subscribe to newsletter" submission.
 *
 * Persists the email into whatever list the host uses for newsletter
 * subscribers. 6amMart stores them in the shared `newsletters` table that
 * the admin landing (`NewsletterController::newsLetterSubscribe`) already
 * writes to, so admins see all subscribers in one place.
 *
 * Bound to App\Builder\NewsletterProvider via auto-discovery in
 * AppServiceProvider::registerBuilderBindings(); a different host can swap
 * the adapter (e.g. to push into Mailchimp / a CRM).
 */
interface NewsletterProvider
{
    /**
     * Persist a newsletter subscription for the given email.
     *
     * Returns success:
     *   ['success' => true]
     *
     * Returns failure (including "already subscribed"):
     *   ['success' => false, 'errors' => [['code' => string, 'message' => string], …]]
     */
    public function subscribe(string $email): array;
}
