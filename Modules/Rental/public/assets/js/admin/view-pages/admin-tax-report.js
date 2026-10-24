
    "use strict";
          $(document).ready(function() {
            function updateUI() {
                if ($('#date_range_type').val() == 'custom') {
                    $('#date_range').removeClass('d-none');
                } else {
                    $('#date_range').addClass('d-none');
                }

                if ($('#calculate_tax_on').val() == 'individual_source') {
                    $('#calculate_commission_tax').removeClass('d-none');
                    $('#calculate_delivery_charge_tax').removeClass('d-none');
                    $('#calculate_service_charge_tax').removeClass('d-none');
                    $('#calculate_subscription_tax').removeClass('d-none');
                    $('#calculate_tax_rate').addClass('d-none').find('select').attr('required', false);
                } else {
                    $('#calculate_tax_rate').removeClass('d-none').find('select').attr('required', true);
                    $('#calculate_commission_tax').addClass('d-none');
                    $('#calculate_delivery_charge_tax').addClass('d-none');
                    $('#calculate_service_charge_tax').addClass('d-none');
                    $('#calculate_subscription_tax').addClass('d-none');
                }
            }
            updateUI();
            $('#date_range_type').on('change', updateUI);
            $('#calculate_tax_on').on('change', updateUI);
            $('#reset_button_id').on('click', function() {
                // Only the tax pickers clear: the two fixed-choice selects go
                // back to their default option, which the form's own reset does
                // on the tick after this handler — select2 is re-synced to the
                // value it lands on rather than blanked.
                $('.js-select2-custom[multiple]').val(null).trigger('change');
                setTimeout(() => {
                    $('.js-select2-custom:not([multiple])').trigger('change.select2');
                    updateUI();
                }, 1);
            });
        });
