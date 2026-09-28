@if (\App\CentralLogics\Helpers::get_business_settings('verified_seller_badge') == 1 && isset($store?->storeConfig) && $store?->storeConfig?->verified_seller)
    {{-- Tooltip context auto-detected from the store's module:
         rental → "Verified Provider", anything else → "Verified Store". --}}
    <i class="tio-verified text-success" data-toggle="tooltip" data-placement="top"
        title="{{ translate('Verified') }} {{ translate(($store?->module_type ?? null) === 'rental' ? 'Provider' : 'Store') }}"></i>
@endif
