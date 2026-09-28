"use strict";

(function ($) {
    const root = document.getElementById("rental-provider-earning-report");
    if (!root) {
        return;
    }

    let currentTransactionType = "order";
    let currentTransactionSearch = "";
    let earningTrendChart = null;

    function currentPlaceholder() {
        if (currentTransactionType === "expense") {
            return root.dataset.placeholderExpense;
        }
        if (currentTransactionType === "subscription") {
            return root.dataset.placeholderSubscription;
        }

        return root.dataset.placeholderOrder;
    }

    function params(extra) {
        const moduleId = $("#rental_provider_module_id").val();
        const storeId = $("#rental_provider_store_id").val();

        return Object.assign({
            module_id: moduleId || "all",
            store_id: storeId || "all",
            filter: $("#rental_provider_filter").val(),
            from: $("#rental_provider_start_date").val(),
            to: $("#rental_provider_end_date").val()
        }, extra || {});
    }

    function toggleCustomDate(wrapper) {
        const selectValue = wrapper.find(".date-type-select").val();
        if (selectValue === "custom") {
            wrapper.find(".custom-date-div").slideDown(200);
        } else {
            wrapper.find(".custom-date-div").slideUp(200);
        }
    }

    function buildSinglePointParabola(categories, values) {
        if (!Array.isArray(categories) || !Array.isArray(values) || categories.length !== 1 || values.length !== 1) {
            return { categories, values };
        }

        const selectedLabel = categories[0];
        const selectedDate = new Date(selectedLabel);
        if (Number.isNaN(selectedDate.getTime())) {
            return { categories, values };
        }

        const monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const formatLabel = function (date) {
            const day = String(date.getDate()).padStart(2, "0");
            return day + " " + monthLabels[date.getMonth()] + " " + date.getFullYear();
        };

        const previousDate = new Date(selectedDate);
        previousDate.setDate(previousDate.getDate() - 1);

        const nextDate = new Date(selectedDate);
        nextDate.setDate(nextDate.getDate() + 1);

        return {
            categories: [formatLabel(previousDate), selectedLabel, formatLabel(nextDate)],
            values: [0, Number(values[0]) || 0, 0]
        };
    }

    function renderTrend(categories, earnings) {
        const chartElement = document.getElementById("rental-provider-earning-trend-chart");
        if (!chartElement) {
            return;
        }

        if (earningTrendChart) {
            earningTrendChart.destroy();
            earningTrendChart = null;
        }

        const chartData = buildSinglePointParabola(categories, earnings);
        const finalCategories = chartData.categories;
        const finalSeries = chartData.values;
        let maxValue = Math.max.apply(null, finalSeries);
        maxValue = maxValue <= 0 ? 1 : Math.ceil(maxValue * 1.1);

        const options = {
            series: [{ name: "Earning", data: finalSeries }],
            chart: { height: 350, type: "line", toolbar: { show: false } },
            colors: ["#019463"],
            stroke: { width: 2, curve: "smooth" },
            markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
            dataLabels: { enabled: false },
            xaxis: { categories: finalCategories },
            yaxis: {
                min: 0,
                max: maxValue,
                tickAmount: 4
            }
        };

        earningTrendChart = new ApexCharts(chartElement, options);
        earningTrendChart.render();
    }

    function fetchSection(selector, url) {
        $.ajax({
            url: url,
            type: "GET",
            data: params(),
            success: function (response) {
                $(selector).html(response.view);
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    }

    function currentTableSelector() {
        if (currentTransactionType === "expense") return "#rental-provider-expense-transaction-table";
        if (currentTransactionType === "subscription") return "#rental-provider-subscription-transaction-table";
        return "#rental-provider-order-transaction-table";
    }

    function normalizeRequestUrl(url) {
        const requestUrl = new URL(url || root.dataset.transactionsUrl, window.location.href);
        return new URL(requestUrl.pathname + requestUrl.search, window.location.origin).toString();
    }

    function fetchTransactions(url) {
        $.ajax({
            url: normalizeRequestUrl(url),
            type: "GET",
            data: params({ type: currentTransactionType, search: currentTransactionSearch }),
            success: function (response) {
                $(currentTableSelector()).html(response.view);
            }
        });
    }

    function exportTransactions(exportType) {
        const query = new URLSearchParams(params({
            type: currentTransactionType,
            search: currentTransactionSearch,
            export_type: exportType
        }));

        window.location.href = root.dataset.exportUrl + "?" + query.toString();
    }

    function syncDateConstraints() {
        const startDate = $("#rental_provider_start_date").val();
        const endDate = $("#rental_provider_end_date").val();

        $("#rental_provider_end_date").attr("min", startDate || null);
        $("#rental_provider_start_date").attr("max", endDate || null);
    }

    $(document).ready(function () {
        $(".date-filter-wrapper").each(function () {
            toggleCustomDate($(this));
        });
        syncDateConstraints();
        $("#rental-provider-transaction-search").attr("placeholder", currentPlaceholder());

        $(document).on("change", ".date-type-select", function () {
            toggleCustomDate($(this).closest(".date-filter-wrapper"));
        });

        $(document).on("change", "#rental_provider_start_date, #rental_provider_end_date", function () {
            syncDateConstraints();
        });

        fetchSection("#rental_provider_earning_summary", root.dataset.summaryUrl);
        fetchSection("#rental_provider_earning_breakdown", root.dataset.breakdownUrl);
        fetchSection("#rental_provider_expense_breakdown", root.dataset.expenseUrl);

        $.ajax({
            url: root.dataset.trendUrl,
            type: "GET",
            data: params(),
            success: function (response) {
                renderTrend(response.categories || [], response.earning_series || []);
            }
        });

        fetchTransactions();

        $(document).on("click", ".rental-provider-transaction-tab", function (event) {
            event.preventDefault();
            $(".rental-provider-transaction-tab").removeClass("active");
            $(this).addClass("active");
            currentTransactionType = $(this).data("type");
            currentTransactionSearch = "";
            $("#rental-provider-transaction-search").val("");
            $("#rental-provider-transaction-search").attr("placeholder", currentPlaceholder());
            $(this).tab("show");
            fetchTransactions();
        });

        $("#rental-provider-transaction-search-form").on("submit", function (event) {
            event.preventDefault();
            currentTransactionSearch = $("#rental-provider-transaction-search").val();
            fetchTransactions();
        });

        $("#rental-provider-transaction-search").on("input", function () {
            if (this.value === "" && currentTransactionSearch !== "") {
                currentTransactionSearch = "";
                fetchTransactions();
            }
        });

        $("#rental-provider-transaction-search").on("search", function () {
            if (this.value === "" && currentTransactionSearch !== "") {
                currentTransactionSearch = "";
                fetchTransactions();
            }
        });

        $(document).on("click", "#rental-provider-export-excel", function () {
            exportTransactions("excel");
        });

        $(document).on("click", "#rental-provider-export-csv", function () {
            exportTransactions("csv");
        });

        $(document).on("click", ".page-area .pagination a", function (event) {
            const containerId = $(this).closest(".datatable-custom").attr("id");
            const activeContainerId = currentTableSelector().replace("#", "");
            if (containerId !== activeContainerId) {
                return;
            }

            event.preventDefault();
            fetchTransactions($(this).attr("href"));
        });

        $(document).on("click", ".collapse-next-tr", function () {
            const $currentRow = $(this).closest("tr");
            const $targetRow = $currentRow.next(".collapsing-tr");

            $targetRow.toggleClass("d-none");
            $(this).find("i").toggleClass("tio-chevron-down tio-chevron-up");
        });

        const providerSelect = document.querySelector(".js-data-example-ajax");
        if (providerSelect && providerSelect.dataset.getProviderUrl) {
            $(providerSelect).select2({
                ajax: {
                    url: providerSelect.dataset.getProviderUrl,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page
                        };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    __port: function (params, success, failure) {
                        let $request = $.ajax(params);
                        $request.then(success);
                        $request.fail(failure);
                        return $request;
                    }
                }
            });
        }
    });
})(jQuery);
