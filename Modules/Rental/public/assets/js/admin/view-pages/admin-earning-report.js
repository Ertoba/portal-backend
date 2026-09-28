"use strict";

(function ($) {
    const root = document.getElementById("rental-admin-earning-report");
    if (!root) {
        return;
    }

    let currentTransactionType = "order";
    let currentTransactionSearch = "";
    let earningTrendChart = null;
    let earningExpenseChart = null;
    let earningsChart = null;

    const reportCurrencySymbol = root.dataset.currencySymbol || document.documentElement.getAttribute("data-currency-symbol") || "";
    const reportCurrencyPosition = root.dataset.currencyPosition || "left";
    const reportCurrencyDecimals = Number(root.dataset.currencyDecimals || 2);

    const ids = {
        summary: "#rental_admin_earning_summary",
        breakdown: "#rental_admin_earning_breakdown",
        expense: "#rental_admin_expense_breakdown",
        trend: "#rental-admin-earning-trend-chart",
        earningVsExpense: "#rental-admin-earning-expense-chart",
        source: "#rental-admin-earnings-pie-chart",
        topProvider: "#rental_admin_top_earning_providers",
        zoneWise: "#rental_admin_zone_wise_earnings"
    };

    const placeholders = {
        order: root.dataset.placeholderOrder,
        expense: root.dataset.placeholderExpense,
        subscription: root.dataset.placeholderSubscription
    };

    function params(extra) {
        return Object.assign({
            module_id: "all",
            filter: $("#rental_admin_filter").val(),
            from: $("#rental_admin_start_date").val(),
            to: $("#rental_admin_end_date").val()
        }, extra || {});
    }

    function formatGraphValue(value) {
        const absValue = Math.abs(Number(value) || 0);
        if (absValue >= 1000000000) return (value / 1000000000).toFixed(1).replace(/\.0$/, "") + "B";
        if (absValue >= 1000000) return (value / 1000000).toFixed(1).replace(/\.0$/, "") + "M";
        if (absValue >= 1000) return (value / 1000).toFixed(1).replace(/\.0$/, "") + "K";
        return Math.round(value).toString();
    }

    function formatReportCurrency(value) {
        const formattedNumber = Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: reportCurrencyDecimals,
            maximumFractionDigits: reportCurrencyDecimals
        });

        const currencyText = reportCurrencySymbol || root.dataset.currencyCode || "";

        return reportCurrencyPosition === "right"
            ? formattedNumber + " " + currencyText
            : currencyText + " " + formattedNumber;
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

    function toggleCustomDate(wrapper) {
        const selectValue = wrapper.find(".date-type-select").val();
        if (selectValue === "custom") {
            wrapper.find(".custom-date-div").slideDown(200);
        } else {
            wrapper.find(".custom-date-div").slideUp(200);
        }
    }

    function initDateFilters() {
        $(".date-filter-wrapper").each(function () {
            toggleCustomDate($(this));
        });

        $(document).on("change", ".date-type-select", function () {
            toggleCustomDate($(this).closest(".date-filter-wrapper"));
        });

        $("#rental_admin_start_date").on("change", function () {
            $("#rental_admin_end_date").attr("min", $(this).val());
        });

        $("#rental_admin_end_date").on("change", function () {
            $("#rental_admin_start_date").attr("max", $(this).val());
        });

        const initialStartDate = $("#rental_admin_start_date").val();
        const initialEndDate = $("#rental_admin_end_date").val();

        if (initialStartDate) {
            $("#rental_admin_end_date").attr("min", initialStartDate);
        }

        if (initialEndDate) {
            $("#rental_admin_start_date").attr("max", initialEndDate);
        }
    }

    function fetchSection(selector, url) {
        $.ajax({
            url: url,
            type: "GET",
            data: params(),
            success: function (response) {
                $(selector).html(response.view);
                if (selector === ids.breakdown && response.earnings) {
                    renderEarningsBySource(response.earnings);
                }
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    }

    function renderTrend(categories, earnings) {
        const chartElement = document.querySelector(ids.trend);
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
            series: [{
                name: "Earning",
                data: finalSeries
            }],
            chart: {
                height: 350,
                type: "line",
                toolbar: { show: false }
            },
            colors: ["#019463"],
            stroke: {
                width: 2,
                curve: "smooth"
            },
            markers: {
                size: 4,
                strokeWidth: 0,
                hover: {
                    size: 6
                }
            },
            dataLabels: { enabled: false },
            xaxis: { categories: finalCategories },
            yaxis: {
                min: 0,
                max: maxValue,
                tickAmount: 4,
                labels: {
                    offsetX: -10,
                    formatter: function (val) {
                        return formatGraphValue(val);
                    }
                }
            },
            grid: {
                strokeDashArray: 4,
                padding: {
                    left: 18,
                    right: 12,
                    bottom: 12
                }
            },
            tooltip: {
                theme: "dark",
                shared: false,
                x: { show: false },
                y: {
                    formatter: function (val, opts) {
                        const point = opts.w.globals.categoryLabels[opts.dataPointIndex];
                        return point + " : " + formatReportCurrency(val);
                    }
                }
            }
        };

        earningTrendChart = new ApexCharts(chartElement, options);
        earningTrendChart.render();
    }

    function renderEarningVsExpense(categories, earnings, expenses) {
        const chartElement = document.querySelector(ids.earningVsExpense);
        if (!chartElement) {
            return;
        }

        if (earningExpenseChart) {
            earningExpenseChart.destroy();
            earningExpenseChart = null;
        }

        const safeCategories = Array.isArray(categories) ? categories : [];
        const safeEarnings = Array.isArray(earnings) ? earnings : [];
        const safeExpenses = Array.isArray(expenses) ? expenses : [];

        let maxValue = Math.max(
            safeEarnings.length ? Math.max.apply(null, safeEarnings) : 0,
            safeExpenses.length ? Math.max.apply(null, safeExpenses) : 0
        );
        maxValue = maxValue <= 0 ? 1 : Math.ceil(maxValue * 1.1);
        let columnWidth = window.innerWidth <= 768 ? '8px' : '13px';

        const options = {
            series: [
                {
                    name: "Earning",
                    data: safeEarnings
                },
                {
                    name: "Expense",
                    data: safeExpenses
                }
            ],
            chart: {
                type: "bar",
                height: 350,
                stacked: false,
                toolbar: { show: false }
            },
            colors: ["#059669E5", "#D97706E5"],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: columnWidth,
                    borderRadius: 5,
                    borderRadiusApplication: 'end',
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: safeCategories
            },
            yaxis: {
                min: 0,
                max: maxValue,
                tickAmount: 4,
                labels: {
                    offsetX: -10,
                    formatter: function (val) {
                        return formatGraphValue(val);
                    }
                }
            },
            grid: {
                borderColor: '#e5e7eb',
                padding: {
                    left: 18,
                    right: 12,
                    bottom: 12
                }
            },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatReportCurrency(val);
                    }
                }
            },
            legend: {
                position: "bottom",
                horizontalAlign: "center"
            }
        };

        earningExpenseChart = new ApexCharts(chartElement, options);
        earningExpenseChart.render();
    }

    function renderEarningsBySource(earnings) {
        const chartElement = document.querySelector(ids.source);
        if (!chartElement) {
            return;
        }

        const chartData = [
            Number(earnings.trip_commission || 0),
            Number(earnings.subscription_earning || 0),
            Number(earnings.additional_charge || 0)
        ];

        const options = {
            chart: {
                type: "donut",
                height: 350
            },
            series: chartData,
            labels: [
                "Trip Commission",
                "Subscription Packages",
                "Additional Fees"
            ],
            colors: ["#04BB7B", "#8B5CF6", "#EC4899"],
            legend: {
                position: "bottom",
                horizontalAlign: "center"
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(0) + "%";
                }
            },
            tooltip: {
                enabled: true,
                y: {
                    formatter: function (val, opts) {
                        const label = opts?.w?.globals?.labels?.[opts.seriesIndex] || "";
                        return label + ": " + formatReportCurrency(val);
                    }
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: "65%",
                        labels: {
                            show: true,
                            value: {
                                show: true,
                                formatter: function (val) {
                                    return formatReportCurrency(val);
                                }
                            },
                            total: {
                                show: true,
                                fontSize: '12px',
                                label: "Total Earning",
                                formatter: function (w) {
                                    const total = w.globals.seriesTotals.reduce(function (a, b) {
                                        return a + b;
                                    }, 0);
                                    return formatReportCurrency(total);
                                }
                            }
                        }
                    }
                }
            }
        };

        if (earningsChart) {
            earningsChart.updateOptions(options);
            earningsChart.updateSeries(chartData);
            return;
        }

        earningsChart = new ApexCharts(chartElement, options);
        earningsChart.render();
    }

    function fetchTrend() {
        $.ajax({
            url: root.dataset.trendUrl,
            type: "GET",
            data: params(),
            success: function (response) {
                renderTrend(response.categories || [], response.earning_series || []);
                renderEarningVsExpense(response.categories || [], response.earning_series || [], response.expense_series || []);
            }
        });
    }

    function currentTableSelector() {
        if (currentTransactionType === "expense") {
            return "#rental-admin-expense-transaction-table";
        }

        if (currentTransactionType === "subscription") {
            return "#rental-admin-subscription-transaction-table";
        }

        return "#rental-admin-order-transaction-table";
    }

    function normalizeRequestUrl(url) {
        const requestUrl = new URL(url || root.dataset.transactionsUrl, window.location.href);
        return new URL(requestUrl.pathname + requestUrl.search, window.location.origin).toString();
    }

    function fetchTransactions(url) {
        $.ajax({
            url: normalizeRequestUrl(url),
            type: "GET",
            data: params({
                type: currentTransactionType,
                search: currentTransactionSearch
            }),
            success: function (response) {
                $(currentTableSelector()).html(response.view);
            }
        });
    }

    function updateSearchPlaceholder() {
        $("#rental-admin-transaction-search").attr("placeholder", placeholders[currentTransactionType] || placeholders.order);
    }

    function exportTransactions(exportType) {
        const query = new URLSearchParams(params({
            type: currentTransactionType,
            search: currentTransactionSearch,
            export_type: exportType
        }));

        window.location.href = root.dataset.exportUrl + "?" + query.toString();
    }

    function loadSections() {
        fetchSection(ids.summary, root.dataset.summaryUrl);
        fetchSection(ids.breakdown, root.dataset.breakdownUrl);
        fetchSection(ids.expense, root.dataset.expenseUrl);
        fetchSection(ids.topProvider, root.dataset.topProviderUrl);
        fetchSection(ids.zoneWise, root.dataset.zoneWiseUrl);
        fetchTrend();
        fetchTransactions();
    }

    $(document).ready(function () {
        initDateFilters();
        updateSearchPlaceholder();
        loadSections();

        $(document).on("click", ".rental-admin-transaction-tab", function (event) {
            event.preventDefault();
            $(".rental-admin-transaction-tab").removeClass("active");
            $(this).addClass("active");
            currentTransactionType = $(this).data("type");
            currentTransactionSearch = "";
            $("#rental-admin-transaction-search").val("");
            updateSearchPlaceholder();
            $(this).tab("show");
            fetchTransactions();
        });

        $("#rental-admin-transaction-search-form").on("submit", function (event) {
            event.preventDefault();
            currentTransactionSearch = $("#rental-admin-transaction-search").val();
            fetchTransactions();
        });

        $("#rental-admin-transaction-search").on("input", function () {
            if (this.value === "" && currentTransactionSearch !== "") {
                currentTransactionSearch = "";
                fetchTransactions();
            }
        });

        $("#rental-admin-transaction-search").on("search", function () {
            if (this.value === "" && currentTransactionSearch !== "") {
                currentTransactionSearch = "";
                fetchTransactions();
            }
        });

        $(document).on("click", "#rental-admin-export-excel", function () {
            exportTransactions("excel");
        });

        $(document).on("click", "#rental-admin-export-csv", function () {
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
    });
})(jQuery);
