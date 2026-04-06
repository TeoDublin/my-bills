window.PageModules = window.PageModules || {};
window.LoadedPageScripts = window.LoadedPageScripts || {};
window.LoadedPageStyles = window.LoadedPageStyles || {};

function setCookie(name, value, days = 365) {

    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));

    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) +
        "; expires=" + date.toUTCString() +
        "; path=/";
}

function getCookie(name) {

    const cookies = document.cookie.split(';');

    for (let i = 0; i < cookies.length; i++) {

        const cookie = cookies[i].trim();

        if (cookie.startsWith(encodeURIComponent(name) + '=')) {

            return decodeURIComponent(cookie.substring(name.length + 1));
        }
    }

    return null;
}

window.crmApp = {

    state: {

        currentPage: null,
        currentParams: {},
        currentModule: null,
        persistedTheme: 'light',
        previewTheme: 'light',
        isThemePreviewActive: false
    },

    init: function () {

        this.state.persistedTheme = window.APP && window.APP.currentTheme ? window.APP.currentTheme : 'light';
        this.state.previewTheme = this.state.persistedTheme;
        this.cacheDom();
        this.bindShellEvents();
        this.modalStack.start();
        this.menu.start();
        this.startRouter();
    },

    cacheDom: function () {

        this.headerLeft = document.querySelector('[data-header-left]');
        this.menuVertical = document.getElementById('sidebarMenu');
        this.pageContent = document.querySelector('.page-content');
        this.toggleBtn = document.getElementById('menuToggle');
        this.icon = document.getElementById('menuIcon');
        this.menuLabels = document.querySelectorAll('.menu-label');
        this.exit = document.querySelector('.menu-exit');
        this.options = document.querySelectorAll('.menu-option');
        this.appContainer = document.getElementById('app');
        this.successToastElement = document.getElementById('successToast');
        this.failToastElement = document.getElementById('failToast');
        this.logo = document.querySelector('[data-app-logo]');
        this.preferencesModalElement = document.getElementById('appPreferencesModal');
        this.saveThemePreferencesButton = document.querySelector('[data-action="save-theme-preferences"]');
        this.themeOptions = document.querySelectorAll('[data-theme-option]');
    },

    bindShellEvents: function () {

        window.addEventListener('popstate', (event) => {

            const state = event.state || this.getRouteStateFromUrl();
            this.loadPage(state.pageId, {

                params: state.params || {},
                pushState: false
            });
        });

        if (this.toggleBtn) {

            this.toggleBtn.addEventListener('click', () => this.menu.toggle());
            this.toggleBtn.addEventListener('keydown', (event) => {

                if (event.key !== 'Enter' && event.key !== ' ') {

                    return;
                }

                event.preventDefault();
                this.menu.toggle();
            });
        }

        if (this.exit) {

            this.exit.addEventListener('click', () => this.menuExit());
        }

        if (this.preferencesModalElement) {

            this.preferencesModalElement.addEventListener('show.bs.modal', () => {

                this.state.persistedTheme = window.APP.currentTheme;
                this.state.previewTheme = window.APP.currentTheme;
                this.state.isThemePreviewActive = true;
                this.syncThemePreferencesForm();
            });

            this.preferencesModalElement.addEventListener('hidden.bs.modal', () => {

                if (this.state.isThemePreviewActive) {

                    this.renderTheme(this.state.persistedTheme);
                }

                this.state.previewTheme = this.state.persistedTheme;
                this.state.isThemePreviewActive = false;
                this.syncThemePreferencesForm();
            });
        }

        if (this.saveThemePreferencesButton) {

            this.saveThemePreferencesButton.addEventListener('click', () => {

                this.saveThemePreferences();
            });
        }

        this.themeOptions.forEach((option) => {

            option.addEventListener('change', () => {

                if (!option.checked) {

                    return;
                }

                this.previewTheme(option.value);
            });
        });

        this.options.forEach((option) => {

            option.addEventListener('click', () => {

                const pageId = option.dataset.menu;

                if (pageId) {

                    this.setMenuOptionState(option);
                    this.loadPage(pageId);
                }
            });
        });
    },

    setMenuOptionState: function (activeOption) {

        this.options.forEach((option) => {

            const isActive = option === activeOption;
            option.classList.toggle('active', isActive);
            option.classList.toggle('menu-active', isActive);
        });
    },

    startRouter: function () {

        const initialRoute = this.getRouteStateFromUrl();
        this.loadPage(initialRoute.pageId, {

            params: initialRoute.params,
            pushState: false,
            replaceState: true
        });
    },

    getRouteStateFromUrl: function () {

        const searchParams = new URLSearchParams(window.location.search);
        const pageId = searchParams.get('page') || window.APP.defaultPage;
        const params = {};

        searchParams.forEach((value, key) => {

            if (key === 'page') {

                return;
            }

            const normalizedKey = key.endsWith('[]') ? key.slice(0, -2) : key;

            if (Object.prototype.hasOwnProperty.call(params, normalizedKey)) {

                if (!Array.isArray(params[normalizedKey])) {

                    params[normalizedKey] = [params[normalizedKey]];
                }

                params[normalizedKey].push(value);
                return;
            }

            params[normalizedKey] = key.endsWith('[]') ? [value] : value;
        });

        return { pageId, params };
    },

    buildPageUrl: function (pageId, params) {

        const page = window.APP.pages[pageId];
        const url = new URL(page.endpoint, window.location.origin);

        Object.entries(params || {}).forEach(([key, value]) => {

            if (Array.isArray(value)) {

                value.forEach((item) => url.searchParams.append(`${key}[]`, item));
                return;
            }

            if (value !== null && value !== undefined && value !== '') {

                url.searchParams.set(key, value);
            }
        });

        return url.toString();
    },

    syncBrowserUrl: function (pageId, params, replaceState) {

        const url = new URL(window.APP.indexUrl, window.location.origin);
        url.searchParams.set('page', pageId);

        Object.entries(params || {}).forEach(([key, value]) => {

            if (Array.isArray(value)) {

                value.forEach((item) => url.searchParams.append(`${key}[]`, item));
                return;
            }

            if (value !== null && value !== undefined && value !== '') {

                url.searchParams.set(key, value);
            }
        });

        const historyMethod = replaceState ? 'replaceState' : 'pushState';
        window.history[historyMethod]({ pageId, params }, '', url.toString());
    },

    setLoading: function () {

        this.appContainer.innerHTML = '<div class="card card-body mt-3 text-center"><h5>Loading...</h5></div>';
    },

    setActiveMenu: function (pageId) {

        this.options.forEach((option) => {

            const isActive = option.dataset.menu === pageId;
            option.classList.toggle('active', isActive);
            option.classList.toggle('menu-active', isActive);
        });
    },

    loadPage: function (pageId, options = {}) {

        const page = window.APP.pages[pageId];

        if (!page) {

            this.showFail('Page not available');
            return;
        }

        const params = options.params || {};
        const browserParams = options.browserParams || params;
        const pushState = options.pushState !== false;
        const replaceState = options.replaceState === true;

        this.destroyCurrentPageModule();
        this.setLoading();

        $.get(this.buildPageUrl(pageId, params))
            .done((html) => {

                $('#app').html(html);

                this.state.currentPage = pageId;
                this.state.currentParams = browserParams;
                this.setActiveMenu(pageId);

                if (replaceState || pushState) {

                    this.syncBrowserUrl(pageId, browserParams, replaceState);
                }

                this.ensurePageAssets(pageId)
                    .done(() => {

                        this.runPageModule(pageId);
                    })
                    .fail(() => {

                        this.showFail('Unable to load page assets');
                    });
            })
            .fail((jqXHR) => {

                if (jqXHR && jqXHR.status === 401) {

                    window.location.href = window.APP.indexUrl;
                    return;
                }

                this.showFail('Unable to load page');
            });
    },

    ensurePageAssets: function (pageId) {

        const page = window.APP.pages[pageId];
        const deferreds = [];

        if (!page) {

            return $.Deferred().resolve().promise();
        }

        if (page.style && !window.LoadedPageStyles[page.style]) {

            deferreds.push(this.ensurePageStyle(page.style));
        }

        if (page.script && !window.LoadedPageScripts[page.script]) {

            deferreds.push($.getScript(page.script).done(() => {

                window.LoadedPageScripts[page.script] = true;
            }));
        }

        if (deferreds.length === 0) {

            return $.Deferred().resolve().promise();
        }

        return $.when.apply($, deferreds);
    },

    ensurePageStyle: function (href) {

        const existingTag = document.querySelector(`link[data-page-style="${href}"]`);

        if (existingTag) {

            window.LoadedPageStyles[href] = true;
            return $.Deferred().resolve().promise();
        }

        const deferred = $.Deferred();
        const link = document.createElement('link');

        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.pageStyle = href;
        link.onload = () => {

            window.LoadedPageStyles[href] = true;
            deferred.resolve();
        };
        link.onerror = () => deferred.reject();

        document.head.appendChild(link);

        return deferred.promise();
    },

    reloadCurrentPage: function (params = null, browserParams = null) {

        this.loadPage(this.state.currentPage || window.APP.defaultPage, {

            params: params || this.state.currentParams || {},
            browserParams: browserParams || params || this.state.currentParams || {},
            pushState: true
        });
    },

    runPageModule: function (pageId) {

        const pageModule = window.PageModules[pageId];

        if (pageModule && typeof pageModule.init === 'function') {

            this.state.currentModule = pageModule;
            pageModule.init({

                pageId: pageId,
                params: this.state.currentParams,
                app: this
            });
            return;
        }

        this.state.currentModule = null;
    },

    destroyCurrentPageModule: function () {

        const currentModule = this.state.currentModule;

        if (currentModule && typeof currentModule.destroy === 'function') {

            currentModule.destroy();
        }

        this.state.currentModule = null;
    },

    menuExit: function () {

        $.post('templates/basic.crm/logout.php')
            .done(() => {

                window.location.href = window.APP.indexUrl;
            })
            .fail(() => {

                this.showFail('Logout failed');
            });
    },

    changeTheme: function (theme) {

        if (!['light', 'dark'].includes(theme)) {

            return;
        }

        if (theme === this.state.persistedTheme) {

            this.renderTheme(theme);
            this.hidePreferencesModal();
            return;
        }

        $.ajax({

            url: 'templates/basic.crm/theme.php',
            method: 'POST',
            dataType: 'json',
            data: {

                theme: theme,
                csrf_token: window.APP.csrfToken || ''
            }
        })
        .done((response) => {

            if (!response || response.ok !== true) {

                this.showFail(response && response.error ? response.error : 'Theme change failed');
                return;
            }

            this.applyTheme(response.theme || theme);
            this.hidePreferencesModal();
            this.showSuccess('Theme updated');
        })
        .fail((jqXHR) => {

            const error = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error
                ? jqXHR.responseJSON.error
                : 'Theme change failed';

            this.showFail(error);
        });
    },

    saveThemePreferences: function () {

        const selectedThemeOption = this.preferencesModalElement
            ? this.preferencesModalElement.querySelector('[data-theme-option]:checked')
            : document.querySelector('[data-theme-option]:checked');
        const nextTheme = selectedThemeOption ? selectedThemeOption.value : '';
        this.changeTheme(nextTheme);
    },

    syncThemePreferencesForm: function () {

        this.themeOptions.forEach((option) => {

            option.checked = option.value === this.state.previewTheme;
        });
    },

    applyTheme: function (theme) {

        this.state.persistedTheme = theme;
        this.state.previewTheme = theme;
        this.state.isThemePreviewActive = false;
        window.APP.currentTheme = theme;
        setCookie('preferred_theme', theme, 365);
        this.renderTheme(theme);
        this.syncThemePreferencesForm();
    },

    previewTheme: function (theme) {

        if (!['light', 'dark'].includes(theme)) {

            return;
        }

        this.state.previewTheme = theme;
        this.renderTheme(theme);
    },

    renderTheme: function (theme) {

        document.body.setAttribute('data-bs-theme', theme);

        if (this.logo) {

            this.logo.src = theme === 'dark' ? window.APP.logoDarkUrl : window.APP.logoLightUrl;
        }
    },

    hidePreferencesModal: function () {

        if (!this.preferencesModalElement || !window.bootstrap || !window.bootstrap.Modal) {

            return;
        }

        window.bootstrap.Modal.getOrCreateInstance(this.preferencesModalElement).hide();
    },

    showSuccess: function (message) {

        if (message) {

            const body = this.successToastElement.querySelector('.toast-body');
            if (body) {

                body.textContent = message;
            }
        }

        if (window.bootstrap && this.successToastElement) {

            window.bootstrap.Toast.getOrCreateInstance(this.successToastElement).show();
        }
    },

    showFail: function (message) {

        if (message) {

            const body = this.failToastElement.querySelector('.toast-body');
            if (body) {

                body.textContent = message;
            }
        }

        if (window.bootstrap && this.failToastElement) {

            window.bootstrap.Toast.getOrCreateInstance(this.failToastElement).show();
        }
    },

    modalStack: {

        started: false,
        sequence: 0,
        baseZIndex: 2000,
        zIndexStep: 20,

        start: function () {

            if (this.started) {

                return;
            }

            document.addEventListener('show.bs.modal', (event) => this.handleShow(event));
            document.addEventListener('shown.bs.modal', () => this.syncBodyClass());
            document.addEventListener('hidden.bs.modal', (event) => this.handleHidden(event));
            this.started = true;
        },

        handleShow: function (event) {

            const modal = event.target;

            if (!(modal instanceof HTMLElement) || !modal.classList.contains('modal')) {

                return;
            }

            if (!modal.id) {

                modal.id = `app-modal-${++this.sequence}`;
            }

            modal.dataset.modalStackManaged = 'true';
            modal.dataset.modalStackSequence = String(++this.sequence);

            const modalZIndex = this.baseZIndex + (this.visibleModals().length + 1) * this.zIndexStep;
            modal.style.zIndex = String(modalZIndex);

            window.setTimeout(() => {

                const backdrop = this.latestUnmanagedBackdrop();

                if (!backdrop) {

                    return;
                }

                backdrop.dataset.stackManaged = 'true';
                backdrop.dataset.stackOwner = modal.id;
                backdrop.style.zIndex = String(modalZIndex - 10);
                this.syncBodyClass();
            }, 0);
        },

        handleHidden: function (event) {

            const modal = event.target;

            if (!(modal instanceof HTMLElement) || !modal.classList.contains('modal')) {

                return;
            }

            modal.style.removeProperty('z-index');
            delete modal.dataset.modalStackManaged;
            delete modal.dataset.modalStackSequence;

            window.setTimeout(() => {

                this.restackVisibleModals();
                this.syncBodyClass();
            }, 0);
        },

        visibleModals: function () {

            return Array.from(document.querySelectorAll('.modal.show'))
                .filter((modal) => modal instanceof HTMLElement)
                .sort((left, right) => {

                    const leftSequence = Number(left.dataset.modalStackSequence || '0');
                    const rightSequence = Number(right.dataset.modalStackSequence || '0');
                    return leftSequence - rightSequence;
                });
        },

        restackVisibleModals: function () {

            this.visibleModals().forEach((modal, index) => {

                const modalZIndex = this.baseZIndex + (index + 1) * this.zIndexStep;
                modal.style.zIndex = String(modalZIndex);

                const backdrop = this.backdropForModal(modal.id);
                if (backdrop) {

                    backdrop.style.zIndex = String(modalZIndex - 10);
                }
            });
        },

        latestUnmanagedBackdrop: function () {

            const backdrops = Array.from(document.querySelectorAll('.modal-backdrop'))
                .filter((backdrop) => !backdrop.dataset.stackManaged);

            return backdrops.length > 0 ? backdrops[backdrops.length - 1] : null;
        },

        backdropForModal: function (modalId) {

            return Array.from(document.querySelectorAll('.modal-backdrop[data-stack-owner]'))
                .find((backdrop) => backdrop.dataset.stackOwner === modalId) || null;
        },

        syncBodyClass: function () {

            if (document.querySelector('.modal.show')) {

                document.body.classList.add('modal-open');
                return;
            }

            document.body.classList.remove('modal-open');
        }
    },

    menu: {

        start: function () {

            if (getCookie(window.APP.menuCookieName) === 'show') {

                window.crmApp.menu.show();
                return;
            }

            window.crmApp.menu.hide();
        },

        show: function () {

            window.crmApp.pageContent.classList.remove('menu-hidden');
            window.crmApp.headerLeft.classList.add('open');
            window.crmApp.menuVertical.classList.add('open');
            window.crmApp.menuLabels.forEach((label) => label.classList.remove('hide'));
            window.crmApp.icon.innerHTML = window.APP.menuIconOpen;
            setCookie(window.APP.menuCookieName, 'show', 365);
        },

        hide: function () {

            window.crmApp.pageContent.classList.add('menu-hidden');
            window.crmApp.headerLeft.classList.remove('open');
            window.crmApp.menuVertical.classList.remove('open');
            window.crmApp.menuLabels.forEach((label) => label.classList.add('hide'));
            window.crmApp.icon.innerHTML = window.APP.menuIconClosed;
            setCookie(window.APP.menuCookieName, 'hide', 365);
        },

        toggle: function () {

            if (window.crmApp.menuVertical.classList.contains('open')) {

                window.crmApp.menu.hide();
                return;
            }

            window.crmApp.menu.show();
        }
    }
};

window.fail = function (message) {

    window.crmApp.showFail(message);
};

window.success = function (message) {

    window.crmApp.showSuccess(message);
};

document.addEventListener('DOMContentLoaded', function () {

    window.crmApp.init();
});
