<?php
/**
 * Table Registration
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Table;

/**
 * Turning a consumer's configuration array into a table this library can work with.
 *
 * Most of it is filling in what was left out. A caller gives labels for one
 * table and not the next, names a primary column here and not there, and the
 * rest has to be derived from what is present — the singular from the plural,
 * the primary column from the columns themselves — because a table that
 * refuses to render until every optional key is supplied is a table nobody
 * registers twice.
 *
 * Everything happens once, at registration, rather than each time a page
 * draws: the request that presses a button is never the request that rendered
 * it, so anything decided at render time is not there when it is needed.
 */
trait Registration {

    /**
     * Register an admin table
     *
     * Registers a new admin table with the given configuration. The table's
     * admin menu page is automatically created — no manual add_menu_page()
     * or add_submenu_page() calls are needed.
     *
     * Configuration is merged with sensible defaults. Labels are auto-generated
     * from singular/plural if not provided. Primary column is auto-detected.
     *
     * @param string $id     Unique table identifier. Used in hooks and internally.
     * @param array  $config Table configuration array. See class docblock for options.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function register( string $id, array $config ): void {
        // Initialize hooks on first registration
        self::init();

        // Default configuration values
        $defaults = [
            // Menu registration
            'page_title'     => '',
            'menu_title'     => '',
            'menu_slug'      => '',
            'parent_slug'    => '',
            'icon'           => 'dashicons-admin-generic',
            'position'       => null,

            // Core settings
            'labels'         => [],
            'callbacks'      => [],

            // Flyout integration
            'flyouts'        => [],
            'add_button'     => '',

            // Sync integration
            'sync'           => '',

            // Column configuration
            'columns'        => [],
            'sortable'       => [],
            'primary_column' => '',
            'hidden_columns' => [],

            // Actions
            'row_actions'    => [],
            'bulk_actions'   => [],

            // Filtering & views
            'views'          => [],
            'filters'        => [],
            'query_args'     => [],
            'status_styles'  => [],

            // Display options
            'per_page'       => 30,
            'searchable'     => true,

            // Security
            'capability'     => 'manage_options',
            'capabilities'   => [],

            // Help
            'help'           => [],

            // Header options
            'logo'           => '',
            'header_title'   => '',
            'header_badge'   => '',

            // Body class
            'body_class'     => '',

            // Row customization
            'row_class'      => null,

            // Empty state
            'empty_state'    => [],

            // Default args
            'orderby'        => '',
            'order'          => 'desc',
        ];

        $config = wp_parse_args( $config, $defaults );

        // Support legacy 'page' key — map to 'menu_slug'
        if ( ! empty( $config['page'] ) && empty( $config['menu_slug'] ) ) {
            $config['menu_slug'] = $config['page'];
        }

        // Ensure menu_slug is set
        if ( empty( $config['menu_slug'] ) ) {
            $config['menu_slug'] = sanitize_key( $id );
        }

        // Parse nested arrays with defaults
        $config['labels']       = self::parse_labels( $config['labels'] );
        $config['callbacks']    = self::parse_callbacks( $config['callbacks'] );
        $config['capabilities'] = self::parse_capabilities( $config['capability'], $config['capabilities'] );
        $config['flyouts']      = self::parse_flyouts( $config['flyouts'] );
        $config['views']        = self::parse_views( $config['views'] );

        // Auto-generate missing labels
        $config['labels'] = self::auto_generate_labels( $config['labels'] );

        // Auto-generate menu titles from labels if not provided
        if ( empty( $config['page_title'] ) ) {
            $config['page_title'] = $config['labels']['title'] ?? ucfirst( $id );
        }
        if ( empty( $config['menu_title'] ) ) {
            $config['menu_title'] = $config['page_title'];
        }

        // Extract per-column flags into top-level arrays
        self::collect_column_flags( $config );

        // Auto-detect primary column
        $config['primary_column'] = self::detect_primary_column(
                $config['primary_column'],
                $config['columns']
        );

        // Store configuration
        self::$tables[ $id ] = $config;
    }

    /**
     * Collect per-column flags into top-level config arrays
     *
     * Scans column definitions for 'sortable' and 'hidden' flags and
     * merges them into the top-level 'sortable' and 'hidden_columns'
     * arrays. This allows column behavior to be defined inline:
     *
     * ```php
     * 'columns' => [
     *     'date_created' => [
     *         'label'    => 'Created',
     *         'sortable' => true,
     *         'hidden'   => true,
     *     ],
     * ],
     * ```
     *
     * Per-column flags are merged with any existing top-level values,
     * so both approaches can be used together.
     *
     * @param array $config Table configuration array (passed by reference).
     *
     * @return void
     * @since 2.0.0
     */
    private static function collect_column_flags( array &$config ): void {
        foreach ( $config['columns'] as $key => $column ) {
            if ( ! is_array( $column ) ) {
                continue;
            }

            if ( ! empty( $column['sortable'] ) && ! in_array( $key, $config['sortable'], true ) ) {
                $config['sortable'][] = $key;
            }

            if ( ! empty( $column['hidden'] ) && ! in_array( $key, $config['hidden_columns'], true ) ) {
                $config['hidden_columns'][] = $key;
            }
        }
    }

    /**
     * Parse labels configuration with defaults
     *
     * @param array $labels User-provided labels.
     *
     * @return array Merged labels with defaults.
     * @since 1.0.0
     *
     */
    private static function parse_labels( array $labels ): array {
        return wp_parse_args( $labels, [
			'singular'         => '',
			'plural'           => '',
			'title'            => '',
			'add_new'          => '',
			'search'           => '',
			'not_found'        => '',
			'not_found_search' => '',
        ] );
    }

    /**
     * Parse callbacks configuration with defaults
     *
     * @param array $callbacks User-provided callbacks.
     *
     * @return array Merged callbacks with defaults.
     * @since 1.0.0
     *
     */
    private static function parse_callbacks( array $callbacks ): array {
        return wp_parse_args( $callbacks, [
			'get_items'  => null,
			'get_counts' => null,
			'delete'     => null,
			'update'     => null,
        ] );
    }

    /**
     * Parse capabilities configuration
     *
     * Supports both a single capability string (applied to all actions)
     * and a granular per-action array. The single string is used as the
     * default for any action not explicitly defined in the array.
     *
     * @param string $capability   Single capability for all actions.
     * @param array  $capabilities Per-action capability overrides.
     *
     * @return array Normalized capabilities array.
     * @since 2.0.0
     *
     */
    private static function parse_capabilities( string $capability, array $capabilities ): array {
        $defaults = [
			'view'   => $capability,
			'edit'   => $capability,
			'delete' => $capability,
			'bulk'   => $capability,
        ];

        return wp_parse_args( $capabilities, $defaults );
    }

    /**
     * Parse flyouts configuration with defaults
     *
     * @param array $flyouts User-provided flyouts.
     *
     * @return array Normalized flyouts array.
     * @since 2.0.0
     *
     */
    private static function parse_flyouts( array $flyouts ): array {
        return wp_parse_args( $flyouts, [
			'edit' => '',
			'view' => '',
        ] );
    }

    /**
     * Parse views configuration
     *
     * Supports both simple array format (auto-generates labels from keys)
     * and explicit key => label format. Can be mixed.
     *
     * Examples:
     * - Simple: ['active', 'pending', 'not_active']
     * - Explicit: ['active' => 'Currently Active', 'pending' => 'Awaiting Review']
     * - Mixed: ['active', 'pending' => 'Awaiting Review', 'inactive']
     *
     * @param array $views User-provided views.
     *
     * @return array Normalized views as key => label pairs.
     * @since 2.0.0
     *
     */
    private static function parse_views( array $views ): array {
        $parsed = [];

        foreach ( $views as $key => $value ) {
            if ( is_numeric( $key ) ) {
                // Simple format: ['active', 'pending'] — auto-label from value
                $parsed[ $value ] = ucwords( str_replace( [ '_', '-' ], ' ', $value ) );
            } else {
                // Explicit format: ['active' => 'Custom Label']
                $parsed[ $key ] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Auto-generate missing labels from singular/plural
     *
     * @param array $labels Parsed labels array.
     *
     * @return array Labels with auto-generated values filled in.
     * @since 1.0.0
     *
     */
    private static function auto_generate_labels( array $labels ): array {
        // Title from plural
        if ( empty( $labels['title'] ) && ! empty( $labels['plural'] ) ) {
            $labels['title'] = ucfirst( $labels['plural'] );
        }

        // Add New from singular
        if ( empty( $labels['add_new'] ) && ! empty( $labels['singular'] ) ) {
            $labels['add_new'] = sprintf(
                    /* translators: %s: the singular name of the thing being added */
                    __( 'Add New %s', 'arraypress' ),
                    ucfirst( $labels['singular'] )
            );
        }

        // Search from plural
        if ( empty( $labels['search'] ) && ! empty( $labels['plural'] ) ) {
            $labels['search'] = sprintf(
                    /* translators: %s: the plural name of the things being searched */
                    __( 'Search %s', 'arraypress' ),
                    $labels['plural']
            );
        }

        return $labels;
    }

    /**
     * Detect primary column from configuration
     *
     * Checks for explicit 'primary' flag in column config, otherwise
     * uses the first non-checkbox column.
     *
     * @param string $primary_column Configured primary column (may be empty).
     * @param array  $columns        Column definitions.
     *
     * @return string Primary column key.
     * @since 1.0.0
     *
     */
    private static function detect_primary_column( string $primary_column, array $columns ): string {
        if ( ! empty( $primary_column ) || empty( $columns ) ) {
            return $primary_column;
        }

        // Look for explicit primary flag
        foreach ( $columns as $key => $column ) {
            if ( is_array( $column ) && ! empty( $column['primary'] ) ) {
                return $key;
            }
        }

        // Fall back to first non-cb column
        foreach ( $columns as $key => $column ) {
            if ( $key !== 'cb' ) {
                return $key;
            }
        }

        return $primary_column;
    }
}
