window.HistoryExtraIncomeTabModule = (function () {

    const MODAL_IDS = [
        'extra_income_page_setup_modal',
        'extra_income_add_modal'
    ];

    function createPageController() {

        return {

            context: null,
            dom: {},
            modals: {},
            listeners: [],
            hoistedModals: [],
            editingIncomeId: null,

            init: function (context) {

                this.context = context;
                this.listeners = [];
                this.hoistedModals = [];
                this.editingIncomeId = null;
                this.hoistPageModals();
                this.cacheDom();
                this.cacheModals();
                this.initSelectPickers();
                this.bindEvents();
                this.exposeGlobals();
            },

            destroy: function () {

                this.listeners.forEach(({ element, eventName, handler }) => {

                    element.removeEventListener(eventName, handler);
                });

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
                delete window.btnFilter;
                delete window.btnClear;

                this.context = null;
                this.dom = {};
                this.modals = {};
                this.listeners = [];
                this.hoistedModals = [];
                this.editingIncomeId = null;
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

                const actionsRoot = document.querySelector('[data-extra-income-actions]');

                this.dom = {
                    floatingMenu: document.querySelector('.floating-menu'),
                    floatingMenuButton: document.querySelector('.floating-menu-btn'),
                    appliedFiltersRoot: document.querySelector('.filter-labels'),
                    clearFiltersButton: document.querySelector('[data-action="clear-filters"]'),
                    applyFiltersButton: document.querySelector('[data-action="apply-filters"]'),
                    saveSetupButton: document.querySelector('[data-action="save-setup"]'),
                    openIncomeCreateButton: document.querySelector('[data-action="open-income-create"]'),
                    saveIncomeButton: document.querySelector('[data-action="save-income"]'),
                    runPageActionButton: document.querySelector('[data-action="run-page-action"]'),
                    actionSelect: actionsRoot ? actionsRoot.querySelector('select[name="actions"]') : null,
                    actionScopeSelect: document.getElementById('extra_income_action_scope'),
                    rowsPerPageInput: document.getElementById('rows_per_page'),
                    tableFontSizeInput: document.getElementById('table_font_size'),
                    nameFilterSelect: document.getElementById('extra_income_name'),
                    incomeNameInput: document.getElementById('extra_income_modal_name'),
                    incomeValueInput: document.getElementById('extra_income_modal_value'),
                    incomeDateInput: document.getElementById('extra_income_modal_date'),
                    incomeModalElement: document.getElementById('extra_income_add_modal'),
                    incomeModalTitle: document.querySelector('[data-income-modal-title]'),
                    incomeModalSubmit: document.querySelector('[data-income-modal-submit]')
                };
            },

            cacheModals: function () {

                this.modals = {
                    setup: this.getModalInstance('extra_income_page_setup_modal'),
                    addIncome: this.getModalInstance('extra_income_add_modal')
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
                this.bindClick(this.dom.openIncomeCreateButton, () => this.openIncomeModalForCreate());
                this.bindClick(this.dom.saveIncomeButton, () => this.saveIncome());
                this.bindClick(this.dom.runPageActionButton, () => this.runPageAction());
                this.bindListener(this.dom.incomeModalElement, 'keydown', (event) => {

                    if (event.key !== 'Enter' || event.shiftKey || event.target.closest('textarea')) {

                        return;
                    }

                    event.preventDefault();
                    this.saveIncome();
                });
                this.bindListener(this.dom.incomeModalElement, 'hidden.bs.modal', () => this.resetIncomeForm());

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

                        const pageNumber = Number.parseInt(button.dataset.pageNumber || '', 10);

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

                    Array.from(table.querySelectorAll('tbody tr[data-id]')).forEach((row) => {

                        this.bindClick(row, (event) => {

                            if (event.target.closest('input, label, button, a, [data-table-row-select-cell]')) {

                                return;
                            }

                            this.openIncomeModalForEdit(row);
                        });
                    });

                    Array.from(table.querySelectorAll('[data-action="delete-income"]')).forEach((button) => {

                        this.bindClick(button, (event) => {

                            event.preventDefault();
                            event.stopPropagation();
                            this.deleteIncome(button);
                        });
                    });

                    syncSelectAllState();
                });
            },

            parseFilters: function () {

                const params = {};
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

                this.appendMultiValue(params, 'name', $(this.dom.nameFilterSelect).val() || []);

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

                this.context.app.reloadCurrentPage({
                    tab: 'extra_income',
                    ...params
                });
            },

            clearFilters: function () {

                this.context.app.reloadCurrentPage({
                    tab: 'extra_income'
                });
            },

            removeAppliedFilter: function (filterKey) {

                if (!filterKey) {

                    return;
                }

                const requestParams = {
                    ...(this.context.params || {}),
                    tab: 'extra_income'
                };
                const browserParams = {
                    ...(this.context.params || {}),
                    tab: 'extra_income'
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

            saveSetup: function () {

                const params = {
                    ...(this.context.params || {}),
                    tab: 'extra_income',
                    rows_per_page: this.dom.rowsPerPageInput ? this.dom.rowsPerPageInput.value : '',
                    table_font_size: this.dom.tableFontSizeInput ? this.dom.tableFontSizeInput.value : ''
                };

                this.hideModal(this.modals.setup);
                this.context.app.reloadCurrentPage(params);
            },

            runPageAction: function () {

                const action = this.dom.actionSelect ? this.dom.actionSelect.value : '';

                if (action !== 'delete') {

                    this.context.app.showFail('Unsupported action');
                    return;
                }

                const scope = this.dom.actionScopeSelect ? this.dom.actionScopeSelect.value : 'filter';
                const selectedIds = scope === 'selected' ? this.getSelectedRowIds() : [];
                let message = 'Delete incomes using the current filter?';

                if (scope === 'selected') {

                    if (selectedIds.length === 0) {

                        this.context.app.showFail('Select at least one row');
                        return;
                    }

                    message = selectedIds.length === 1
                        ? 'Delete the selected income?'
                        : `Delete ${selectedIds.length} selected incomes?`;
                }

                if (!window.confirm(message)) {

                    return;
                }

                this.postManageAction({
                    action: 'bulk_delete_extra_incomes',
                    scope: scope,
                    filters: JSON.stringify(this.context.params || {}),
                    selected_ids: JSON.stringify(selectedIds)
                }, (response) => {

                    this.context.app.showSuccess(response.message || 'Incomes deleted');
                    this.context.app.reloadCurrentPage({
                        ...(this.context.params || {}),
                        tab: 'extra_income'
                    });
                });
            },

            openIncomeModalForCreate: function () {

                this.editingIncomeId = null;

                if (this.dom.incomeModalTitle) {

                    this.dom.incomeModalTitle.textContent = 'New income';
                }

                if (this.dom.incomeModalSubmit) {

                    this.dom.incomeModalSubmit.textContent = 'Save income';
                }

                this.resetIncomeForm();
                this.showModal(this.modals.addIncome);
            },

            openIncomeModalForEdit: function (row) {

                this.editingIncomeId = Number.parseInt(row.dataset.id || '', 10);

                if (!Number.isFinite(this.editingIncomeId) || this.editingIncomeId <= 0) {

                    this.editingIncomeId = null;
                    return;
                }

                if (this.dom.incomeNameInput) {

                    this.dom.incomeNameInput.value = row.dataset.name || '';
                }

                if (this.dom.incomeValueInput) {

                    this.dom.incomeValueInput.value = row.dataset.value || '';
                }

                if (this.dom.incomeDateInput) {

                    this.dom.incomeDateInput.value = row.dataset.date || this.dom.incomeDateInput.dataset.defaultValue || '';
                }

                if (this.dom.incomeModalTitle) {

                    this.dom.incomeModalTitle.textContent = 'Edit income';
                }

                if (this.dom.incomeModalSubmit) {

                    this.dom.incomeModalSubmit.textContent = 'Save changes';
                }

                this.showModal(this.modals.addIncome);
            },

            resetIncomeForm: function () {

                this.editingIncomeId = null;

                if (this.dom.incomeNameInput) {

                    this.dom.incomeNameInput.value = '';
                }

                if (this.dom.incomeValueInput) {

                    this.dom.incomeValueInput.value = '';
                }

                if (this.dom.incomeDateInput) {

                    this.dom.incomeDateInput.value = this.dom.incomeDateInput.dataset.defaultValue || '';
                }

                if (this.dom.incomeModalTitle) {

                    this.dom.incomeModalTitle.textContent = 'New income';
                }

                if (this.dom.incomeModalSubmit) {

                    this.dom.incomeModalSubmit.textContent = 'Save income';
                }
            },

            getIncomeFormPayload: function () {

                const name = this.dom.incomeNameInput ? this.dom.incomeNameInput.value.trim() : '';
                const value = this.dom.incomeValueInput ? this.dom.incomeValueInput.value.trim() : '';
                const date = this.dom.incomeDateInput ? this.dom.incomeDateInput.value : '';

                if (name === '') {

                    this.context.app.showFail('Enter the income name');
                    return null;
                }

                if (value === '' || Number.isNaN(Number(value)) || Number(value) < 0) {

                    this.context.app.showFail('Enter a valid value');
                    return null;
                }

                if (date === '') {

                    this.context.app.showFail('Enter a valid date');
                    return null;
                }

                return { name: name, value: value, date: date };
            },

            saveIncome: function () {

                const payload = this.getIncomeFormPayload();

                if (!payload) {

                    return;
                }

                this.postManageAction({
                    action: this.editingIncomeId ? 'update_extra_income' : 'create_extra_income',
                    income_id: this.editingIncomeId ? String(this.editingIncomeId) : '',
                    name: payload.name,
                    value: payload.value,
                    date: payload.date
                }, (response) => {

                    this.resetIncomeForm();
                    this.hideModal(this.modals.addIncome);
                    this.context.app.showSuccess(response.message || (this.editingIncomeId ? 'Income updated' : 'Income created'));
                    this.context.app.reloadCurrentPage({
                        ...(this.context.params || {}),
                        tab: 'extra_income'
                    });
                });
            },

            deleteIncome: function (button) {

                const incomeId = Number.parseInt(button.dataset.id || '', 10);

                if (!Number.isFinite(incomeId) || incomeId <= 0) {

                    this.context.app.showFail('Invalid income');
                    return;
                }

                if (!window.confirm('Delete this income?')) {

                    return;
                }

                this.postManageAction({
                    action: 'delete_extra_income',
                    income_id: String(incomeId)
                }, (response) => {

                    this.context.app.showSuccess(response.message || 'Income deleted');
                    this.context.app.reloadCurrentPage({
                        ...(this.context.params || {}),
                        tab: 'extra_income'
                    });
                });
            },

            postManageAction: function (payload, onSuccess) {

                $.ajax({
                    url: '/my-bills/pages/history/extra_income/manage.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        csrf_token: window.APP.csrfToken || '',
                        ...payload
                    }
                })
                .done((response) => {

                    if (!response || response.ok !== true) {

                        this.context.app.showFail(response && response.error ? response.error : 'Action failed');
                        return;
                    }

                    onSuccess(response);
                })
                .fail((jqXHR) => {

                    this.context.app.showFail(this.readAjaxError(jqXHR, 'Action failed'));
                });
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

            goToPage: function (pageNumber) {

                this.context.app.reloadCurrentPage({
                    ...(this.context.params || {}),
                    tab: 'extra_income',
                    page_num: pageNumber
                });
            },

            exposeGlobals: function () {

                window.parseFilter = this.parseFilters.bind(this);
                window.btnFilter = this.applyFilters.bind(this);
                window.btnClear = this.clearFilters.bind(this);
            }
        };
    }

    return createPageController();
}());
