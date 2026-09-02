<?php
/**
 * Table Class
 *
 * Generates a WP_List_Table instance from configuration. This class extends
 * WordPress's WP_List_Table to provide a configuration-driven approach to
 * creating admin list tables with automatic column formatting, row actions,
 * bulk actions, views, and filtering.
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

use WP_List_Table;
use ArrayPress\RegisterTables\Traits\BulkActions;
use ArrayPress\RegisterTables\Traits\ColumnDefinitions;
use ArrayPress\RegisterTables\Traits\ColumnRenderer;
use ArrayPress\RegisterTables\Traits\EmptyState;
use ArrayPress\RegisterTables\Traits\Filtering;
use ArrayPress\RegisterTables\Traits\QueryBuilder;
use ArrayPress\RegisterTables\Traits\RowActions;
use ArrayPress\RegisterTables\Traits\ViewsAndFilters;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


/**
 * Class Table
 *
 * Dynamic table class that extends WP_List_Table based on configuration.
 *
 * ## Usage
 *
 * This class is instantiated automatically by the Manager class. It should not
 * be instantiated directly. Configuration is passed from the registered table.
 *
 * ## Configuration Options
 *
 * The $config array supports the following keys:
 *
 * - `columns`          (array)    Column definitions with labels and optional callbacks
 * - `sortable`         (array)    List of sortable columns
 * - `hidden_columns`   (array)    Columns hidden by default
 * - `primary_column`   (string)   Column for row actions
 * - `bulk_actions`     (array)    Bulk action definitions
 * - `row_actions`      (array|callable) Row action definitions or callback
 * - `views`            (array)    Status view definitions
 * - `filters`          (array)    Dropdown filter definitions
 * - `query_args`       (array)    Whitelisted URL params passed to query
 * - `callbacks`        (array)    Data callbacks (get_items, get_counts, delete)
 * - `labels`           (array)    UI labels (singular, plural, etc.)
 * - `per_page`         (int)      Items per page default
 * - `capability`       (string)   Single capability for all actions
 * - `capabilities`     (array)    Per-action capabilities (overrides capability)
 * - `flyouts`          (array)    Flyout IDs ['edit' => '', 'view' => '']
 * - `row_class`        (callable) Callback returning CSS class(es) for a row
 * - `empty_state`      (array)    Empty state configuration with heading, description, button
 *
 * ## Filters
 *
 * Every one is scoped by table id. These names are not derived from the
 * namespace, so a Strauss-prefixed copy fires them unchanged — which is what
 * makes them reachable, and what made the unscoped ones shared between every
 * plugin on the site that bundles this library.
 *
 * - `arraypress_table_columns_{$id}`          - Modify column definitions
 * - `arraypress_table_hidden_columns_{$id}`   - Modify hidden columns
 * - `arraypress_table_sortable_columns_{$id}` - Modify sortable columns
 * - `arraypress_table_query_args_{$id}`       - Modify query args before fetching items
 * - `arraypress_table_row_actions_{$id}`      - Modify row actions
 * - `arraypress_table_bulk_actions_{$id}`     - Modify bulk actions
 * - `arraypress_table_views_{$id}`            - Modify status views
 *
 * @since 1.0.0
 */
class Table extends WP_List_Table {

    use BulkActions;
    use ColumnDefinitions;
    use ColumnRenderer;
    use EmptyState;
    use Filtering;
    use QueryBuilder;
    use RowActions;
    use ViewsAndFilters;

    /**
     * Table identifier
     *
     * Unique string identifying this table instance. Used in filter hooks
     * and for generating unique element IDs.
     *
     * @since 1.0.0
     * @var string
     */
    private string $id;

    /**
     * Table configuration
     *
     * Complete configuration array passed during registration.
     * Contains columns, actions, callbacks, and display options.
     *
     * @since 1.0.0
     * @var array
     */
    private array $config;

    /**
     * Current status filter
     *
     * The currently active status filter from the URL query string.
     * Empty string means no status filter (show all).
     *
     * @since 1.0.0
     * @var string
     */
    private string $status = '';

    /**
     * Status counts cache
     *
     * Cached array of item counts per status. Populated by get_counts()
     * and used for view tabs and pagination.
     *
     * @since 1.0.0
     * @var array
     */
    private array $counts = [];

    /**
     * Items per page
     *
     * Number of items to display per page. Retrieved from user meta
     * (screen options) or falls back to config default.
     *
     * @since 1.0.0
     * @var int
     */
    private int $per_page;

    /**
     * Constructor
     *
     * Initializes the table with configuration and sets up the parent
     * WP_List_Table with appropriate labels.
     *
     * @param string $id     Unique table identifier used in hooks and element IDs.
     * @param array  $config Table configuration array containing columns, actions,
     *                       callbacks, and display options.
     *
     * @since 1.0.0
     *
     */
    public function __construct( string $id, array $config ) {
        $this->id     = $id;
        $this->config = $config;

        // Get current status from URL
        $this->status = Request::key( 'status' );

        // Get per page from screen options or use config default
        $this->per_page = $this->get_items_per_page( 'per_page', (int) ( $config['per_page'] ?? 30 ) );

        // Initialize parent WP_List_Table
        parent::__construct( [
			'singular' => $config['labels']['singular'] ?? 'item',
			'plural'   => $config['labels']['plural'] ?? 'items',
			'ajax'     => false,
        ] );
    }

    /* =========================================================================
     * COLUMN DEFINITIONS
     * ========================================================================= */

/**
     * Get CSS classes for the table element
     *
     * Removes 'table-view-excerpt' class which WordPress adds when columns
     * are manageable via screen options. This class causes row actions to
     * be always visible instead of on hover.
     *
     * @return array Array of CSS class names.
     * @since 1.0.0
     *
     */
    protected function get_table_classes(): array {
        $classes = parent::get_table_classes();

        // Remove excerpt view class to keep row actions hover behavior
        $classes = array_diff( $classes, [ 'table-view-excerpt' ] );

        return array_values( $classes );
    }

    /* =========================================================================
     * ROW RENDERING
     * ========================================================================= */

    /**
     * Generate a single row with optional custom CSS classes
     *
     * Overrides the parent single_row() method to support the 'row_class'
     * configuration option. When a row_class callback is provided, it is
     * called with the item and its return value is added as CSS class(es)
     * on the <tr> element.
     *
     * This follows the same pattern WordPress core uses in WP_Posts_List_Table
     * where rows receive status-based classes like 'status-draft' and
     * 'status-pending'.
     *
     * @param object $item Data object for the current row.
     *
     * @return void
     * @since 2.0.0
     */
    public function single_row( $item ): void {
        $classes = '';

        // Apply row_class callback if configured
        if ( isset( $this->config['row_class'] ) && is_callable( $this->config['row_class'] ) ) {
            $custom_class = call_user_func( $this->config['row_class'], $item );

            if ( ! empty( $custom_class ) ) {
                $classes = is_array( $custom_class )
                        ? implode( ' ', array_map( 'sanitize_html_class', $custom_class ) )
                        : sanitize_html_class( $custom_class );
            }
        }

        // Auto-add a status class when the row has a status to add.
        //
        // A row is whatever get_items() returned, and that is very often an
        // array — $wpdb->get_results() with ARRAY_A, or a plugin that never
        // had objects. method_exists() takes an object or a class name and
        // throws a TypeError on anything else, so this used to fatal on the
        // first row of any table built that way, with a stack trace instead
        // of a screen.
        $status = (string) Row::get( $item, 'status', '' );

        if ( '' !== $status ) {
            $classes .= ' status-' . sanitize_html_class( $status );
        }

        $classes = trim( $classes );

        // The row carries its id so Quick Edit can find it, hide it, and
        // replace it with what the save returned. Core does the same with
        // `post-{ID}`.
        $attributes = sprintf( ' id="item-%s"', esc_attr( (string) $this->get_item_id( $item ) ) );

        if ( ! empty( $classes ) ) {
            $attributes .= ' class="' . esc_attr( $classes ) . '"';
        }

        echo '<tr' . $attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.

        $this->single_row_columns( $item );
        echo '</tr>';
    }

    /* =========================================================================
     * DATA RETRIEVAL
     * ========================================================================= */

/* =========================================================================
     * COLUMN RENDERING
     * ========================================================================= */

/* =========================================================================
     * ROW ACTIONS
     * ========================================================================= */

/* =========================================================================
     * BULK ACTIONS
     * ========================================================================= */

/* =========================================================================
     * VIEWS (STATUS TABS)
     * ========================================================================= */

/* =========================================================================
     * FILTERS (DROPDOWNS)
     * ========================================================================= */

/* =========================================================================
     * PREPARATION & DISPLAY
     * ========================================================================= */

/* =========================================================================
     * HELPER METHODS
     * ========================================================================= */

    /**
     * Get items per page from screen options
     *
     * Retrieves the user's preferred items per page from their user meta,
     * falling back to the default from screen options or config.
     *
     * @param string $option        Option name (unused, kept for compatibility).
     * @param int    $default_value Default value from config.
     *
     * @return int Items per page.
     * @since 1.0.0
     *
     */
    protected function get_items_per_page( $option, $default_value = 30 ): int {
        // An ajax request -- a quick edit saving and drawing its row back --
        // never loads the screen API, so the function itself is missing
        // there. The default is what the page would have used anyway; the
        // one row being drawn does not page.
        if ( ! function_exists( 'get_current_screen' ) ) {
            return $default_value;
        }

        $screen = get_current_screen();

        if ( ! $screen ) {
            return $default_value;
        }

        $option_name = $screen->get_option( 'per_page', 'option' );

        if ( empty( $option_name ) ) {
            return $default_value;
        }

        $user     = get_current_user_id();
        $per_page = get_user_meta( $user, $option_name, true );

        if ( empty( $per_page ) || $per_page < 1 ) {
            $per_page = $screen->get_option( 'per_page', 'default' ) ?: $default_value;
        }

        return absint( $per_page );
    }
}
