(function (window, document) {

    if (!window || !document) {

        return;
    }

    const SELECTOR = 'select[multiple]:not([data-native-multiple])';

    function summaryLabel(select) {

        const selectedOptions = Array.from(select.selectedOptions).map((option) => option.textContent.trim());
        const baseLabel = select.dataset.searchPlaceholder || select.getAttribute('aria-label') || 'values';

        if (selectedOptions.length === 0) {

            return `Select ${baseLabel}`;
        }

        if (selectedOptions.length <= 2) {

            return selectedOptions.join(', ');
        }

        return `${selectedOptions.length} selected`;
    }

    function syncSelect(select, summary, list) {

        summary.textContent = summaryLabel(select);

        list.querySelectorAll('.crm-multi-picker-option').forEach((item, index) => {

            const checkbox = item.querySelector('input');
            const isSelected = Boolean(select.options[index]?.selected);

            if (checkbox) {

                checkbox.checked = isSelected;
            }

            item.classList.toggle('selected', isSelected);
        });
    }

    function closeAll() {

        document.querySelectorAll('.crm-multi-picker.open').forEach((picker) => {

            picker.classList.remove('open');

            const toggle = picker.querySelector('.crm-multi-picker-toggle');
            if (toggle) {

                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function buildPicker(select) {

        if (!(select instanceof HTMLSelectElement) || select.dataset.crmMultiReady === 'true') {

            return;
        }

        select.dataset.crmMultiReady = 'true';
        select.classList.add('crm-multi-select-native');

        const wrapper = document.createElement('div');
        wrapper.className = 'crm-multi-picker';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'crm-multi-picker-toggle';
        toggle.setAttribute('aria-expanded', 'false');

        const summary = document.createElement('span');
        summary.className = 'crm-multi-picker-summary';

        const caret = document.createElement('span');
        caret.className = 'crm-multi-picker-caret';
        caret.innerHTML = '&#9662;';

        toggle.appendChild(summary);
        toggle.appendChild(caret);

        const panel = document.createElement('div');
        panel.className = 'crm-multi-picker-panel';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'crm-multi-picker-search-wrap';

        const search = document.createElement('input');
        search.type = 'text';
        search.className = 'crm-multi-picker-search';
        search.placeholder = `Search in ${select.dataset.searchPlaceholder || 'values'}`;

        searchWrap.appendChild(search);

        const actions = document.createElement('div');
        actions.className = 'crm-multi-picker-actions';

        const selectAllButton = document.createElement('button');
        selectAllButton.type = 'button';
        selectAllButton.className = 'crm-multi-picker-action';
        selectAllButton.textContent = 'Select all';

        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'crm-multi-picker-action';
        clearButton.textContent = 'Clear';

        actions.appendChild(selectAllButton);
        actions.appendChild(clearButton);

        const list = document.createElement('div');
        list.className = 'crm-multi-picker-options';

        Array.from(select.options).forEach((option) => {

            const optionLabel = document.createElement('label');
            optionLabel.className = 'crm-multi-picker-option';
            optionLabel.dataset.optionText = option.textContent.trim().toLowerCase();

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = option.selected;

            const text = document.createElement('span');
            text.className = 'crm-multi-picker-option-text';
            text.textContent = option.textContent;

            optionLabel.appendChild(checkbox);
            optionLabel.appendChild(text);
            list.appendChild(optionLabel);

            checkbox.addEventListener('change', function () {

                option.selected = checkbox.checked;
                syncSelect(select, summary, list);
            });
        });

        panel.appendChild(searchWrap);
        panel.appendChild(actions);
        panel.appendChild(list);
        wrapper.appendChild(toggle);
        wrapper.appendChild(panel);
        select.insertAdjacentElement('afterend', wrapper);

        toggle.addEventListener('click', function () {

            const isOpen = wrapper.classList.contains('open');
            closeAll();
            wrapper.classList.toggle('open', !isOpen);
            toggle.setAttribute('aria-expanded', String(!isOpen));

            if (!isOpen) {

                search.focus();
            }
        });

        search.addEventListener('input', function () {

            const query = search.value.trim().toLowerCase();

            list.querySelectorAll('.crm-multi-picker-option').forEach((item) => {

                const matches = query === '' || item.dataset.optionText.includes(query);
                item.classList.toggle('d-none', !matches);
            });
        });

        selectAllButton.addEventListener('click', function () {

            list.querySelectorAll('.crm-multi-picker-option').forEach((item, index) => {

                if (item.classList.contains('d-none')) {

                    return;
                }

                const checkbox = item.querySelector('input');
                if (checkbox) {

                    checkbox.checked = true;
                }

                if (select.options[index]) {

                    select.options[index].selected = true;
                }
            });

            syncSelect(select, summary, list);
        });

        clearButton.addEventListener('click', function () {

            list.querySelectorAll('.crm-multi-picker-option').forEach((item, index) => {

                const checkbox = item.querySelector('input');
                if (checkbox) {

                    checkbox.checked = false;
                }

                if (select.options[index]) {

                    select.options[index].selected = false;
                }
            });

            syncSelect(select, summary, list);
        });

        syncSelect(select, summary, list);
    }

    if (!document.documentElement.dataset.crmMultiSelectBound) {

        document.documentElement.dataset.crmMultiSelectBound = 'true';

        document.addEventListener('click', function (event) {

            if (event.target.closest('.crm-multi-picker')) {

                return;
            }

            closeAll();
        });

        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape') {

                closeAll();
            }
        });
    }

    window.CrmMultiSelect = {

        init: function (root) {

            const container = root || document;

            container.querySelectorAll(SELECTOR).forEach((select) => {

                buildPicker(select);
            });
        },
        closeAll: closeAll
    };
}(window, document));
