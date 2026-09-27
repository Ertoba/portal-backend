"use strict";
$(document).ready(function () {
    let originalData = {};

    $(document).on('click', '.get_data', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        originalData = {
            id,
            action: $(this).attr('data-action') || $(this).data('action') || buildUpdateAction(id),
            name: $(this).data('name'),
            tax_rate: $(this).data('tax_rate'),
            is_active: $('#status_' +  id).is(':checked'),
        };
        setDataOnModal(originalData)
    });

    $(document).on('click', '.reset', function () {
        setDataOnModal(originalData)
    });

    function setDataOnModal(originalData){
        const modal = $('#editTaxData');
        const action = originalData.action || buildUpdateAction(originalData.id);
        modal.find('form').attr('action', action);
        modal.find('#tax_name').val(originalData.name);
        modal.find('#tax_rate').val(originalData.tax_rate);
        modal.find('#tax_status').prop('checked', originalData.is_active == 1);
    };

    $(document).on('submit', '#editTaxData form', function (e) {
        const action = $(this).attr('action');
        if (isMissingOrIndexAction(action)) {
            const fallbackAction = buildUpdateAction(originalData.id);
            if (fallbackAction && !isMissingOrIndexAction(fallbackAction)) {
                $(this).attr('action', fallbackAction);
                return true;
            }

            e.preventDefault();
            console.error('Tax edit form action is missing.');
            if (typeof sent_notification === 'function') {
                sent_notification('errorMessage', 'Unable to update tax. Please reopen the edit form and try again.');
            }
            return false;
        }
    });

    function buildUpdateAction(id) {
        if (!id) return '';
        const form = $('#editTaxData form');
        const base = String(form.data('update-action-base') || '').replace(/\/$/, '');
        if (base) {
            return base + '/' + id;
        }
        const taxBasePath = window.location.pathname.replace(/\/get-taxvat-data\/?$/, '');
        return window.location.origin + taxBasePath.replace(/\/$/, '') + '/update-taxvat-data/' + id;
    }

    function isMissingOrIndexAction(action) {
        if (!action) return true;
        const url = new URL(action, window.location.origin);
        return /\/taxvat\/get-taxvat-data\/?$/.test(url.pathname);
    }
});

    document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".confirmStatus").forEach(checkbox => {
                checkbox.addEventListener("click", e => {
                    e.preventDefault();

                    const input = checkbox.querySelector(".toggle-switch-sm input");
                    const isChecked = input.checked;
                    const url = checkbox.dataset.url;

                    const title = checkbox.dataset[isChecked ? "off_title" : "on_title"];
                    const message = checkbox.dataset[isChecked ? "off_message" : "on_message"];

                    $('#confirmationTitle').text(title);
                    $('#confirmationMessage').text(message);
                    document.getElementById('seturl').dataset.url = url;
                    $('#exampleModal').modal('show');
                });
            });
        });


        document.getElementById('seturl').addEventListener('click', function() {
            const url = this.dataset.url;
            const is_active = this.dataset.is_active;

            if (!url) return console.error("No URL found for status change");

            $.get(url, {
                is_active
            }, function(response) {
                $('#exampleModal').modal('hide');
                $('#status_' + response.id).prop('checked', response.status);
                    sent_notification('successMessage' , response.message);
            }).fail(function(xhr) {
                console.error("Error updating status:", xhr.responseText);

            });
        });
