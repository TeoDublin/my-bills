(function (window, $) {

    if (!$ || !$.fn || !$.fn.selectpicker) {

        return;
    }

    function getSearchLabel($select) {

        return $select.data('searchPlaceholder')
            || $select.attr('title')
            || $select.attr('aria-label')
            || 'valori';
    }

    function selectedCountLabel($select) {

        const selectedCount = $select.find('option:selected').length;
        const label = String(getSearchLabel($select)).trim();

        if (selectedCount === 0) {

            return `Seleziona ${label}`;
        }

        if (selectedCount === 1) {

            return `1 selezionato`;
        }

        return `${selectedCount} selezionati`;
    }

    function ensureClearButton($wrapper, $input) {

        const $searchBox = $wrapper.find('.bs-searchbox');

        if ($searchBox.length === 0 || $searchBox.find('[data-selectpicker-search-clear]').length > 0) {

            return;
        }

        const $button = $('<button type="button" class="selectpicker-search-clear" data-selectpicker-search-clear aria-label="Pulisci ricerca">Pulisci</button>');

        $button.on('click', function (event) {

            event.preventDefault();
            event.stopPropagation();
            $input.val('').trigger('input').trigger('focus');
        });

        $searchBox.append($button);
    }

    function ensureSearchPlaceholder($select, $input) {

        if ($input.attr('placeholder')) {

            return;
        }

        $input.attr('placeholder', `Cerca in ${String(getSearchLabel($select)).trim()}`);
    }

    function syncToggleState($select) {

        const picker = $select.data('selectpicker');

        if (!picker || !picker.$button) {

            return;
        }

        const hasValue = $select.find('option:selected').length > 0;
        picker.$newElement.toggleClass('has-selection', hasValue);
        picker.$button.attr('title', selectedCountLabel($select));
    }

    function enhanceSelect(select) {

        const $select = $(select);

        if (!$select.prop('multiple') || $select.data('selectpickerSearchToolsReady')) {

            return;
        }

        $select.data('selectpickerSearchToolsReady', true);
        $select.next('.bootstrap-select').addClass('multi-select-enhanced crm-filter-select');
        syncToggleState($select);
        $select.on('changed.bs.select loaded.bs.select refreshed.bs.select', function () {

            syncToggleState($select);
        });

        $select.on('shown.bs.select', function () {

            const picker = $select.data('selectpicker');

            if (!picker || !picker.$menu) {

                return;
            }

            const $wrapper = picker.$menu;
            const $input = $wrapper.find('.bs-searchbox input');

            if ($input.length === 0) {

                return;
            }

            const savedValue = $select.data('liveSearchValue');

            if (typeof savedValue === 'string' && $input.val() !== savedValue) {

                $input.val(savedValue).trigger('input');
            }

            if (!$input.data('selectpickerSearchToolsBound')) {

                $input.data('selectpickerSearchToolsBound', true);
                $input.attr('autocomplete', 'off');
                $input.addClass('selectpicker-search-input');

                $input.on('input', function () {

                    $select.data('liveSearchValue', $(this).val());
                });
            }

            ensureSearchPlaceholder($select, $input);
            ensureClearButton($wrapper, $input);
        });

        $select.on('hidden.bs.select', function () {

            const picker = $select.data('selectpicker');

            if (!picker || !picker.$menu) {

                return;
            }

            const $input = picker.$menu.find('.bs-searchbox input');

            if ($input.length > 0) {

                $select.data('liveSearchValue', $input.val());
            }
        });
    }

    window.SelectpickerSearchTools = {

        init: function (root) {

            const container = root || document;

            $(container).find('select.selectpicker[multiple]').each(function () {

                enhanceSelect(this);
            });
        }
    };
}(window, window.jQuery));

