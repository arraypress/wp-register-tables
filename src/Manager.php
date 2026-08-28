<?php
/**
 * Admin Tables Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin list tables.
 * Provides a configuration-driven approach to creating admin tables with support for:
 * - Automatic menu page registration
 * - Column definitions with automatic formatting
 * - Row actions (edit, delete, custom)
 * - Bulk actions with callbacks
 * - Status views (tabs)
 * - Dropdown filters
 * - Search functionality
 * - Screen options (items per page)
 * - Help tabs
 * - Modern EDD-style headers
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

use ArrayPress\RegisterTables\Traits\ActionProcessing;
use ArrayPress\RegisterTables\Traits\AdminNotices;
use ArrayPress\RegisterTables\Traits\AssetManager;
use ArrayPress\RegisterTables\Traits\BulkProcessing;
use ArrayPress\RegisterTables\Traits\MenuBuilder;
use ArrayPress\RegisterTables\Traits\PageRenderer;
use ArrayPress\RegisterTables\Traits\Registration;
use ArrayPress\RegisterTables\Traits\ScreenSetup;
use ArrayPress\RegisterTables\Traits\Urls;

// Exit if accessed directly

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Class Manager
 *
 * Static manager class for admin table registration and rendering.
 *
 * ## Basic Usage
 *
 * ```php
 * // Register a table (menu page is created automatically)
 * Manager::register( 'my_customers', [
 *     'page_title'  => 'Customers',
 *     'menu_title'  => 'Customers',
 *     'menu_slug'   => 'my-plugin-customers',
 *     'parent_slug' => 'my-plugin',
 *     'capability'  => 'manage_options',
 *     'labels'      => [
 *         'singular' => 'customer',
 *         'plural'   => 'customers',
 *     ],
 *     'columns'     => [
 *         'name'   => 'Name',
 *         'email'  => 'Email',
 *         'status' => 'Status',
 *     ],
 *     'callbacks'   => [
 *         'get_items'  => [ Customers::class, 'query' ],
 *         'get_counts' => [ Customers::class, 'get_counts' ],
 *     ],
 * ] );
 * ```
 *
 * ## Configuration Options
 *
 * | Option              | Type              | Description                                      |
 * |---------------------|-------------------|--------------------------------------------------|
 * | page_title          | string            | Page title tag text                              |
 * | menu_title          | string            | Menu item text                                   |
 * | menu_slug           | string            | Admin page slug (required)                       |
 * | parent_slug         | string            | Parent menu slug (for submenu pages)             |
 * | capability          | string            | Capability required to view page                 |
 * | icon                | string            | Dashicon or icon URL (top-level only)            |
 * | position            | int|null          | Menu position (top-level only)                   |
 * | labels              | array             | UI labels (singular, plural, title, etc.)        |
 * | columns             | array             | Column definitions                               |
 * | sortable            | array             | Sortable column keys                             |
 * | primary_column      | string            | Column for row actions                           |
 * | hidden_columns      | array             | Columns hidden by default                        |
 * | row_actions         | array|callable    | Row action definitions                           |
 * | bulk_actions        | array             | Bulk action definitions                          |
 * | views               | array             | Status view definitions                          |
 * | filters             | array             | Dropdown filter definitions                      |
 * | callbacks           | array             | Data callbacks (get_items, get_counts, delete)   |
 * | status_styles       | array             | Custom status => CSS class mappings              |
 * | capabilities        | array             | Per-action capabilities (overrides capability)   |
 * | per_page            | int               | Items per page default (30)                      |
 * | searchable          | bool              | Enable search box (true)                         |
 * | logo                | string            | Header logo URL                                  |
 * | header_title        | string            | Custom header title                              |
 * | flyouts             | array             | Flyout IDs ['edit' => '', 'view' => '']          |
 * | add_button          | string|callable   | Add button: flyout ID, URL, or callback          |
 * | help                | array             | Help tab definitions                             |
 * | body_class          | string            | Additional CSS class for admin body               |
 *
 * ## Action Hooks
 *
 * - `arraypress_before_render_table_{$id}`    - Before the table renders
 * - `arraypress_after_render_table_{$id}`     - After it renders
 * - `arraypress_table_item_deleted_{$id}`     - After an item is deleted
 * - `arraypress_table_bulk_action_{$id}`      - After a bulk action
 * - `arraypress_table_bulk_action_{$id}_{$action}` - After one particular bulk action
 * - `arraypress_table_single_action_{$id}`    - A row action with no handler of its own
 * - `arraypress_table_admin_notices_{$id}`    - Notices above the table
 *
 * ## Filter Hooks
 *
 * - `arraypress_table_admin_notices`          - Custom admin notices
 * - `arraypress_table_admin_notices_{$id}`    - Custom notices for specific table
 *
 * @since 1.0.0
 */
class Manager {

    use ActionProcessing;
    use AdminNotices;
    use AssetManager;
    use BulkProcessing;
    use MenuBuilder;
    use PageRenderer;
    use Registration;
    use ScreenSetup;
    use Urls;

    /* =========================================================================
     * PROPERTIES
     * ========================================================================= */

    /**
     * Registered tables storage
     *
     * Associative array of table ID => configuration pairs.
     * Populated by register() calls.
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private static array $tables = [];

    /**
     * Hook suffixes for registered menu pages
     *
     * Maps table ID => hook suffix returned by add_menu_page/add_submenu_page.
     * Used for targeting screen options and help tabs.
     *
     * @since 2.0.0
     * @var array<string, string>
     */
    private static array $hook_suffixes = [];

    /**
     * Asset enqueue flag
     *
     * Prevents duplicate asset enqueuing when multiple tables
     * are registered on the same page.
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $assets_enqueued = false;

    /**
     * Initialization flag
     *
     * Prevents duplicate hook registration when multiple tables
     * are registered.
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $initialized = false;

    /* =========================================================================
     * REGISTRATION
     * ========================================================================= */

/* =========================================================================
     * INITIALIZATION
     * ========================================================================= */

    /**
     * Initialize the manager
     *
     * Hooks into WordPress admin to enable menu registration, action processing,
     * screen options, and asset enqueuing. Called automatically on first
     * register() call.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function init(): void {
        // Only initialize once
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // Handle screen option saves (must be early, before WP processes the form)
        self::handle_screen_options();

        // Register admin menu pages
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );

        // Process actions early (before output) to enable redirects
        add_action( 'admin_init', [ __CLASS__, 'process_early_actions' ], 20 );

        // Setup screen options after page loads
        add_action( 'admin_init', [ __CLASS__, 'setup_load_hooks' ], 999 );

        // Enqueue CSS/JS assets
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Add body classes for table pages
        add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );

        // Fix menu highlight for submenu pages
        add_filter( 'parent_file', [ __CLASS__, 'fix_parent_menu_highlight' ] );
        add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_highlight' ] );
    }

    /* =========================================================================
     * MENU REGISTRATION
     * ========================================================================= */

/* =========================================================================
     * ASSETS
     * ========================================================================= */

/* =========================================================================
     * SCREEN OPTIONS
     * ========================================================================= */

/* =========================================================================
     * ACTION PROCESSING
     * ========================================================================= */

/* =========================================================================
     * RENDERING
     * ========================================================================= */

/* =========================================================================
     * UTILITY METHODS
     * ========================================================================= */

    /**
     * Get clean base URL for redirects
     *
     * Builds a URL with the page parameter and preserves status, search,
     * and custom filter parameters. Used for post-action redirects.
     *
     * @param array $config Table configuration.
     *
     * @return string Clean admin URL.
     * @since 1.0.0
     *
     */
    /**
     * Add body classes to admin table pages
     *
     * Adds CSS classes to the admin body element for styling table pages.
     * Classes added:
     * - `admin-table` - Generic class for all table pages
     * - `admin-table-{$id}` - Table-specific class
     * - Custom class from `body_class` config option
     *
     * @param string $classes Space-separated list of body classes.
     *
     * @return string Modified classes string.
     * @since 1.0.0
     *
     */
    public static function add_body_class( string $classes ): string {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return $classes;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( $config['menu_slug'] === $page ) {
                // Add generic table class
                $classes .= ' admin-table';

                // Add table-specific class
                $classes .= ' admin-table-' . sanitize_html_class( $id );

                // Add custom class from config if provided
                if ( ! empty( $config['body_class'] ) ) {
                    $classes .= ' ' . sanitize_html_class( $config['body_class'] );
                }

                break;
            }
        }

        return $classes;
    }

    /* =========================================================================
     * TABLE MANAGEMENT
     * ========================================================================= */

    /**
     * Get a registered table configuration
     *
     * @param string $id Table identifier.
     *
     * @return array|null Table configuration or null if not found.
     * @since 1.0.0
     *
     */
    public static function get_table( string $id ): ?array {
        return self::$tables[ $id ] ?? null;
    }

    /**
     * Check if a table is registered
     *
     * @param string $id Table identifier.
     *
     * @return bool True if registered.
     * @since 1.0.0
     *
     */
    public static function has_table( string $id ): bool {
        return isset( self::$tables[ $id ] );
    }

    /**
     * Unregister a table
     *
     * Removes a table from the registry. Useful for conditional table removal.
     *
     * @param string $id Table identifier.
     *
     * @return bool True if removed, false if not found.
     * @since 1.0.0
     *
     */
    public static function unregister( string $id ): bool {
        if ( isset( self::$tables[ $id ] ) ) {
            unset( self::$tables[ $id ] );

            return true;
        }

        return false;
    }

    /**
     * Get all registered tables
     *
     * @return array All registered table configurations.
     * @since 1.0.0
     *
     */
    public static function get_all_tables(): array {
        return self::$tables;
    }

    /**
     * Get the hook suffix for a registered table
     *
     * Returns the hook suffix from add_menu_page/add_submenu_page,
     * useful for targeting specific admin pages.
     *
     * @param string $id Table identifier.
     *
     * @return string|null Hook suffix or null if not found.
     * @since 2.0.0
     *
     */
    public static function get_hook_suffix( string $id ): ?string {
        return self::$hook_suffixes[ $id ] ?? null;
    }
}
