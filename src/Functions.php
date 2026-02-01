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

if ( ! function_exists( 'init_admin_tables' ) ) {
	/**
	 * Initialize the admin tables manager
	 *
	 * Sets up WordPress hooks for action processing, screen options, and asset
	 * enqueuing. Call this once in your plugin after registering all tables,
	 * typically on the 'plugins_loaded' or 'init' hook.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * // In your plugin's main file
	 * add_action( 'plugins_loaded', function() {
	 *     // Register tables first
	 *     register_admin_table( 'my_customers', [ ... ] );
	 *
	 *     // Then initialize
	 *     init_admin_tables();
	 * } );
	 */
	function init_admin_tables(): void {
		Manager::init();
	}
}

if ( ! function_exists( 'register_admin_table' ) ) {
	/**
	 * Register an admin table
	 *
	 * Registers a new admin list table with the given configuration.
	 * Tables must be registered before init_admin_tables() is called.
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
	 *     'page'      => 'my-plugin-customers',
	 *     'labels'    => [
	 *         'singular' => 'customer',
	 *         'plural'   => 'customers',
	 *     ],
	 *     'columns'   => [
	 *         'name'   => 'Name',
	 *         'email'  => 'Email',
	 *         'status' => 'Status',
	 *     ],
	 *     'callbacks' => [
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
	 * Returns a callable that renders the specified table. Useful for
	 * WordPress menu registration where a callback function is required.
	 *
	 * @param string $table_id Table identifier (as passed to register_admin_table).
	 *
	 * @return callable Callback function suitable for add_menu_page/add_submenu_page.
	 *
	 * @since 1.0.0
	 *
	 * @example
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