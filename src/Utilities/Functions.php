<?php
/**
 * Admin Tables Helper Functions
 *
 * Global helper functions for registering and rendering admin tables.
 * These functions provide a convenient procedural API for the Manager class.
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterTables\Manager;

if ( ! function_exists( 'register_admin_table' ) ) {
	/**
	 * Register an admin table
	 *
	 * Registers a new admin list table with the given configuration.
	 * The admin menu page is automatically created — no manual
	 * add_menu_page() or add_submenu_page() calls are needed.
	 *
	 * @param string $id     Unique table identifier. Used in hooks and internally.
	 * @param array  $config Table configuration array. See Manager class for options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * register_admin_table( 'my_customers', [
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
	 */
	function register_admin_table( string $id, array $config ): void {
		Manager::register( $id, $config );
	}
}

if ( ! function_exists( 'get_table_renderer' ) ) {
	/**
	 * Get a render callback for a registered table
	 *
	 * Returns a callable that renders the specified table. This is provided
	 * for edge cases where you need to embed a table in an existing admin
	 * page rather than using the automatic menu registration.
	 *
	 * Note: In most cases, you don't need this function. The library
	 * automatically registers menu pages via the register_admin_table()
	 * configuration (page_title, menu_title, menu_slug, parent_slug).
	 *
	 * @param string $table_id Table identifier (as passed to register_admin_table).
	 *
	 * @return callable Callback function suitable for add_menu_page/add_submenu_page.
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * // Only needed for custom menu setups or embedding in existing pages
	 * add_submenu_page(
	 *     'my-plugin',
	 *     'Customers',
	 *     'Customers',
	 *     'manage_options',
	 *     'my-plugin-customers',
	 *     get_table_renderer( 'my_customers' )
	 * );
	 */
	function get_table_renderer( string $table_id ): callable {
		return function () use ( $table_id ) {
			Manager::render_table( $table_id );
		};
	}
}