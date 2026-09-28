"use strict";

(function () {
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var lang = $('#vehicle_ai_lang').val() || 'en';
    var requestType = $('#vehicle_ai_request_type').val() || 'admin';
    var descRoute = $('#vehicle_ai_generate_desc_route').val();

    function toast(type, msg) {
        if (typeof toastr !== 'undefined') { toastr[type](msg); }
    }

    var $seoBtn = $('.vehicle-ai-generate[data-section="seo"]');
    var $metaHeader = $('#vehicle-meta-section .card .card-header').first();
    if ($seoBtn.length && $metaHeader.length) {
        $metaHeader.addClass('justify-content-between align-items-start flex-wrap gap-2');
        $seoBtn.appendTo($metaHeader);
    }
    $('.vehicle-ai-generate').each(function () {
        var $card = $(this).closest('.card');
        if ($card.length && $card.find('.card').length === 0 && !$card.parent().hasClass('outline-wrapper')) {
            $card.addClass('bg-animate').wrap('<div class="outline-wrapper"></div>');
        }
    });
    $('.outline-wrapper').each(function () {
        var child = this.firstElementChild;
        if (child) this.style.borderRadius = window.getComputedStyle(child).borderRadius;
    });

    function getVehicleName() {
        var v = $('#default_name').val();
        if (!v) v = $('input[name="name[]"]').first().val();
        return (v || '').trim();
    }

    function getVehicleDescription() {
        var id = 'default_description';
        if (window.CKEDITOR && CKEDITOR.instances[id]) return CKEDITOR.instances[id].getData();
        return $('#' + id).val() || '';
    }

    function fillName(name) {
        if (!name) return;
        var $name = $('#default_name');
        if (!$name.length) $name = $('input[name="name[]"]').first();
        $name.val(name).trigger('input').trigger('focus');
        try {
            var $form = $name.closest('form');
            if ($form.length && $.fn.validate && $form.data('validator')) {
                $form.validate().element($name);
            }
        } catch (e) {}
    }

    function fillDescription(text) {
        if (!text) return;
        var id = 'default_description';
        if (window.CKEDITOR && CKEDITOR.instances[id]) {
            CKEDITOR.instances[id].setData(text);
        } else {
            $('#' + id).val(text).trigger('change');
        }
    }

    function fillThumbnail(file) {
        if (!file) return;
        var input = document.getElementById('thumbnail');
        if (!input) return;
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            $(input).trigger('change');
        } catch (e) {}
    }

    function fillVehicleInfo(d) {
        if (d.model) $('input[name="model"]').val(d.model);
        if (d.type) $('#choice_type').val(d.type).trigger('change');
        if (d.fuel_type) $('#choice_fuel_type').val(d.fuel_type).trigger('change');
        if (d.transmission_type) $('#choice_transmission_type').val(d.transmission_type).trigger('change');
        if (d.seating_capacity) $('input[name="seating_capacity"]').val(d.seating_capacity);
        if (d.engine_capacity) $('input[name="engine_capacity"]').val(d.engine_capacity);
        if (d.engine_power) $('input[name="engine_power"]').val(d.engine_power);
        if (typeof d.air_condition !== 'undefined') {
            $('input[name="air_condition"][value="' + (d.air_condition ? 1 : 0) + '"]').prop('checked', true);
        }
        if (d.brand_id) $('#choice_brand').val(String(d.brand_id)).trigger('change');
        if (d.category_id) $('#choice_category').val(String(d.category_id)).trigger('change');
        if (d.tags && d.tags.length) fillTags(d.tags);
    }

    function fillPricing(d) {
        setTrip('trip_hourly', 'hourly_price', d.hourly_price);
        setTrip('trip_day_wise', 'day_wise_price', d.day_wise_price);
        setTrip('trip_distance', 'distance_price', d.distance_price);
        if (d.discount_price != null) {
            $('#discount_input').val(d.discount_price);
            $('#discount_type').val('percent').trigger('change');
        }
    }

    function setTrip(checkboxName, priceName, value) {
        if (value == null) return;
        var $cb = $('input[name="' + checkboxName + '"]');
        if ($cb.length && !$cb.is(':checked')) {
            $cb.prop('checked', true).trigger('change');
        }
        $('input[name="' + priceName + '"]').prop('disabled', false).val(value);
    }

    function fillTags(tags) {
        if (!tags || !tags.length) return;
        var $sel = $('#pickup_zones12');
        if (!$sel.length) return;
        var existing = ($sel.val() || []).map(String);
        tags.forEach(function (tag) {
            if (existing.indexOf(String(tag)) === -1) {
                $sel.append(new Option(tag, tag, true, true));
                existing.push(String(tag));
            }
        });
        $sel.trigger('change');
    }

    function fillSeo(d) {
        if (d.meta_title) $('textarea[name="meta_title"]').val(d.meta_title);
        if (d.meta_description) $('textarea[name="meta_description"]').val(d.meta_description);
    }

    var SECTION_FILLERS = {
        'title': function (d) { fillName(d.title); },
        'generate-description': function (d) { fillDescription(d.description); },
        'vehicle-info': fillVehicleInfo,
        'pricing': fillPricing,
        'tags': function (d) { fillTags(d.tags); },
        'seo': fillSeo
    };

    $(document).on('click', '.vehicle-ai-generate', function () {
        var $btn = $(this);
        var section = $btn.data('section');
        var route = $btn.data('route');
        var name = getVehicleName();

        if (!name) {
            toast('error', $btn.data('error') || 'Please provide a vehicle name first.');
            return;
        }

        sectionBtnLoading($btn, true);
        $.ajax({
            url: route,
            method: 'POST',
            data: { _token: csrf, name: name, description: getVehicleDescription(), langCode: lang, requestType: requestType },
            success: function (res) {
                var data = (res && res.data) || {};
                if (SECTION_FILLERS[section]) SECTION_FILLERS[section](data);
            },
            error: function (xhr) {
                toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'AI could not generate a result.');
            },
            complete: function () { sectionBtnLoading($btn, false); }
        });
    });

    function sectionBtnLoading($btn, loading) {
        var section = $btn.data('section');
        var $text = $btn.find('.btn-text');
        var $anim = $btn.find('.ai-text-animation');
        var $card = $btn.closest('.card');
        var $wrappers = $card.parent('.outline-wrapper');
        if (section === 'title') {
            $wrappers = $wrappers.add($('#default_name').closest('.outline-wrapper'));
        } else if (section === 'generate-description') {
            $wrappers = $wrappers.add($('#default_description').closest('.outline-wrapper'));
        }
        if (loading) {
            $btn.prop('disabled', true);
            $text.text('');
            $anim.removeClass('d-none').addClass('ai-text-animation-visible');
            $card.addClass('active');
            $wrappers.addClass('outline-animating');
        } else {
            $btn.prop('disabled', false);
            $text.text('Re-generate');
            $anim.addClass('d-none').removeClass('ai-text-animation-visible');
            setTimeout(function () {
                $card.removeClass('active');
                $wrappers.removeClass('outline-animating');
            }, 400);
        }
    }

    function runAutoChain() {
        var steps = [
            { section: 'vehicle-info', delay: 1200 },
            { section: 'pricing', delay: 2800 },
            { section: 'tags', delay: 4400 },
            { section: 'seo', delay: 6000 }
        ];
        steps.forEach(function (step) {
            setTimeout(function () {
                var $btn = $('.vehicle-ai-generate[data-section="' + step.section + '"]').first();
                if (!$btn.length || !getVehicleName()) return;
                var $card = $btn.closest('.card');
                if ($card.length && $card.offset()) {
                    $('html, body').animate({ scrollTop: $card.offset().top - 100 }, 600);
                }
                $btn.trigger('click');
            }, step.delay);
        });
    }

    var $modal = $('#vehicleAiModal');
    if (!$modal.length) return;

    var selectedImageFile = null;

    function showPane(id) {
        $('.ai-modal-content', $modal).hide();
        $('#' + id).show();
    }

    $modal.on('click', '.vehicle-ai-action-btn', function () {
        showPane($(this).data('action') === 'image' ? 'vehicleAiImage' : 'vehicleAiKeyword');
    });
    $modal.on('click', '.vehicle-ai-back-btn', function () { showPane('vehicleAiMain'); });
    $modal.on('show.bs.modal shown.bs.modal', function () { showPane('vehicleAiMain'); });

    function requestDescription(name) {
        if (!descRoute || !name) return;
        $.ajax({
            url: descRoute,
            method: 'POST',
            data: { _token: csrf, name: name, langCode: lang, requestType: requestType },
            success: function (res) {
                if (res && res.data && res.data.description) fillDescription(res.data.description);
            }
        });
    }

    $('#vehicleAiGenerateTitles').on('click', function () {
        var keywords = ($('#vehicleAiKeywords').val() || '').trim();
        if (!keywords) { toast('error', 'Please enter a keyword.'); return; }
        var $btn = $(this);
        $btn.prop('disabled', true).find('.ai-loader-animation').removeClass('d-none');
        $btn.find('.tio-arrow-forward').addClass('d-none');
        $('#vehicleAiTitlesWrapper').show();
        $('.vehicle-ai-generating', $modal).removeClass('d-none');
        $('.vehicle-ai-titles-heading', $modal).addClass('d-none');
        $('#vehicleAiTitles').empty();

        $.ajax({
            url: $btn.data('route'),
            method: 'POST',
            data: { _token: csrf, keywords: keywords, langCode: lang, requestType: requestType },
            success: function (res) {
                var titles = (res && res.data && res.data.titles) ? res.data.titles : [];
                $('.vehicle-ai-generating', $modal).addClass('d-none');
                if (!titles.length) { toast('error', 'AI could not generate a result.'); return; }
                $('.vehicle-ai-titles-heading', $modal).removeClass('d-none');
                var $list = $('#vehicleAiTitles').empty();
                titles.forEach(function (title) {
                    var $item = $('<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2"></button>');
                    $item.append($('<span></span>').text(title));
                    $item.append('<i class="tio-add-circle-outlined text--primary"></i>');
                    $item.on('click', function () {
                        fillName(title);
                        requestDescription(title);
                        $modal.modal('hide');
                        toast('success', 'Vehicle name applied. Generating the rest…');
                        setTimeout(runAutoChain, 1500);
                    });
                    $list.append($item);
                });
            },
            error: function (xhr) {
                $('.vehicle-ai-generating', $modal).addClass('d-none');
                toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'AI could not generate a result.');
            },
            complete: function () {
                $btn.prop('disabled', false).find('.ai-loader-animation').addClass('d-none');
                $btn.find('.tio-arrow-forward').removeClass('d-none');
            }
        });
    });

    var $analyzeBtn = $('#vehicleAiAnalyze');

    $('#vehicleAiImageInput').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        selectedImageFile = file;
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#vehicleAiPreviewImg').attr('src', e.target.result);
            $('#vehicleAiImagePreview').show();
            $('#vehicleAiChooseImage .text-box').hide();
        };
        reader.readAsDataURL(file);
        $analyzeBtn.prop('disabled', false);
    });

    $('#vehicleAiRemoveImage').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        selectedImageFile = null;
        $('#vehicleAiImageInput').val('');
        $('#vehicleAiImagePreview').hide();
        $('#vehicleAiChooseImage .text-box').show();
        $analyzeBtn.prop('disabled', true);
    });

    $analyzeBtn.on('click', function () {
        if (!selectedImageFile) { toast('error', 'Please upload an image.'); return; }
        var $btn = $(this);
        var $btnText = $btn.find('.btn-text');
        var originalText = $btnText.text();
        $btn.prop('disabled', true).find('.ai-btn-animation').removeClass('d-none');
        $btnText.text('Generating…');

        var fd = new FormData();
        fd.append('image', selectedImageFile);
        fd.append('langCode', lang);
        fd.append('requestType', requestType === 'admin' ? 'admin' : 'image');

        $.ajax({
            url: $btn.data('route'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': csrf },
            success: function (res) {
                var data = (res && res.data) || {};
                if (data.name) fillName(data.name);
                if (data.description) fillDescription(data.description);
                fillThumbnail(selectedImageFile);
                $modal.modal('hide');
                toast('success', 'Vehicle info generated.');
                setTimeout(runAutoChain, 800);
            },
            error: function (xhr) {
                toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'AI could not generate a result.');
            },
            complete: function () {
                $btn.prop('disabled', false).find('.ai-btn-animation').addClass('d-none');
                $btnText.text(originalText);
            }
        });
    });
})();
