'use strict';
$('.status_change_alert').on('click', function (event) {
    let url = $(this).data('url');
    let message = $(this).data('message');
    status_change_alert(url, message, event)
})

function status_change_alert(url, message, e) {
    e.preventDefault();
    Swal.fire({
        title: $('#data-set').data('translate-are-you-sure'),
        text: message,
        type: 'warning',
        showCancelButton: true,
        cancelButtonColor: 'default',
        confirmButtonColor: '#FC6A57',
        cancelButtonText: $('#data-set').data('translate-no'),
        confirmButtonText: $('#data-set').data('translate-yes'),
        reverseButtons: true
    }).then((result) => {
        if (result.value) {
            location.href=url;
        }
    })
}
$(document).on('ready', function () {

    let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

    $('#column1_search').on('keyup', function () {
        datatable
            .columns(1)
            .search(this.value)
            .draw();
    });

    $('#column2_search').on('keyup', function () {
        datatable
            .columns(2)
            .search(this.value)
            .draw();
    });

    $('#column3_search').on('keyup', function () {
        datatable
            .columns(3)
            .search(this.value)
            .draw();
    });

    $('#column4_search').on('keyup', function () {
        datatable
            .columns(4)
            .search(this.value)
            .draw();
    });

    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

 function resetEligibleProviderSelection() {
        $('#select-all-eligible-stores').prop('checked', false);
        $('#eligible-store-footer').addClass('d-none');
        $('#eligible-store-list .eligible-store-item').removeClass('border border-success bg-success bg-opacity-10');
        $('#eligible-store-list .eligible-give-btn').removeClass('d-none');
        $('#eligible-store-list .eligible-store-check').addClass('d-none');
    }

    $(document).on('click', '#open-eligible-store-list', function (e) {
        e.preventDefault();
        $('#offcanvas__customBtn3').removeClass('open');
        $('#offcanvas__eligibleStores').addClass('open');
        $('#offcanvasOverlay').addClass('show');
        resetEligibleProviderSelection();
    });

    $(document).on('click', '#back-to-verification', function (e) {
        e.preventDefault();
        $('#offcanvas__eligibleStores').removeClass('open');
        $('#offcanvas__customBtn3').addClass('open');
        $('#offcanvasOverlay').addClass('show');
        resetEligibleProviderSelection();
    });

    $(document).on('change', '#select-all-eligible-stores', function () {
        const checked = $(this).is(':checked');
        $('#eligible-store-list .eligible-store-item').toggleClass('border border-success bg-success bg-opacity-10', checked);
        $('#eligible-store-list .eligible-give-btn').toggleClass('d-none', checked);
        $('#eligible-store-list .eligible-store-check').toggleClass('d-none', !checked);
        $('#eligible-store-footer').toggleClass('d-none', !checked);
    });

    $(document).on('click', '#verify-all-stores', function (e) {
        e.preventDefault();
        $('#offcanvas__eligibleStores').removeClass('open');
        $('#offcanvas__customBtn3').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        resetEligibleProviderSelection();
        $('#verify-all-modal').modal('show');
    });

    $(document).on('click', '#verify-all-summary', function (e) {
        e.preventDefault();
        $('#offcanvas__customBtn3').removeClass('open');
        $('#offcanvas__eligibleStores').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        resetEligibleProviderSelection();
        $('#verify-all-modal').modal('show');
    });

    $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
        resetEligibleProviderSelection();
    });
