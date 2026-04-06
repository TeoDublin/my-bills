window.PageModules = window.PageModules || {};

window.PageModules['montly'] = (function () {

    const TAB_SCRIPTS = {
        bills: '/my-bills/pages/montly/bills/bills.js',
        income: '/my-bills/pages/montly/income/income.js'
    };

    function createPageController() {

        return {

            context: null,
            dom: {},
            listeners: [],
            activeTabModule: null,

            init: function (context) {

                this.context = context;
                this.listeners = [];
                this.activeTabModule = null;
                this.cacheDom();
                this.bindEvents();
                this.loadActiveTabModule();
            },

            destroy: function () {

                this.listeners.forEach(({ element, eventName, handler }) => {

                    element.removeEventListener(eventName, handler);
                });

                if (this.activeTabModule && typeof this.activeTabModule.destroy === 'function') {

                    this.activeTabModule.destroy();
                }

                this.context = null;
                this.dom = {};
                this.listeners = [];
                this.activeTabModule = null;
            },

            cacheDom: function () {

                const root = document.querySelector('[data-montly-tabs]');

                this.dom = {
                    root: root,
                    tabButtons: Array.from(document.querySelectorAll('[data-tab]'))
                };
            },

            bindEvents: function () {

                this.dom.tabButtons.forEach((button) => {

                    this.bindListener(button, 'click', () => {

                        const tab = button.dataset.tab || 'bills';
                        this.openTab(tab);
                    });
                });
            },

            bindListener: function (element, eventName, handler) {

                if (!element) {

                    return;
                }

                element.addEventListener(eventName, handler);
                this.listeners.push({ element, eventName, handler });
            },

            openTab: function (tab) {

                const currentTab = this.dom.root ? (this.dom.root.dataset.activeTab || 'bills') : 'bills';

                if (tab === currentTab) {

                    return;
                }

                this.context.app.reloadCurrentPage({
                    tab: tab
                });
            },

            loadActiveTabModule: function () {

                const tab = this.dom.root ? (this.dom.root.dataset.activeTab || 'bills') : 'bills';
                const scriptUrl = TAB_SCRIPTS[tab];

                if (!scriptUrl) {

                    this.context.app.showFail('Tab not available');
                    return;
                }

                $.getScript(scriptUrl)
                    .done(() => {

                        const module = tab === 'bills' ? window.MontlyBillsTabModule : window.MontlyIncomeTabModule;

                        if (!module || typeof module.init !== 'function') {

                            this.context.app.showFail('Unable to load tab module');
                            return;
                        }

                        this.activeTabModule = module;
                        module.init({
                            ...this.context,
                            tab: tab
                        });
                    })
                    .fail(() => {

                        this.context.app.showFail('Unable to load tab assets');
                    });
            }
        };
    }

    return createPageController();
}());
