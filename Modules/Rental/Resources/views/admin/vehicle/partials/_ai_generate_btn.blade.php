@if ($openai_enabled ?? false)
    <button type="button"
            class="btn bg-white text-primary opacity-1 generate_btn_wrapper p-0 mb-2 vehicle-ai-generate"
            data-section="{{ $section }}"
            data-route="{{ route($aiRoutePrefix . '.' . $section) }}"
            data-error="{{ $error ?? translate('messages.Please provide a vehicle name first so the AI can generate.') }}">
        <div class="btn-svg-wrapper">
            <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/blink-right-small.svg') }}" alt="">
        </div>
        <span class="ai-text-animation d-none" role="status">{{ translate('Just_a_second') }}</span>
        <span class="btn-text">{{ translate('Generate') }}</span>
    </button>
@endif
