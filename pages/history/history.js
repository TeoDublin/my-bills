window.PageModules = window.PageModules || {};

window.PageModules['history'] = (function () {

    const MODAL_IDS = [
        'history_page_setup_modal',
        'history_export_setup_modal',
        'history_grid_import_modal',
        'history_action_progress_modal'
    ];

    function createPageController() {

        return {

            context: null,
            dom: {},
            modals: {},
            listeners: [],
            hoistedModals: [],
            pollTimer: null,

            init: function (context) {

                this.context = context;
                this.listeners = [];
                this.hoistedModals = [];
                this.pollTimer = null;

                this.hoistPageModals();
                this.cacheDom();
                this.cacheModals();
                this.initSelectPickers();
                this.bindEvents();
                this.exposeGlobals();
                this.syncInitialFilterUrl();
            },

            destroy: function () {

                this.listeners.forEach(({ element, eventName, handler }) => {

                    element.removeEventListener(eventName, handler);
                });

                if ($.fn.selectpicker) {

                    $('.selectpicker').selectpicker('destroy');
                }

                if (this.pollTimer) {

                    window.clearTimeout(this.pollTimer);
                }

                Object.values(this.modals).forEach((instance) => {

                    if (instance && typeof instance.dispose === 'function') {

                        instance.dispose();
                    }
                });

                this.hoistedModals.forEach((modal) => {

                    if (modal && modal.parentNode) {

                        modal.parentNode.removeChild(modal);
                    }
                });

                delete window.parseFilter;
                delete window.btnFiltra;
                delete window.btnClean;

                this.context = null;
                this.dom = {};
                this.modals = {};
                this.listeners = [];
                this.hoistedModals = [];
                this.pollTimer = null;
            },

            hoistPageModals: function () {

                MODAL_IDS.forEach((modalId) => {

                    const modal = document.getElementById(modalId);

                    if (!modal || modal.parentNode === document.body) {

                        return;
                    }

                    document.body.appendChild(modal);
                    this.hoistedModals.push(modal);
                });
            },

            cacheDom: function () {

                const actionsRoot = document.querySelector('[data-history-actions]');
                const progressModalElement = document.getElementById('history_action_progress_modal');

                this.dom = {

                    floatingMenu: document.querySelector('.floating-menu'),
                    floatingMenuButton: document.querySelector('.floating-menu-btn'),
                    appliedFiltersRoot: document.querySelector('.filter-labels'),
                    clearFiltersButton: document.querySelector('[data-action="clear-filters"]'),
                    applyFiltersButton: document.querySelector('[data-action="apply-filters"]'),
                    saveSetupButton: document.querySelector('[data-action="save-setup"]'),
                    runPageActionButton: document.querySelector('[data-action="run-page-action"]'),
                    confirmExportSetupButton: document.querySelector('[data-action="confirm-export-setup"]'),
                    confirmGridExportButton: document.querySelector('[data-action="confirm-grid-export"]'),
                    closeProgressButton: document.querySelector('[data-action="close-progress"]'),
                    downloadExportButton: document.querySelector('[data-action="download-export"]'),
                    actionSelect: actionsRoot ? actionsRoot.querySelector('select[name="actions"]') : null,
                    actionsRoot: actionsRoot,
                    exportScopeSelect: document.getElementById('history_export_scope'),
                    exportTypeSelect: document.getElementById('history_export_type'),
                    gridImportFileInput: document.getElementById('grid_import_file'),
                    rowsPerPageInput: document.getElementById('rows_per_page'),
                    tableFontSizeInput: document.getElementById('table_font_size'),
                    progressModalElement: progressModalElement,
                    progressTitle: progressModalElement ? progressModalElement.querySelector('[data-progress-title]') : null,
                    progressBar: progressModalElement ? progressModalElement.querySelector('[data-progress-bar]') : null,
                    progressError: progressModalElement ? progressModalElement.querySelector('[data-progress-error]') : null
                };
            },

            cacheModals: function () {

                this.modals = {

                    setup: this.getModalInstance('history_page_setup_modal'),
                    exportSetup: this.getModalInstance('history_export_setup_modal'),
                    gridImport: this.getModalInstance('history_grid_import_modal'),
                    progress: this.getModalInstance('history_action_progress_modal')
                };
            },

            getModalInstance: function (modalId) {

                const element = document.getElementById(modalId);

                if (!element || !window.bootstrap || !window.bootstrap.Modal) {

                    return null;
                }

                return window.bootstrap.Modal.getOrCreateInstance(element);
            },

            initSelectPickers: function () {

                if (window.CrmMultiSelect && typeof window.CrmMultiSelect.init === 'function') {

                    window.CrmMultiSelect.init(document);
                }
            },

            bindEvents: function () {

                this.bindClick(this.dom.floatingMenuButton, () => {

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

                this.bindClick(this.dom.clearFiltersButton, () => this.clearFilters());
                this.bindClick(this.dom.applyFiltersButton, () => this.applyFilters());
                this.bindClick(this.dom.saveSetupButton, () => this.saveSetup());
                this.bindClick(this.dom.runPageActionButton, () => this.runPageAction());
                this.bindClick(this.dom.confirmExportSetupButton, () => this.confirmExportSetup());
                this.bindClick(this.dom.confirmGridExportButton, () => this.startGridExport());
                this.bindClick(this.dom.closeProgressButton, () => this.closeProgressModal());

                this.bindPagination();
                this.bindTableSelection();
            },

            bindClick: function (element, handler) {

                this.bindListener(element, 'click', handler);
            },

            bindListener: function (element, eventName, handler) {

                if (!element) {

                    return;
                }

                element.addEventListener(eventName, handler);
                this.listeners.push({ element, eventName, handler });
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

            bindPagination: function () {

                document.querySelectorAll('[data-page-number]').forEach((button) => {

                    this.bindClick(button, () => {

                        const pageNumber = Number.parseInt(button.dataset.pageNumber, 10);

                        if (!Number.isFinite(pageNumber) || pageNumber < 1) {

                            return;
                        }

                        this.goToPage(pageNumber);
                    });
                });
            },

            bindTableSelection: function () {

                document.querySelectorAll('[data-table-selectable]').forEach((table) => {

                    const selectAll = table.querySelector('[data-table-select-all]');
                    const rowCheckboxes = Array.from(table.querySelectorAll('[data-table-row-select]'));
                    const rowSelectCells = Array.from(table.querySelectorAll('[data-table-row-select-cell]'));

                    if (!selectAll || rowCheckboxes.length === 0) {

                        return;
                    }

                    const syncSelectAllState = () => {

                        const checkedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
                        selectAll.checked = checkedCount === rowCheckboxes.length;
                        selectAll.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
                    };

                    this.bindListener(selectAll, 'change', () => {

                        rowCheckboxes.forEach((checkbox) => {

                            checkbox.checked = selectAll.checked;
                        });

                        selectAll.indeterminate = false;
                    });

                    rowCheckboxes.forEach((checkbox) => {

                        this.bindListener(checkbox, 'change', syncSelectAllState);
                    });

                    rowSelectCells.forEach((cell) => {

                        this.bindClick(cell, (event) => {

                            if (event.target.closest('input, label, button, a')) {

                                return;
                            }

                            const checkbox = cell.querySelector('[data-table-row-select]');

                            if (!checkbox) {

                                return;
                            }

                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });

                    syncSelectAllState();
                });
            },

            runPageAction: function () {

                const action = this.dom.actionSelect ? this.dom.actionSelect.value : '';

                if (action !== 'export') {

                    this.context.app.showFail('Azione non supportata');
                    return;
                }

                if (this.modals.exportSetup) {

                    this.modals.exportSetup.show();
                    return;
                }

                this.confirmExportSetup();
            },

            confirmExportSetup: function () {

                const scope = this.dom.exportScopeSelect ? this.dom.exportScopeSelect.value : 'filter';
                const exportType = this.dom.exportTypeSelect ? this.dom.exportTypeSelect.value : '';

                if (scope === 'grid') {

                    this.hideModal(this.modals.exportSetup);
                    this.showModal(this.modals.gridImport);
                    return;
                }

                this.hideModal(this.modals.exportSetup);
                this.startExportJob({
                    scope: scope,
                    exportType: exportType,
                    selectedIds: scope === 'selected' ? this.getSelectedRowIds() : []
                });
            },

            startGridExport: function () {

                const fileInput = this.dom.gridImportFileInput;

                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {

                    this.context.app.showFail('Carica prima un file XLSX');
                    return;
                }

                this.hideModal(this.modals.gridImport);
                this.startExportJob({
                    scope: 'grid',
                    exportType: this.dom.exportTypeSelect ? this.dom.exportTypeSelect.value : '',
                    file: fileInput.files[0],
                    selectedIds: []
                });
            },

            startExportJob: function (options) {

                if (!this.dom.actionsRoot) {

                    this.context.app.showFail('Azioni non disponibili');
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', this.dom.actionsRoot.dataset.csrfToken || '');
                formData.append('handler', options.exportType || '');
                formData.append('scope', options.scope || 'filter');
                formData.append('filters', JSON.stringify(this.context.params || {}));
                formData.append('selected_ids', JSON.stringify(options.selectedIds || []));

                if (options.file) {

                    formData.append('grid_file', options.file);
                }

                $.ajax({

                    url: '/my-bills/fragments/async/start.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                })
                .done((response) => {

                    if (!response || response.ok !== true || !response.job || !response.job.job_key) {

                        this.context.app.showFail((response && response.error) || 'Impossibile avviare l\'export');
                        return;
                    }

                    if (this.dom.gridImportFileInput) {

                        this.dom.gridImportFileInput.value = '';
                    }

                    this.showProgressModal();
                    this.updateProgressUi(response.job);
                    this.pollExportJob(response.job.job_key);
                })
                .fail((jqXHR) => {

                    this.context.app.showFail(this.readAjaxError(jqXHR, 'Impossibile avviare l\'export'));
                });
            },

            pollExportJob: function (jobKey) {

                if (this.pollTimer) {

                    window.clearTimeout(this.pollTimer);
                    this.pollTimer = null;
                }

                $.post('/my-bills/fragments/async/progress.php', {

                    csrf_token: this.dom.actionsRoot.dataset.csrfToken || '',
                    job_key: jobKey
                }).done((response) => {

                    if (!response || response.ok !== true || !response.job) {

                        this.finishProgressWithError((response && response.error) || 'Impossibile aggiornare il progresso');
                        return;
                    }

                    this.updateProgressUi(response.job);

                    if (response.job.status === 'completed' || response.job.status === 'failed') {

                        return;
                    }

                    this.pollTimer = window.setTimeout(() => this.pollExportJob(jobKey), 500);
                }).fail((jqXHR) => {

                    this.finishProgressWithError(this.readAjaxError(jqXHR, 'Impossibile aggiornare il progresso'));
                });
            },

            updateProgressUi: function (job) {

                const progressBarData = Array.isArray(job.progress_bars) && job.progress_bars.length > 0
                    ? job.progress_bars[0]
                    : null;
                const percent = progressBarData && Number.isFinite(Number(progressBarData.percent))
                    ? Math.max(0, Math.min(100, Number(progressBarData.percent)))
                    : 0;

                if (this.dom.progressTitle) {

                    this.dom.progressTitle.textContent = progressBarData && progressBarData.title
                        ? progressBarData.title
                        : 'Preparazione export';
                }

                if (this.dom.progressBar) {

                    this.dom.progressBar.style.width = `${percent}%`;
                    this.dom.progressBar.textContent = `${percent}%`;
                    this.dom.progressBar.setAttribute('aria-valuenow', String(percent));
                }

                if (job.status === 'completed') {

                    if (this.dom.progressBar) {

                        this.dom.progressBar.classList.remove('progress-bar-animated');
                    }

                    if (this.dom.downloadExportButton && job.download_url) {

                        this.dom.downloadExportButton.href = job.download_url;
                        this.dom.downloadExportButton.classList.remove('d-none');
                    }

                    if (this.dom.closeProgressButton) {

                        this.dom.closeProgressButton.classList.remove('d-none');
                    }

                    if (this.dom.progressError) {

                        this.dom.progressError.classList.add('d-none');
                        this.dom.progressError.textContent = '';
                    }
                }

                if (job.status === 'failed') {

                    this.finishProgressWithError(job.error_message || 'Export non riuscito');
                }
            },

            finishProgressWithError: function (message) {

                if (this.pollTimer) {

                    window.clearTimeout(this.pollTimer);
                    this.pollTimer = null;
                }

                if (this.dom.progressBar) {

                    this.dom.progressBar.classList.remove('progress-bar-animated');
                }

                if (this.dom.progressError) {

                    this.dom.progressError.textContent = message;
                    this.dom.progressError.classList.remove('d-none');
                }

                if (this.dom.closeProgressButton) {

                    this.dom.closeProgressButton.classList.remove('d-none');
                }
            },

            showProgressModal: function () {

                if (this.dom.downloadExportButton) {

                    this.dom.downloadExportButton.classList.add('d-none');
                    this.dom.downloadExportButton.removeAttribute('href');
                }

                if (this.dom.closeProgressButton) {

                    this.dom.closeProgressButton.classList.add('d-none');
                }

                if (this.dom.progressError) {

                    this.dom.progressError.textContent = '';
                    this.dom.progressError.classList.add('d-none');
                }

                if (this.dom.progressBar) {

                    this.dom.progressBar.classList.add('progress-bar-animated');
                    this.dom.progressBar.style.width = '0%';
                    this.dom.progressBar.textContent = '0%';
                    this.dom.progressBar.setAttribute('aria-valuenow', '0');
                }

                this.showModal(this.modals.progress);
            },

            closeProgressModal: function () {

                this.hideModal(this.modals.progress);
            },

            readAjaxError: function (jqXHR, fallback) {

                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {

                    return jqXHR.responseJSON.error;
                }

                return fallback;
            },

            showModal: function (modalInstance) {

                if (modalInstance) {

                    modalInstance.show();
                }
            },

            hideModal: function (modalInstance) {

                if (modalInstance) {

                    modalInstance.hide();
                }
            },

            getSelectedRowIds: function () {

                return Array.from(document.querySelectorAll('tr[data-id]'))
                    .filter((row) => {

                        const checkbox = row.querySelector('[data-table-row-select]');
                        return checkbox && checkbox.checked;
                    })
                    .map((row) => Number(row.dataset.id))
                    .filter((id) => Number.isFinite(id) && id > 0);
            },

            exposeGlobals: function () {

                window.parseFilter = this.parseFilters.bind(this);
                window.btnFiltra = this.applyFilters.bind(this);
                window.btnClean = this.clearFilters.bind(this);
            },

            parseFilters: function () {

                const params = {};
                const errors = [];
                const dateFrom = document.querySelector('#data_da')?.value || '';
                const dateTo = document.querySelector('#data_a')?.value || '';
                const includeAllDates = document.querySelector('#data_all')?.checked || false;

                if (dateFrom !== '' && dateTo !== '' && dateTo < dateFrom) {

                    errors.push('Data fine non puo essere inferiore alla data inizio');
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

                const displayParams = this.parseDisplayPreferences();

                if (!displayParams) {

                    return null;
                }

                if (errors.length > 0) {

                    alert(errors.join('\\n'));
                    return null;
                }

                return { ...params, ...displayParams };
            },

            appendMultiValue: function (params, key, values) {

                if (Array.isArray(values) && values.length > 0) {

                    params[key] = values;
                }
            },

            ensureExplicitFilterState: function (params) {

                const nextParams = {

                    ...(params || {})
                };

                if (!this.hasActiveFilterParams(nextParams)) {

                    nextParams.data_all = '1';
                }

                return nextParams;
            },

            hasActiveFilterParams: function (params) {

                return ['group', 'name', 'data_all', 'data_da', 'data_a']
                    .some((key) => Object.prototype.hasOwnProperty.call(params || {}, key));
            },

            syncInitialFilterUrl: function () {

                if (this.hasActiveFilterParams(this.context.params)) {

                    return;
                }

                const params = this.parseFilters();

                if (!params) {

                    return;
                }

                const explicitParams = this.ensureExplicitFilterState(params);

                this.context.params = explicitParams;

                if (this.context.app && this.context.app.state) {

                    this.context.app.state.currentParams = explicitParams;
                }

                if (this.context.app && typeof this.context.app.syncBrowserUrl === 'function') {

                    this.context.app.syncBrowserUrl(this.context.pageId, explicitParams, true);
                }
            },

            applyFilters: function () {

                const params = this.parseFilters();

                if (!params) {

                    return;
                }

                this.context.app.reloadCurrentPage(this.ensureExplicitFilterState(params));
            },

            clearFilters: function () {

                const displayParams = this.parseDisplayPreferences();

                if (!displayParams) {

                    return;
                }

                this.context.app.reloadCurrentPage(this.ensureExplicitFilterState(displayParams));
            },

            removeAppliedFilter: function (filterKey) {

                if (!filterKey) {

                    return;
                }

                const requestParams = {

                    ...(this.context.params || {})
                };
                const browserParams = {

                    ...(this.context.params || {})
                };

                delete requestParams.page_num;
                delete browserParams.page_num;
                requestParams[filterKey] = '__clear__';
                delete browserParams[filterKey];

                if (filterKey === 'data_da' || filterKey === 'data_a') {

                    delete requestParams.data_all;
                    delete browserParams.data_all;
                }

                const displayParams = this.parseDisplayPreferences();

                if (!displayParams) {

                    return;
                }

                this.context.app.reloadCurrentPage(
                    this.ensureExplicitFilterState({

                        ...requestParams,
                        ...displayParams
                    }),
                    this.ensureExplicitFilterState({

                        ...browserParams,
                        ...displayParams
                    })
                );
            },

            goToPage: function (pageNumber) {

                this.context.app.reloadCurrentPage({

                    ...(this.context.params || {}),
                    page_num: String(pageNumber)
                });
            },

            parseDisplayPreferences: function () {

                const params = {};
                const errors = [];
                const rowsPerPage = this.dom.rowsPerPageInput ? this.dom.rowsPerPageInput.value : '';
                const tableFontSize = this.dom.tableFontSizeInput ? this.dom.tableFontSizeInput.value : '';

                this.validateIntegerRange(rowsPerPage, 5, 200, 'Le righe devono essere comprese tra 5 e 200', errors);
                this.validateIntegerRange(tableFontSize, 10, 40, 'La dimensione del testo deve essere compresa tra 10 e 40', errors);

                if (errors.length > 0) {

                    alert(errors.join('\\n'));
                    return null;
                }

                if (rowsPerPage !== '') {

                    params.rows_per_page = rowsPerPage;
                }

                if (tableFontSize !== '') {

                    params.table_font_size = tableFontSize;
                }

                return params;
            },

            validateIntegerRange: function (value, min, max, message, errors) {

                if (value === '') {

                    return;
                }

                const parsed = Number(value);

                if (!Number.isInteger(parsed) || parsed < min || parsed > max) {

                    errors.push(message);
                }
            },

            saveSetup: function () {

                const displayParams = this.parseDisplayPreferences();

                if (!displayParams) {

                    return;
                }

                this.hideModal(this.modals.setup);
                this.context.app.reloadCurrentPage({

                    ...(this.context.params || {}),
                    ...displayParams
                });
            },

        };
    }

    return createPageController();
}());
