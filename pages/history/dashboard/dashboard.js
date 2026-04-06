window.HistoryDashboardTabModule = (function () {

    function createPageController() {

        return {

            context: null,
            dom: {},
            listeners: [],
            groupChart: null,
            weeklyChart: null,
            themeObserver: null,
            currentGroupBreakdown: null,
            currentWeeklyBreakdown: null,

            init: function (context) {

                this.context = context;
                this.listeners = [];
                this.cacheDom();
                this.initSelectPickers();
                this.bindEvents();
                this.exposeGlobals();
                this.renderCharts();
            },

            destroy: function () {

                this.listeners.forEach(({ element, eventName, handler }) => {

                    element.removeEventListener(eventName, handler);
                });

                if (this.themeObserver) {

                    this.themeObserver.disconnect();
                }

                if (this.groupChart && typeof this.groupChart.destroy === 'function') {

                    this.groupChart.destroy();
                }

                if (this.weeklyChart && typeof this.weeklyChart.destroy === 'function') {

                    this.weeklyChart.destroy();
                }

                this.context = null;
                this.dom = {};
                this.listeners = [];
                this.groupChart = null;
                this.weeklyChart = null;
                this.themeObserver = null;
                this.currentGroupBreakdown = null;
                this.currentWeeklyBreakdown = null;
                delete window.parseFilter;
                delete window.btnFilter;
                delete window.btnClear;
            },

            cacheDom: function () {

                this.dom = {
                    floatingMenu: document.querySelector('.floating-menu'),
                    floatingMenuButton: document.querySelector('.floating-menu-btn'),
                    appliedFiltersRoot: document.querySelector('.filter-labels'),
                    viewButtons: document.querySelectorAll('[data-action="set-view"]'),
                    clearFiltersButton: document.querySelector('[data-action="clear-filters"]'),
                    applyFiltersButton: document.querySelector('[data-action="apply-filters"]'),
                    resetGroupChartButton: document.querySelector('[data-action="reset-group-chart"]'),
                    resetWeeklyChartButton: document.querySelector('[data-action="reset-weekly-chart"]')
                };
            },

            initSelectPickers: function () {

                if (window.CrmMultiSelect && typeof window.CrmMultiSelect.init === 'function') {

                    window.CrmMultiSelect.init(document);
                }
            },

            bindEvents: function () {

                this.bindListener(this.dom.floatingMenuButton, 'click', () => {

                    if (!this.dom.floatingMenu || !this.dom.floatingMenuButton) {

                        return;
                    }

                    this.dom.floatingMenu.classList.toggle('open');
                    this.dom.floatingMenuButton.classList.toggle('open');
                });

                this.bindListener(this.dom.appliedFiltersRoot, 'click', (event) => {

                    const closePopoverButton = event.target.closest('[data-close-filter-popover]');

                    if (closePopoverButton) {

                        event.preventDefault();
                        event.stopPropagation();
                        this.closeClientFilterPopover(closePopoverButton.closest('.filter-label-clients'));
                        return;
                    }

                    const button = event.target.closest('[data-remove-filter]');

                    if (!button) {

                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    this.removeAppliedFilter(button.getAttribute('data-remove-filter') || '');
                });

                this.bindListener(this.dom.appliedFiltersRoot, 'mouseover', (event) => {

                    const clientFilter = event.target.closest('.filter-label-clients');

                    if (!clientFilter) {

                        return;
                    }

                    this.openClientFilterPopover(clientFilter);
                });

                this.bindListener(document, 'click', (event) => {

                    if (event.target.closest('.filter-label-clients')) {

                        return;
                    }

                    this.closeClientFilterPopover();
                });

                this.bindListener(this.dom.clearFiltersButton, 'click', () => this.clearFilters());
                this.bindListener(this.dom.applyFiltersButton, 'click', () => this.applyFilters());
                this.bindListener(this.dom.resetGroupChartButton, 'click', () => this.renderGroupChart());
                this.bindListener(this.dom.resetWeeklyChartButton, 'click', () => this.renderWeeklyChart());
                this.dom.viewButtons.forEach((button) => {

                    this.bindListener(button, 'click', () => this.setView(button.getAttribute('data-view') || 'outcomes'));
                });
                this.bindThemeObserver();
            },

            bindListener: function (element, eventName, handler) {

                if (!element) {

                    return;
                }

                element.addEventListener(eventName, handler);
                this.listeners.push({ element, eventName, handler });
            },

            exposeGlobals: function () {

                window.parseFilter = this.parseFilters.bind(this);
                window.btnFilter = this.applyFilters.bind(this);
                window.btnClear = this.clearFilters.bind(this);
            },

            bindThemeObserver: function () {

                if (!window.MutationObserver || !document.body) {

                    return;
                }

                this.themeObserver = new MutationObserver((mutations) => {

                    const hasThemeChange = mutations.some((mutation) => mutation.attributeName === 'data-bs-theme');

                    if (!hasThemeChange) {

                        return;
                    }

                    this.renderCurrentCharts();
                });

                this.themeObserver.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['data-bs-theme']
                });
            },

            isDarkTheme: function () {

                return document.body.getAttribute('data-bs-theme') === 'dark';
            },

            getChartStyles: function () {

                const textColor = this.isDarkTheme() ? '#ffffff' : '#212529';
                const mutedTextColor = this.isDarkTheme() ? 'rgba(255, 255, 255, 0.72)' : '#6c757d';
                const gridLineColor = this.isDarkTheme() ? 'rgba(255, 255, 255, 0.12)' : 'rgba(33, 37, 41, 0.12)';

                return {
                    textColor: textColor,
                    mutedTextColor: mutedTextColor,
                    gridLineColor: gridLineColor
                };
            },

            renderCurrentCharts: function () {

                if (this.currentGroupBreakdown) {

                    this.renderGroupBreakdownChart(this.currentGroupBreakdown.groupKey, this.currentGroupBreakdown.label);
                } else {

                    this.renderGroupChart();
                }

                if (this.currentWeeklyBreakdown) {

                    this.renderWeeklyBreakdownChart(this.currentWeeklyBreakdown.weekKey, this.currentWeeklyBreakdown.label);
                } else {

                    this.renderWeeklyChart();
                }
            },

            openClientFilterPopover: function (clientFilter) {

                document.querySelectorAll('.filter-label-clients.is-popover-visible').forEach((element) => {

                    if (element !== clientFilter) {

                        element.classList.remove('is-popover-visible');
                    }
                });

                if (clientFilter) {

                    clientFilter.classList.add('is-popover-visible');
                }
            },

            closeClientFilterPopover: function (clientFilter = null) {

                if (clientFilter) {

                    clientFilter.classList.remove('is-popover-visible');
                    return;
                }

                document.querySelectorAll('.filter-label-clients.is-popover-visible').forEach((element) => {

                    element.classList.remove('is-popover-visible');
                });
            },

            parseFilters: function () {

                const params = {
                    tab: 'dashboard',
                    view: this.currentViewMode()
                };
                const errors = [];
                const dateFrom = document.querySelector('#data_da')?.value || '';
                const dateTo = document.querySelector('#data_a')?.value || '';
                const includeAllDates = document.querySelector('#data_all')?.checked || false;

                if (dateFrom !== '' && dateTo !== '' && dateTo < dateFrom) {

                    errors.push('End date cannot be earlier than start date');
                }

                if (includeAllDates) {

                    params.data_all = '1';
                } else {

                    if (dateFrom !== '') {

                        params.data_da = dateFrom;
                    }

                    if (dateTo !== '') {

                        params.data_a = dateTo;
                    }
                }

                this.appendMultiValue(params, 'group', $('#group').val() || []);
                this.appendMultiValue(params, 'name', $('#name').val() || []);

                if (errors.length > 0) {

                    alert(errors.join('\n'));
                    return null;
                }

                return params;
            },

            appendMultiValue: function (params, key, values) {

                if (Array.isArray(values) && values.length > 0) {

                    params[key] = values;
                }
            },

            applyFilters: function () {

                const params = this.parseFilters();

                if (!params) {

                    return;
                }

                this.context.app.reloadCurrentPage(params);
            },

            clearFilters: function () {

                this.context.app.reloadCurrentPage({
                    tab: 'dashboard',
                    view: this.currentViewMode()
                });
            },

            setView: function (view) {

                const nextView = ['outcomes', 'current_outcomes', 'next_outcomes'].includes(view)
                    ? view
                    : 'outcomes';

                this.context.app.reloadCurrentPage({
                    ...(this.context.params || {}),
                    tab: 'dashboard',
                    view: nextView
                });
            },

            currentViewMode: function () {

                const viewMode = this.readJsonScript('history_dashboard_view_mode_json', 'outcomes');

                return ['outcomes', 'current_outcomes', 'next_outcomes'].includes(viewMode)
                    ? viewMode
                    : 'outcomes';
            },

            removeAppliedFilter: function (filterKey) {

                if (!filterKey) {

                    return;
                }

                const requestParams = {
                    ...(this.context.params || {}),
                    tab: 'dashboard',
                    view: this.currentViewMode()
                };
                const browserParams = {
                    ...(this.context.params || {}),
                    tab: 'dashboard',
                    view: this.currentViewMode()
                };

                delete requestParams.page_num;
                delete browserParams.page_num;
                requestParams[filterKey] = '__clear__';
                delete browserParams[filterKey];

                if (filterKey === 'data_da' || filterKey === 'data_a') {

                    delete requestParams.data_all;
                    delete browserParams.data_all;
                }

                this.context.app.reloadCurrentPage(requestParams, browserParams);
            },

            readJsonScript: function (id, fallback) {

                const element = document.getElementById(id);

                if (!element) {

                    return fallback;
                }

                try {

                    return JSON.parse(element.textContent || '');
                } catch (error) {

                    return fallback;
                }
            },

            ensureHighcharts: function () {

                if (window.Highcharts) {

                    return Promise.resolve(window.Highcharts);
                }

                if (window.__historyDashboardHighchartsPromise) {

                    return window.__historyDashboardHighchartsPromise;
                }

                window.__historyDashboardHighchartsPromise = new Promise((resolve, reject) => {

                    const script = document.createElement('script');
                    script.src = 'https://code.highcharts.com/highcharts.js';
                    script.onload = () => resolve(window.Highcharts);
                    script.onerror = () => reject(new Error('Unable to load Highcharts'));
                    document.head.appendChild(script);
                });

                return window.__historyDashboardHighchartsPromise;
            },

            renderCharts: function () {

                this.ensureHighcharts()
                    .then((Highcharts) => {

                        const groupSeries = this.readJsonScript('history_dashboard_group_series_json', []);
                        const groupNameBreakdown = this.readJsonScript('history_dashboard_group_name_breakdown_json', {});
                        const weeklySeries = this.readJsonScript('history_dashboard_weekly_series_json', { categories: [], data: [] });
                        const weeklyNameBreakdown = this.readJsonScript('history_dashboard_weekly_name_breakdown_json', {});

                        this.groupChartData = groupSeries;
                        this.groupBreakdownData = groupNameBreakdown;
                        this.weeklyChartData = weeklySeries;
                        this.weeklyBreakdownData = weeklyNameBreakdown;
                        this.Highcharts = Highcharts;
                        this.renderGroupChart();
                        this.renderWeeklyChart();
                    })
                    .catch(() => {

                        this.context.app.showFail('Unable to load charts');
                    });
            },

            renderGroupChart: function () {

                if (!this.Highcharts) {

                    return;
                }

                if (this.groupChart && typeof this.groupChart.destroy === 'function') {

                    this.groupChart.destroy();
                }

                if (this.dom.resetGroupChartButton) {

                    this.dom.resetGroupChartButton.classList.add('d-none');
                }

                this.currentGroupBreakdown = null;

                const chartStyles = this.getChartStyles();

                this.groupChart = this.Highcharts.chart('history_dashboard_groups_chart', {
                    chart: {
                        type: 'pie',
                        backgroundColor: 'transparent'
                    },
                    title: {
                        text: 'By group',
                        style: {
                            color: chartStyles.textColor
                        }
                    },
                    plotOptions: {
                        pie: {
                            dataLabels: {
                                color: chartStyles.textColor
                            },
                            cursor: 'pointer',
                            point: {
                                events: {
                                    click: (event) => {

                                        const pointOptions = this.groupChartData[event.point.index] || {};
                                        const groupKey = String(pointOptions.custom && pointOptions.custom.groupKey ? pointOptions.custom.groupKey : '');
                                        const label = String(event.point.name || '');
                                        this.renderGroupBreakdownChart(groupKey, label);
                                    }
                                }
                            }
                        }
                    },
                    series: [{
                        name: 'Outcomes',
                        data: this.groupChartData
                    }],
                    credits: {
                        enabled: false
                    }
                });
            },

            renderGroupBreakdownChart: function (groupKey, label) {

                if (!this.Highcharts || !groupKey || !this.groupBreakdownData || !this.groupBreakdownData[groupKey]) {

                    return;
                }

                const breakdown = this.groupBreakdownData[groupKey];

                if (this.groupChart && typeof this.groupChart.destroy === 'function') {

                    this.groupChart.destroy();
                }

                if (this.dom.resetGroupChartButton) {

                    this.dom.resetGroupChartButton.classList.remove('d-none');
                }

                this.currentGroupBreakdown = {
                    groupKey: groupKey,
                    label: label
                };

                const chartStyles = this.getChartStyles();

                this.groupChart = this.Highcharts.chart('history_dashboard_groups_chart', {
                    chart: {
                        type: 'bar',
                        backgroundColor: 'transparent'
                    },
                    title: {
                        text: label,
                        style: {
                            color: chartStyles.textColor
                        }
                    },
                    xAxis: {
                        categories: breakdown.categories,
                        labels: {
                            style: {
                                color: chartStyles.textColor
                            }
                        }
                    },
                    yAxis: {
                        gridLineColor: chartStyles.gridLineColor,
                        labels: {
                            style: {
                                color: chartStyles.textColor
                            }
                        },
                        title: {
                            text: null,
                            style: {
                                color: chartStyles.textColor
                            }
                        }
                    },
                    series: [{
                        name: 'Outcomes',
                        data: breakdown.data
                    }],
                    credits: {
                        enabled: false
                    }
                });
            },

            renderWeeklyChart: function () {

                if (!this.Highcharts) {

                    return;
                }

                if (this.weeklyChart && typeof this.weeklyChart.destroy === 'function') {

                    this.weeklyChart.destroy();
                }

                if (this.dom.resetWeeklyChartButton) {

                    this.dom.resetWeeklyChartButton.classList.add('d-none');
                }

                this.currentWeeklyBreakdown = null;

                const chartStyles = this.getChartStyles();

                this.weeklyChart = this.Highcharts.chart('history_dashboard_weekly_chart', {
                    chart: {
                        type: 'column',
                        backgroundColor: 'transparent'
                    },
                    title: {
                        text: 'Outcomes',
                        style: {
                            color: chartStyles.textColor
                        }
                    },
                    xAxis: {
                        categories: this.weeklyChartData.categories,
                        labels: {
                            style: {
                                color: chartStyles.textColor
                            }
                        }
                    },
                    yAxis: {
                        gridLineColor: chartStyles.gridLineColor,
                        labels: {
                            style: {
                                color: chartStyles.textColor
                            }
                        },
                        title: {
                            text: null,
                            style: {
                                color: chartStyles.textColor
                            }
                        }
                    },
                    plotOptions: {
                        series: {
                            cursor: 'pointer',
                            point: {
                                events: {
                                    click: (event) => {

                                        const pointOptions = this.weeklyChartData.data[event.point.index] || {};
                                        const weekKey = String(pointOptions.custom && pointOptions.custom.weekKey ? pointOptions.custom.weekKey : '');
                                        const label = String(event.point.category || '');
                                        this.renderWeeklyBreakdownChart(weekKey, label);
                                    }
                                }
                            }
                        }
                    },
                    series: [{
                        name: 'Outcomes',
                        data: this.weeklyChartData.data
                    }],
                    credits: {
                        enabled: false
                    }
                });
            },

            renderWeeklyBreakdownChart: function (weekKey, label) {

                if (!this.Highcharts || !weekKey || !this.weeklyBreakdownData || !this.weeklyBreakdownData[weekKey]) {

                    return;
                }

                const breakdown = this.weeklyBreakdownData[weekKey];

                if (this.weeklyChart && typeof this.weeklyChart.destroy === 'function') {

                    this.weeklyChart.destroy();
                }

                if (this.dom.resetWeeklyChartButton) {

                    this.dom.resetWeeklyChartButton.classList.remove('d-none');
                }

                this.currentWeeklyBreakdown = {
                    weekKey: weekKey,
                    label: label
                };

                const chartStyles = this.getChartStyles();

                this.weeklyChart = this.Highcharts.chart('history_dashboard_weekly_chart', {
                            chart: {
                                type: 'bar',
                                backgroundColor: 'transparent'
                            },
                            title: {
                                text: label,
                                style: {
                                    color: chartStyles.textColor
                                }
                            },
                            xAxis: {
                                categories: breakdown.categories,
                                labels: {
                                    style: {
                                        color: chartStyles.textColor
                                    }
                                }
                            },
                            yAxis: {
                                gridLineColor: chartStyles.gridLineColor,
                                labels: {
                                    style: {
                                        color: chartStyles.textColor
                                    }
                                },
                                title: {
                                    text: null,
                                    style: {
                                        color: chartStyles.textColor
                                    }
                                }
                            },
                            series: [{
                                name: 'Outcomes',
                                data: breakdown.data
                            }],
                            credits: {
                                enabled: false
                            }
                        });
            }
        };
    }

    return createPageController();
}());
