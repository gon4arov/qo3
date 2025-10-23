/**
 * qmenu Admin Module JavaScript
 * Handles dynamic item management, drag-and-drop sorting, and autocomplete
 */

(function($) {
    'use strict';

    var QMenu = {
        itemRow: 0,
        routes: [],
        userToken: '',

        init: function(config) {
            this.itemRow = config.itemRow || 0;
            this.routes = config.routes || [];
            this.userToken = config.userToken || '';
            this.strings = config.strings || {};

            this.bindEvents();
            this.initializeExistingRows();
            this.initSortable();
        },

        bindEvents: function() {
            $('#qmenu-add-item').on('click', $.proxy(this.addItem, this));
            $('#qmenu-items').on('click', '.qmenu-remove', $.proxy(this.removeItem, this));
            $('#qmenu-items').on('change', '.qmenu-type', $.proxy(this.onTypeChange, this));
        },

        initializeExistingRows: function() {
            var self = this;
            $('#qmenu-items tbody tr.qmenu-sortable-row').each(function() {
                self.attachAutocomplete($(this));
                self.attachColorControls($(this));
            });
            $('#qmenu-items .qmenu-type').trigger('change');
        },

        initSortable: function() {
            var self = this;
            $('#qmenu-items tbody').sortable({
                handle: '.qmenu-drag-handle',
                placeholder: 'qmenu-sortable-placeholder',
                helper: 'clone',
                opacity: 0.8,
                update: function() {
                    self.updateRowIndexes();
                }
            });
        },

        addItem: function() {
            var rowId = 'qmenu-item-row' + this.itemRow;
            var html = this.buildRowHtml(rowId, this.itemRow);

            $('#qmenu-items tbody').append(html);

            var $row = $('#' + rowId);
            this.attachAutocomplete($row);
            this.attachColorControls($row);

            this.itemRow++;
            $('#qmenu-items tbody').sortable('refresh');
        },

        buildRowHtml: function(rowId, index) {
            var s = this.strings;

            return [
                '<tr id="', rowId, '" class="qmenu-sortable-row">',
                  '<td class="text-center qmenu-row-number" style="vertical-align: middle;">',
                    '<strong>', (index + 1), '</strong>',
                  '</td>',
                  '<td class="text-center qmenu-drag-handle" style="cursor: move; vertical-align: middle;">',
                    '<i class="fa fa-bars text-muted"></i>',
                  '</td>',
                  '<td class="text-left">',
                    '<input type="text" name="module_qmenu_items[', index, '][label]" value="" placeholder="', s.label, '" class="form-control qmenu-label-input" />',
                  '</td>',
                  this.buildColorCell(index),
                  this.buildTypeCell(index),
                  this.buildDestinationCell(index),
                  '<td class="text-center">',
                    '<input type="hidden" name="module_qmenu_items[', index, '][enabled]" value="0" />',
                    '<input type="checkbox" name="module_qmenu_items[', index, '][enabled]" value="1" checked="checked" />',
                  '</td>',
                  '<td class="text-center">',
                    '<input type="checkbox" name="module_qmenu_items[', index, '][new_tab]" value="1" />',
                  '</td>',
                  '<td class="text-right">',
                    '<button type="button" data-row="', rowId, '" class="btn btn-danger qmenu-remove">',
                      '<i class="fa fa-minus-circle"></i>',
                    '</button>',
                  '</td>',
                '</tr>'
            ].join('');
        },

        buildColorCell: function(index) {
            return [
                '<td class="text-center">',
                  '<div class="input-group qmenu-color-group" data-row="', index, '">',
                    '<span class="input-group-addon" style="padding:0;border:none;background:transparent;">',
                      '<input type="color" class="qmenu-color-picker" value="#000000" style="width:40px;height:34px;border:none;padding:0;" />',
                    '</span>',
                    '<input type="hidden" name="module_qmenu_items[', index, '][color]" value="#000000" class="qmenu-color-value" />',
                    '<span class="input-group-btn">',
                      '<button type="button" class="btn btn-default qmenu-color-clear" title="', this.strings.clearColor, '">',
                        '<span aria-hidden="true">&times;</span>',
                      '</button>',
                    '</span>',
                  '</div>',
                '</td>'
            ].join('');
        },

        buildTypeCell: function(index) {
            var s = this.strings;
            return [
                '<td class="text-left">',
                  '<select name="module_qmenu_items[', index, '][type]" class="form-control qmenu-type">',
                    '<option value="link">', s.typeLink, '</option>',
                    '<option value="route">', s.typeRoute, '</option>',
                    '<option value="category">', s.typeCategory, '</option>',
                    '<option value="product">', s.typeProduct, '</option>',
                    '<option value="information">', s.typeInformation, '</option>',
                  '</select>',
                '</td>'
            ].join('');
        },

        buildDestinationCell: function(index) {
            var s = this.strings;
            return [
                '<td class="text-left">',
                  '<div class="qmenu-destination qmenu-destination-link" data-type="link">',
                    '<input type="text" name="module_qmenu_items[', index, '][href]" value="" placeholder="', s.helpLink, '" class="form-control qmenu-field-link" />',
                  '</div>',
                  '<div class="qmenu-destination qmenu-destination-route" data-type="route" style="display:none">',
                    '<input type="text" name="module_qmenu_items[', index, '][route]" value="" placeholder="', s.helpRoute, '" class="form-control qmenu-field-route" data-autocomplete="route" />',
                  '</div>',
                  '<div class="qmenu-destination qmenu-destination-category" data-type="category" style="display:none">',
                    '<input type="hidden" name="module_qmenu_items[', index, '][category_id]" value="0" class="qmenu-entity-id" data-entity="category" />',
                    '<input type="text" name="module_qmenu_items[', index, '][category_name]" value="" placeholder="', s.helpCategory, '" class="form-control qmenu-entity-input" data-autocomplete="category" />',
                  '</div>',
                  '<div class="qmenu-destination qmenu-destination-product" data-type="product" style="display:none">',
                    '<input type="hidden" name="module_qmenu_items[', index, '][product_id]" value="0" class="qmenu-entity-id" data-entity="product" />',
                    '<input type="text" name="module_qmenu_items[', index, '][product_name]" value="" placeholder="', s.helpProduct, '" class="form-control qmenu-entity-input" data-autocomplete="product" />',
                  '</div>',
                  '<div class="qmenu-destination qmenu-destination-information" data-type="information" style="display:none">',
                    '<input type="hidden" name="module_qmenu_items[', index, '][information_id]" value="0" class="qmenu-entity-id" data-entity="information" />',
                    '<input type="text" name="module_qmenu_items[', index, '][information_name]" value="" placeholder="', s.helpInformation, '" class="form-control qmenu-entity-input" data-autocomplete="information" />',
                  '</div>',
                '</td>'
            ].join('');
        },

        removeItem: function(e) {
            var rowId = $(e.currentTarget).data('row');
            $('#' + rowId).remove();
            this.updateRowIndexes();
        },

        onTypeChange: function(e) {
            var $row = $(e.currentTarget).closest('tr');
            var type = $(e.currentTarget).val();

            $row.find('.qmenu-destination').hide();
            $row.find('.qmenu-destination-' + type).show();

            switch(type) {
                case 'link':
                    $row.find('.qmenu-field-route, .qmenu-entity-id, .qmenu-entity-input').val('');
                    $row.find('.qmenu-entity-id').val('0');
                    break;
                case 'route':
                    $row.find('.qmenu-field-link, .qmenu-entity-id, .qmenu-entity-input').val('');
                    $row.find('.qmenu-entity-id').val('0');
                    break;
                default:
                    $row.find('.qmenu-field-link, .qmenu-field-route').val('');
                    break;
            }

            this.attachAutocomplete($row);
        },

        updateRowIndexes: function() {
            $('#qmenu-items tbody tr.qmenu-sortable-row').each(function(index) {
                var $row = $(this);
                var newId = 'qmenu-item-row' + index;

                $row.attr('id', newId);

                $row.find('input, select').each(function() {
                    var $input = $(this);
                    var name = $input.attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + index + ']');
                        $input.attr('name', name);
                    }
                });

                $row.find('.qmenu-remove').attr('data-row', newId);
                $row.find('.qmenu-color-group').attr('data-row', index);

                // Update row number
                $row.find('.qmenu-row-number strong').text(index + 1);
            });
        },

        attachAutocomplete: function($context) {
            var self = this;
            $context.find('[data-autocomplete]').each(function() {
                var $input = $(this);

                if ($input.data('qmenu-autocomplete')) {
                    return;
                }

                var type = $input.data('autocomplete');

                if (type !== 'route' && !$input.data('qmenu-entity-listener')) {
                    $input.on('input', function() {
                        $(this).closest('.qmenu-destination').find('.qmenu-entity-id').val('0');
                    });
                    $input.data('qmenu-entity-listener', true);
                }

                $input.data('qmenu-autocomplete', true);

                if (type === 'route') {
                    self.setupRouteAutocomplete($input);
                } else {
                    self.setupEntityAutocomplete($input, type);
                }

                $input.on('focus', function() {
                    $(this).autocomplete('search', $(this).val() || '');
                });
            });
        },

        setupRouteAutocomplete: function($input) {
            var self = this;
            $input.autocomplete({
                minLength: 0,
                autoFocus: true,
                source: function(request, response) {
                    var term = typeof request === 'object' ? request.term : request;
                    var results = [];

                    if (term) {
                        var lower = term.toLowerCase();
                        for (var i = 0; i < self.routes.length && results.length < 20; i++) {
                            if (self.routes[i].toLowerCase().indexOf(lower) !== -1) {
                                results.push(self.routes[i]);
                            }
                        }
                    } else {
                        results = self.routes.slice(0, 20);
                    }

                    response($.map(results, function(route) {
                        return { label: route, value: route };
                    }));
                },
                select: function(event, ui) {
                    event.preventDefault();
                    $input.val(ui.item.value);
                    self.updateLabelIfEmpty($input, ui.item.value);
                }
            });
        },

        setupEntityAutocomplete: function($input, type) {
            var self = this;
            $input.autocomplete({
                minLength: 0,
                autoFocus: true,
                source: function(request, response) {
                    var term = typeof request === 'object' ? request.term : request;
                    $.ajax({
                        url: 'index.php?route=extension/module/qmenu/autocomplete&user_token=' + self.userToken + '&type=' + type + '&filter_name=' + encodeURIComponent(term || ''),
                        dataType: 'json',
                        success: function(json) {
                            response($.map(json, function(item) {
                                return { label: item.label, value: item.value };
                            }));
                        }
                    });
                },
                select: function(event, ui) {
                    event.preventDefault();
                    $input.val(ui.item.label);
                    $input.closest('.qmenu-destination').find('.qmenu-entity-id').val(ui.item.value);
                    self.updateLabelIfEmpty($input, ui.item.label);
                }
            });
        },

        updateLabelIfEmpty: function($input, value) {
            var $labelInput = $input.closest('tr').find('.qmenu-label-input');
            if ($labelInput.val() === '') {
                $labelInput.val(value);
            }
        },

        attachColorControls: function($context) {
            $context.find('.qmenu-color-group').each(function() {
                var $group = $(this);

                if ($group.data('qmenu-color-initialized')) {
                    return;
                }

                $group.data('qmenu-color-initialized', true);

                var $picker = $group.find('.qmenu-color-picker');
                var $value = $group.find('.qmenu-color-value');
                var $clear = $group.find('.qmenu-color-clear');

                $picker.val($value.val() || '#000000');

                $picker.on('change input', function() {
                    var val = $(this).val();
                    if (val) {
                        $value.val(val);
                    }
                });

                $clear.on('click', function() {
                    $value.val('');
                    $picker.val('#000000');
                });
            });
        }
    };

    window.QMenu = QMenu;

})(jQuery);
