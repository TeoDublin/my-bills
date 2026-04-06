window.MontlyIncomeTabModule = (function () {

    return {

        context: null,
        listeners: [],
        dom: {},

        init: function (context) {

            this.context = context;
            this.listeners = [];
            this.cacheDom();
            this.bindEvents();
        },

        destroy: function () {

            this.listeners.forEach(({ element, eventName, handler }) => {

                element.removeEventListener(eventName, handler);
            });

            this.context = null;
            this.listeners = [];
            this.dom = {};
        },

        cacheDom: function () {

            const root = document.querySelector('[data-income-root]');

            this.dom = {
                root: root,
                saveButton: document.querySelector('[data-action="save-income"]'),
                valueInput: document.getElementById('income_value'),
                dayInput: document.getElementById('income_day')
            };
        },

        bindEvents: function () {

            this.bindListener(this.dom.saveButton, 'click', () => this.saveIncome());
        },

        bindListener: function (element, eventName, handler) {

            if (!element) {

                return;
            }

            element.addEventListener(eventName, handler);
            this.listeners.push({ element, eventName, handler });
        },

        saveIncome: function () {

            const value = this.dom.valueInput ? this.dom.valueInput.value.trim() : '';
            const day = this.dom.dayInput ? this.dom.dayInput.value.trim() : '';

            if (value === '' || Number.isNaN(Number(value)) || Number(value) < 0) {

                this.context.app.showFail('Enter a valid income value');
                return;
            }

            const dayNumber = Number.parseInt(day, 10);

            if (!Number.isInteger(dayNumber) || dayNumber < 1 || dayNumber > 31) {

                this.context.app.showFail('Enter a valid income day');
                return;
            }

            $.ajax({
                url: '/my-bills/pages/montly/income/manage.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    csrf_token: this.dom.root ? (this.dom.root.dataset.csrfToken || '') : '',
                    action: 'create_income',
                    value: value,
                    day: String(dayNumber)
                }
            })
            .done((response) => {

                if (!response || response.ok !== true) {

                    this.context.app.showFail((response && response.error) || 'Unable to save income');
                    return;
                }

                this.context.app.showSuccess(response.message || 'Income saved');
                this.context.app.reloadCurrentPage({
                    ...(this.context.params || {}),
                    tab: 'income'
                });
            })
            .fail((jqXHR) => {

                const response = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null;
                this.context.app.showFail((response && response.error) || 'Unable to save income');
            });
        }
    };
}());
