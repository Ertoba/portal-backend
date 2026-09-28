<?php

namespace App\Builder;

use Modules\Builder\Contracts\CapabilityProvider as CapabilityProviderContract;
use Modules\Builder\ValueObjects\HostCapabilities;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6amMart capability manifest adapter.
 *
 * Phase 1: passes through the published `config/builder.php` capabilities block.
 * As each capability axis is wired (location, payment rails, currency, …), the
 * data-driven flags get **derived** here from host business settings and merged
 * UNDER the config — `array_replace_recursive($derived, $config)` — so an
 * explicit config value always overrides derivation, and derivation overrides
 * nothing (the config baseline). See the per-axis phases.
 */
class CapabilityProvider implements CapabilityProviderContract
{
    public function capabilities(?StorefrontScope $scope): HostCapabilities
    {
        $config = (array) config('builder.capabilities', []);

        // ── Back-compat shim (Phase 2) ─────────────────────────────────────
        // The legacy `wallet_features_enabled` flag is still the operator's
        // source of truth for wallet/partial during the transition, so FORCE
        // the manifest to mirror it (overriding the config baseline). Once the
        // shim is removed, `capabilities.features.wallet` becomes authoritative.
        $walletOn = (bool) config('builder.wallet_features_enabled', true);
        $config['features']['wallet'] = $walletOn;
        $config['payment']['wallet']  = $walletOn;
        $config['payment']['partial'] = $walletOn;

        // Derivation hook — more data-driven flags get merged here as later
        // phases land (location rails, payment buckets, currency, …), UNDER the
        // config so an explicit config value always wins:
        //   $config = array_replace_recursive($derived, $config);

        return HostCapabilities::fromArray($config);
    }
}
