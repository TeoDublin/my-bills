window.MontlyBillsTabModule = (function () {

    const MODAL_IDS = [
        'montly_page_setup_modal',
        'montly_bill_modal',
        'montly_group_manage_modal'
    ];

    function createPageController() {

        return {

            context: null,
            dom: {},
            modals: {},
            listeners: [],
            hoistedModals: [],
            reopenBillModalAfterGroupManage: false,
            editingMontlyBillId: null,

            init: function (context) {

                this.context = context;
                this.listeners = [];
                this.hoistedModals = [];
                this.reopenBillModalAfterGroupManage = false;
                this.editingMontlyBillId = null;

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
                this.reopenBillModalAfterGroupManage = false;
                this.editingMontlyBillId = null;
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

                const actionsRoot = document.querySelector('[data-montly-actions]');

                this.dom = {
                    floatingMenu: document.querySelector('.floating-menu'),
                    floatingMenuButton: document.querySelector('.floating-menu-btn'),
                    appliedFiltersRoot: document.querySelector('.filter-labels'),
                    clearFiltersButton: document.querySelector('[data-action="clear-filters"]'),
                    applyFiltersButton: document.querySelector('[data-action="apply-filters"]'),
                    saveSetupButton: document.querySelector('[data-action="save-setup"]'),
                    openBillCreateButton: document.querySelector('[data-action="open-montly-create"]'),
                    saveBillButton: document.querySelector('[data-action="save-montly-bill"]'),
                    openGroupManagerButton: document.querySelector('[data-action="open-group-manager"]'),
                    createGroupButton: document.querySelector('[data-action="create-group"]'),
                    renameGroupButton: document.querySelector('[data-action="rename-group"]'),
                    deleteGroupButton: document.querySelector('[data-action="delete-group"]'),
                    runPageActionButton: document.querySelector('[data-action="run-page-action"]'),
                    actionSelect: actionsRoot ? actionsRoot.querySelector('select[name="actions"]') : null,
                    actionsRoot: actionsRoot,
                    actionScopeSelect: document.getElementById('montly_action_scope'),
                    rowsPerPageInput: document.getElementById('rows_per_page'),
                    tableFontSizeInput: document.getElementById('table_font_size'),
                    billGroupSelect: document.getElementById('montly_bill_group_id'),
                    billNameInput: document.getElementById('montly_bill_name'),
                    billValueInput: document.getElementById('montly_bill_value'),
                    billDayInput: document.getElementById('montly_bill_day'),
                    billFirstDateInput: document.getElementById('montly_bill_first_date'),
                    billLastDateInput: document.getElementById('montly_bill_last_date'),
                    billModalTitle: document.querySelector('[data-montly-modal-title]'),
                    billModalSubmit: document.querySelector('[data-montly-modal-submit]'),
                    newGroupNameInput: document.getElementById('montly_new_group_name'),
                    manageGroupSelect: document.getElementById('montly_manage_group_id'),
                    manageGroupNameInput: document.getElementById('montly_manage_group_name')
                };
            },

            cacheModals: function () {

                this.modals = {
                    setup: this.getModalInstance('montly_page_setup_modal'),
                    bill: this.getModalInstance('montly_bill_modal'),
                    groupManage: this.getModalInstance('montly_group_manage_modal')
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
                this.bindClick(this.dom.openBillCreateButton, () => this.openBillModalForCreate());
                this.bindClick(this.dom.saveBillButton, () => this.saveMontlyBill());
                this.bindClick(this.dom.openGroupManagerButton, () => this.openGroupManager());
                this.bindClick(this.dom.createGroupButton, () => this.createGroup());
                this.bindClick(this.dom.renameGroupButton, () => this.renameGroup());
                this.bindClick(this.dom.deleteGroupButton, () => this.deleteGroup());
                this.bindClick(this.dom.runPageActionButton, () => this.runPageAction());
                this.bindListener(this.dom.manageGroupSelect, 'change', () => this.syncManageGroupName());
                this.bindListener(document.getElementById('montly_group_manage_modal'), 'hidden.bs.modal', () => {

                    if (!this.reopenBillModalAfterGroupManage) {

                        return;
                    }

                    this.reopenBillModalAfterGroupManage = false;
                    this.showModal(this.modals.bill);
                });
                this.bindListener(document.getElementById('montly_bill_modal'), 'hidden.bs.modal', () => this.resetMontlyBillForm());

                this.bindPagination();
                this.bindTableSelection();
                this.syncManageGroupName();
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

                    Array.from(table.querySelectorAll('tbody tr[data-id]')).forEach((row) => {

                        this.bindClick(row, (event) => {

                            if (event.target.closest('input, label, button, a, [data-table-row-select-cell]')) {

                                return;
                            }

                            this.openBillModalForEdit(row);
                        });
                    });

                    Array.from(table.querySelectorAll('[data-action="delete-montly-bill"]')).forEach((button) => {

                        this.bindClick(button, (event) => {

                            event.preventDefault();
                            event.stopPropagation();
                            this.deleteMontlyBill(button);
                        });
                    });

                    syncSelectAllState();
                });
            },

            runPageAction: function () {

                const action = this.dom.actionSelect ? this.dom.actionSelect.value : '';

                if (action === 'delete') {

                    this.runDeleteAction();
                    return;
                }

                this.context.app.showFail('Unsupported action');
            },

            runDeleteAction: function () {

                const scope = this.dom.actionScopeSelect ? this.dom.actionScopeSelect.value : 'filter';
                const selectedIds = scope === 'selected' ? this.getSelectedRowIds() : [];
                let message = 'Delete monthly bills using the current filter?';

                if (scope === 'selected') {

                    if (selectedIds.length === 0) {

                        this.context.app.showFail('Select at least one row');
                        return;
                    }

                    message = selectedIds.length === 1
                        ? 'Delete the selected monthly bill?'
                        : `Delete ${selectedIds.length} selected monthly bills?`;
                }

                if (!window.confirm(message)) {

                    return;
                }

                this.postMontlyManageAction({
                    action: 'bulk_delete_montly_bills',
                    scope: scope,
                    filters: JSON.stringify(this.context.params || {}),
                    selected_ids: JSON.stringify(selectedIds)
                }, (response) => {

                    this.context.app.showSuccess(response.message || 'Monthly bills deleted');
                    this.context.app.reloadCurrentPage(this.context.params || {});
                });
            },

            openGroupManager: function () {

                const billModalElement = document.getElementById('montly_bill_modal');
                const billIsOpen = billModalElement ? billModalElement.classList.contains('show') : false;

                this.reopenBillModalAfterGroupManage = billIsOpen;

                if (billIsOpen) {

                    this.hideModal(this.modals.bill);
                }

                this.syncManageGroupName();
                this.showModal(this.modals.groupManage);
            },

            saveMontlyBill: function () {

                const payload = this.getMontlyBillFormPayload();

                if (!payload) {

                    return;
                }

                const action = this.editingMontlyBillId ? 'update_montly_bill' : 'create_montly_bill';

                this.postMontlyManageAction({
                    action: action,
                    id: this.editingMontlyBillId ? String(this.editingMontlyBillId) : '',
                    group_id: payload.groupId,
                    name: payload.name,
                    value: payload.value,
                    day: payload.day,
                    first_date: payload.firstDate,
                    last_date: payload.lastDate
                }, (response) => {

                    this.applyGroupOptions(response.groups || [], payload.groupId);
                    this.resetMontlyBillForm();
                    this.hideModal(this.modals.bill);
                    this.context.app.showSuccess(response.message || (this.editingMontlyBillId ? 'Monthly bill updated' : 'Monthly bill created'));
                    this.context.app.reloadCurrentPage(this.context.params || {});
                });
            },

            getMontlyBillFormPayload: function () {

                const groupId = this.dom.billGroupSelect ? this.dom.billGroupSelect.value : '';
                const name = this.dom.billNameInput ? this.dom.billNameInput.value.trim() : '';
                const value = this.dom.billValueInput ? this.dom.billValueInput.value.trim() : '';
                const day = this.dom.billDayInput ? this.dom.billDayInput.value.trim() : '';
                const firstDate = this.dom.billFirstDateInput ? this.dom.billFirstDateInput.value : '';
                const lastDate = this.dom.billLastDateInput ? this.dom.billLastDateInput.value : '';

                if (groupId === '') {

                    this.context.app.showFail('Select a group');
                    return null;
                }

                if (name === '') {

                    this.context.app.showFail('Enter the monthly bill name');
                    return null;
                }

                if (value === '' || Number.isNaN(Number(value)) || Number(value) < 0) {

                    this.context.app.showFail('Enter a valid value');
                    return null;
                }

                const dayNumber = Number.parseInt(day, 10);

                if (!Number.isInteger(dayNumber) || dayNumber < 1 || dayNumber > 31) {

                    this.context.app.showFail('Day must be between 1 and 31');
                    return null;
                }

                if (firstDate !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(firstDate)) {

                    this.context.app.showFail('Enter a valid first date');
                    return null;
                }

                if (lastDate !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(lastDate)) {

                    this.context.app.showFail('Enter a valid last date');
                    return null;
                }

                return {
                    groupId: groupId,
                    name: name,
                    value: value,
                    day: String(dayNumber),
                    firstDate: firstDate,
                    lastDate: lastDate
                };
            },

            createGroup: function () {

                const name = this.dom.newGroupNameInput ? this.dom.newGroupNameInput.value.trim() : '';

                if (name === '') {

                    this.context.app.showFail('Enter the group name');
                    return;
                }

                this.postMontlyManageAction({
                    action: 'create_group',
                    name: name
                }, (response) => {

                    const selectedGroupId = response.group_id ? String(response.group_id) : '';
                    this.applyGroupOptions(response.groups || [], selectedGroupId);

                    if (this.dom.newGroupNameInput) {

                        this.dom.newGroupNameInput.value = '';
                    }

                    this.syncManageGroupName();
                    this.context.app.showSuccess(response.message || 'Group created');
                });
            },

            renameGroup: function () {

                const groupId = this.dom.manageGroupSelect ? this.dom.manageGroupSelect.value : '';
                const name = this.dom.manageGroupNameInput ? this.dom.manageGroupNameInput.value.trim() : '';

                if (groupId === '') {

                    this.context.app.showFail('Select a group');
                    return;
                }

                if (name === '') {

                    this.context.app.showFail('Enter the new group name');
                    return;
                }

                this.postMontlyManageAction({
                    action: 'rename_group',
                    group_id: groupId,
                    name: name
                }, (response) => {

                    this.applyGroupOptions(response.groups || [], groupId);
                    this.syncManageGroupName();
                    this.context.app.showSuccess(response.message || 'Group updated');
                });
            },

            deleteGroup: function () {

                const groupId = this.dom.manageGroupSelect ? this.dom.manageGroupSelect.value : '';
                const selectedOption = this.dom.manageGroupSelect && this.dom.manageGroupSelect.selectedOptions
                    ? this.dom.manageGroupSelect.selectedOptions[0]
                    : null;
                const groupName = selectedOption ? selectedOption.textContent.trim() : '';

                if (groupId === '') {

                    this.context.app.showFail('Select a group');
                    return;
                }

                if (!window.confirm(`Delete the group "${groupName}"? This will only run if no monthly bills are linked to it.`)) {

                    return;
                }

                this.postMontlyManageAction({
                    action: 'delete_group',
                    group_id: groupId
                }, (response) => {

                    this.applyGroupOptions(response.groups || [], response.selected_group_id ? String(response.selected_group_id) : '');
                    this.syncManageGroupName();
                    this.context.app.showSuccess(response.message || 'Group deleted');
                });
            },

            openBillModalForCreate: function () {

                this.editingMontlyBillId = null;

                if (this.dom.billModalTitle) {

                    this.dom.billModalTitle.textContent = 'New monthly bill';
                }

                if (this.dom.billModalSubmit) {

                    this.dom.billModalSubmit.textContent = 'Save monthly bill';
                }

                this.showModal(this.modals.bill);
            },

            openBillModalForEdit: function (row) {

                if (!row) {

                    return;
                }

                this.editingMontlyBillId = Number.parseInt(row.dataset.id || '', 10);

                if (this.dom.billGroupSelect) {

                    this.dom.billGroupSelect.value = row.dataset.groupId || '';
                }

                if (this.dom.billNameInput) {

                    this.dom.billNameInput.value = row.dataset.name || '';
                }

                if (this.dom.billValueInput) {

                    this.dom.billValueInput.value = row.dataset.value || '';
                }

                if (this.dom.billDayInput) {

                    this.dom.billDayInput.value = row.dataset.day || '';
                }

                if (this.dom.billFirstDateInput) {

                    this.dom.billFirstDateInput.value = row.dataset.firstDate || '';
                }

                if (this.dom.billLastDateInput) {

                    this.dom.billLastDateInput.value = row.dataset.lastDate || '';
                }

                if (this.dom.billModalTitle) {

                    this.dom.billModalTitle.textContent = `Edit monthly bill #${row.dataset.id || ''}`;
                }

                if (this.dom.billModalSubmit) {

                    this.dom.billModalSubmit.textContent = 'Save changes';
                }

                this.showModal(this.modals.bill);
            },

            deleteMontlyBill: function (button) {

                const row = button ? button.closest('tr[data-id]') : null;
                const id = row ? row.dataset.id || '' : '';
                const name = row ? (row.dataset.name || '').trim() : '';

                if (id === '') {

                    this.context.app.showFail('Invalid monthly bill');
                    return;
                }

                if (!window.confirm(`Delete the monthly bill "${name || ('#' + id)}"?`)) {

                    return;
                }

                this.postMontlyManageAction({
                    action: 'delete_montly_bill',
                    id: id
                }, (response) => {

                    this.context.app.showSuccess(response.message || 'Monthly bill deleted');
                    this.context.app.reloadCurrentPage(this.context.params || {});
                });
            },

            postMontlyManageAction: function (payload, onSuccess) {

                const csrfToken = this.dom.actionsRoot ? (this.dom.actionsRoot.dataset.csrfToken || '') : '';

                $.ajax({

                    url: '/my-bills/pages/montly/bills/manage.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        csrf_token: csrfToken,
                        ...payload
                    }
                })
                .done((response) => {

                    if (!response || response.ok !== true) {

                        this.context.app.showFail((response && response.error) || 'Operation failed');
                        return;
                    }

                    if (typeof onSuccess === 'function') {

                        onSuccess(response);
                    }
                })
                .fail((jqXHR) => {

                    this.context.app.showFail(this.readAjaxError(jqXHR, 'Operation failed'));
                });
            },

            applyGroupOptions: function (groups, selectedGroupId = '') {

                if (!Array.isArray(groups) || groups.length === 0) {

                    return;
                }

                const normalizedSelectedGroupId = selectedGroupId !== '' ? String(selectedGroupId) : '';
                const selectedFilterGroups = ($('#group').val() || []).map((value) => String(value));

                document.querySelectorAll('[data-montly-group-select]').forEach((select) => {

                    const currentValue = select.value ? String(select.value) : '';
                    const nextValue = normalizedSelectedGroupId || currentValue;

                    select.innerHTML = groups.map((group) => {

                        const id = String(group.id || '');
                        const name = this.escapeHtml(group.name || '');
                        const isSelected = id !== '' && id === nextValue ? ' selected' : '';

                        return `<option value="${id}"${isSelected}>${name}</option>`;
                    }).join('');
                });

                const groupFilterSelect = document.getElementById('group');

                if (groupFilterSelect) {

                    groupFilterSelect.innerHTML = groups.map((group) => {

                        const id = String(group.id || '');
                        const name = this.escapeHtml(group.name || '');
                        const isSelected = selectedFilterGroups.includes(id) ? ' selected' : '';

                        return `<option value="${id}"${isSelected}>${name}</option>`;
                    }).join('');

                    this.refreshMultiSelect(groupFilterSelect);
                }
            },

            syncManageGroupName: function () {

                if (!this.dom.manageGroupSelect || !this.dom.manageGroupNameInput) {

                    return;
                }

                const selectedOption = this.dom.manageGroupSelect.selectedOptions[0];
                this.dom.manageGroupNameInput.value = selectedOption ? selectedOption.textContent.trim() : '';
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
                window.btnFilter = this.applyFilters.bind(this);
                window.btnClear = this.clearFilters.bind(this);
            },

            parseFilters: function () {

                const params = {};
                const errors = [];
                const dayFrom = document.querySelector('#day_from')?.value || '';
                const dayTo = document.querySelector('#day_to')?.value || '';
                const includeAllDays = document.querySelector('#day_all')?.checked || false;

                if (dayFrom !== '' && dayTo !== '' && Number(dayTo) < Number(dayFrom)) {

                    errors.push('End day cannot be earlier than start day');
                }

                if (includeAllDays) {

                    params.day_all = '1';
                } else {

                    if (dayFrom !== '') {

                        params.day_from = dayFrom;
                    }

                    if (dayTo !== '') {

                        params.day_to = dayTo;
                    }
                }

                this.appendMultiValue(params, 'group', $('#group').val() || []);
                this.appendMultiValue(params, 'name', $('#name').val() || []);

                const displayParams = this.parseDisplayPreferences();

                if (!displayParams) {

                    return null;
                }

                if (errors.length > 0) {

                    alert(errors.join('\n'));
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

                    nextParams.day_all = '1';
                }

                return nextParams;
            },

            hasActiveFilterParams: function (params) {

                return ['group', 'name', 'day_all', 'day_from', 'day_to']
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

                if (filterKey === 'day') {

                    requestParams.day_from = '__clear__';
                    requestParams.day_to = '__clear__';
                    delete requestParams.day_all;
                    delete browserParams.day_from;
                    delete browserParams.day_to;
                    delete browserParams.day_all;
                } else {

                    requestParams[filterKey] = '__clear__';
                    delete browserParams[filterKey];
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

                this.validateIntegerRange(rowsPerPage, 5, 200, 'Rows must be between 5 and 200', errors);
                this.validateIntegerRange(tableFontSize, 10, 40, 'Text size must be between 10 and 40', errors);

                if (errors.length > 0) {

                    alert(errors.join('\n'));
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

            resetMontlyBillForm: function () {

                this.editingMontlyBillId = null;

                if (this.dom.billNameInput) {

                    this.dom.billNameInput.value = '';
                }

                if (this.dom.billValueInput) {

                    this.dom.billValueInput.value = this.dom.billValueInput.dataset.defaultValue || '';
                }

                if (this.dom.billDayInput) {

                    this.dom.billDayInput.value = this.dom.billDayInput.dataset.defaultValue || '';
                }

                if (this.dom.billFirstDateInput) {

                    this.dom.billFirstDateInput.value = this.dom.billFirstDateInput.dataset.defaultValue || '';
                }

                if (this.dom.billLastDateInput) {

                    this.dom.billLastDateInput.value = this.dom.billLastDateInput.dataset.defaultValue || '';
                }

                if (this.dom.billGroupSelect) {

                    this.dom.billGroupSelect.value = this.dom.billGroupSelect.dataset.defaultValue || this.dom.billGroupSelect.value;
                }

                if (this.dom.billModalTitle) {

                    this.dom.billModalTitle.textContent = 'New monthly bill';
                }

                if (this.dom.billModalSubmit) {

                    this.dom.billModalSubmit.textContent = 'Save monthly bill';
                }
            },

            escapeHtml: function (value) {

                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            },

            refreshMultiSelect: function (select) {

                if (!(select instanceof HTMLSelectElement)) {

                    return;
                }

                const picker = select.nextElementSibling;

                if (picker && picker.classList.contains('crm-multi-picker')) {

                    picker.remove();
                }

                delete select.dataset.crmMultiReady;

                if (window.CrmMultiSelect && typeof window.CrmMultiSelect.init === 'function') {

                    window.CrmMultiSelect.init(select.parentNode || document);
                }
            }
        };
    }

    return createPageController();
}());
