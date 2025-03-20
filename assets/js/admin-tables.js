/**
 * Admin Tables JavaScript with WordPress-compatible class names
 * Enhanced with interactive column support
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.1.0
 */

(function ($) {
    'use strict';

    /**
     * Advanced Admin Tables functionality
     */
    var AdvancedAdminTables = {
        /**
         * Initialize the tables functionality
         */
        init: function () {
            var self = this;

            // Initialize all tables
            $('.wp-list-table').each(function () {
                self.initTable($(this));
            });

            // Toggle advanced filters panel
            $(document).on('click', '#wp-more-filters', function (e) {
                e.preventDefault();
                $('#wp-advanced-filters').slideToggle(200);
            });

            // Close filters panel
            $(document).on('click', '.wp-close-filters', function (e) {
                e.preventDefault();
                $('#wp-advanced-filters').slideUp(200);
            });

            // Clean up form submission to avoid empty parameters and interactive inputs
            $('form[id$="-filter"]').on('submit', function () {
                // Disable all interactive elements to prevent them from being submitted
                $(this).find('[data-interactive="true"]').each(function () {
                    $(this).find('input').prop('disabled', true);
                });

                // Get all inputs that aren't submit buttons
                $(this).find('input, select').not('[type="submit"]').each(function () {
                    // If value is empty and it's not a hidden field for essential params
                    if (!$(this).val() &&
                        !$(this).is('[type="hidden"][name="page"]') &&
                        !$(this).is('[type="hidden"][name="post_type"]')) {
                        // Disable the input so it's not included in the form submission
                        $(this).prop('disabled', true);
                    }
                });

                // Re-enable all inputs after submission for future form use
                setTimeout(function () {
                    $('form[id$="-filter"] input, form[id$="-filter"] select').prop('disabled', false);
                }, 100);

                return true;
            });

            // Initialize action button handling
            this.initActionButtons();

            // Initialize interactive columns
            this.initInteractiveColumns();
        },

        /**
         * Initialize a specific table
         *
         * @param {jQuery} $table The table jQuery object
         */
        initTable: function ($table) {
            var tableId = $table.attr('id') || '';

            // Apply column widths if defined
            this.applyColumnWidths($table);

            // Custom initialization for specific tables can go here
            if (tableId) {
                // Trigger custom event that table configurations can hook into
                $(document).trigger('advanced_admin_table_init', [tableId, $table]);
            }
        },

        /**
         * Apply column widths if defined in the markup
         *
         * @param {jQuery} $table The table jQuery object
         */
        applyColumnWidths: function ($table) {
            $table.find('th[data-width]').each(function () {
                var width = $(this).data('width');
                if (width) {
                    $(this).css('width', width);
                    // Also apply to corresponding td cells for consistency
                    var index = $(this).index();
                    $table.find('tr td:nth-child(' + (index + 1) + ')').css('width', width);
                }
            });
        },

        /**
         * Initialize action buttons with improved URL handling
         */
        initActionButtons: function () {
            // Add New button handling
            $('.page-title-action').on('click', function (e) {
                // We can add custom behavior here if needed in the future
                // For now, this is just a hook point
            });

            // Enhance row action links if needed
            $('.row-actions a').on('click', function (e) {
                var $link = $(this);

                // Handle confirmation dialogs
                if ($link.data('confirm')) {
                    if (!confirm($link.data('confirm'))) {
                        e.preventDefault();
                        return false;
                    }
                }

                // We can add more custom behavior here if needed
            });
        },

        /**
         * Initialize interactive columns
         */
        initInteractiveColumns: function () {
            // Toggle switch interaction - supports both Elementify and custom toggles
            $(document).on('change', '.toggle-container input[type="checkbox"], .table-toggle input', function () {
                var $element = $(this).closest('[data-interactive="true"]');
                AdvancedAdminTables.handleInteractiveUpdate($element, $(this).prop('checked'));
            });

            // Featured star interaction
            $(document).on('click', '.featured-container, .table-featured', function (e) {
                e.preventDefault();
                var $element = $(this);
                var isFeatured = $element.hasClass('is-featured') || $element.hasClass('featured');
                AdvancedAdminTables.handleInteractiveUpdate($element, !isFeatured);
            });
        },

        /**
         * Handle interactive column updates
         *
         * @param {jQuery} $element The interactive element
         * @param {boolean} newValue The new value to set
         */
        handleInteractiveUpdate: function ($element, newValue) {
            // Skip if not interactive
            if (!$element.data('interactive')) {
                return;
            }

            // Get data from element
            var itemId = $element.data('item-id');
            var column = $element.data('column');
            var tableId = $element.data('table-id');
            var nonce = $element.data('nonce');

            // Apply updating state
            $element.addClass('updating');

            // Disable input if it exists
            $element.find('input').prop('disabled', true);

            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'table_column_update',
                    nonce: nonce,
                    table_id: tableId,
                    column: column,
                    item_id: itemId,
                    value: newValue
                },
                success: function (response) {
                    if (response.success) {
                        // Update was successful
                        AdvancedAdminTables.updateElementState($element, response.data.value);
                    } else {
                        // Update failed, revert the element
                        AdvancedAdminTables.updateElementState($element, !newValue);

                        // Only show error notices, not success
                        if (response.data && response.data.message) {
                            AdvancedAdminTables.showNotice(response.data.message, 'error');
                        }
                    }
                },
                error: function (xhr, status, error) {
                    // Revert the element on error
                    AdvancedAdminTables.updateElementState($element, !newValue);
                },
                complete: function () {
                    // Remove updating state
                    $element.removeClass('updating');
                    $element.find('input').prop('disabled', false);
                }
            });
        },

        /**
         * Update the state of an interactive element
         *
         * @param {jQuery} $element The element to update
         * @param {boolean} value The new value
         */
        updateElementState: function ($element, value) {
            // Handle Elementify toggle component
            if ($element.hasClass('toggle-container')) {
                $element.toggleClass('toggle-checked', value);
                $element.find('input[type="checkbox"]').prop('checked', value);
            }
            // Handle custom toggle
            else if ($element.hasClass('table-toggle')) {
                $element.toggleClass('checked', value);
                $element.find('input').prop('checked', value);
            }
            // Handle Elementify featured component
            else if ($element.hasClass('featured-container')) {
                $element.toggleClass('is-featured', value);
                $element.find('.dashicons')
                    .removeClass('dashicons-star-filled dashicons-star-empty')
                    .addClass(value ? 'dashicons-star-filled' : 'dashicons-star-empty');
            }
            // Handle custom featured
            else if ($element.hasClass('table-featured')) {
                $element.toggleClass('featured', value);
                $element.find('.dashicons')
                    .removeClass('dashicons-star-filled dashicons-star-empty')
                    .addClass(value ? 'dashicons-star-filled' : 'dashicons-star-empty');
            }
        },

        /**
         * Show a WordPress admin notice
         *
         * @param {string} message The message to show
         * @param {string} type The notice type (success, error, warning, info)
         */
        showNotice: function (message, type) {
            // Create notice element
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            // Add dismiss button
            var dismissButton = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            dismissButton.on('click', function () {
                notice.fadeOut(100, function () {
                    notice.remove();
                });
            });

            notice.append(dismissButton);

            // Find existing notices container or create one
            var noticesContainer = $('.wp-header-end').length ?
                $('.wp-header-end') :
                $('.wrap h1, .wrap h2').first();

            // Insert the notice
            if (noticesContainer.length) {
                noticesContainer.after(notice);
            } else {
                $('.wrap').prepend(notice);
            }

            // Auto-dismiss after 5 seconds for success notices
            if (type === 'success') {
                setTimeout(function () {
                    notice.fadeOut(500, function () {
                        notice.remove();
                    });
                }, 5000);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        AdvancedAdminTables.init();
    });

})(jQuery);