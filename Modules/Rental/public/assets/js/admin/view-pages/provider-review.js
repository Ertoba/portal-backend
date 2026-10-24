"use strict";
// Call the dataTables jQuery plugin
$(document).ready(function () {
    $('#dataTable').DataTable();


    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

