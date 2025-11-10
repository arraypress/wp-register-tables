<?php
/**
 * Admin Tables Helper Functions
 *
 * Global helper functions for registering admin tables.
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
	 * Simplified function for registering admin tables with BerlinDB integration.
	 *
	 * @param string $id     Unique table identifier
	 * @param array  $config Table configuration
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	function register_admin_table( string $id, array $config ): void {
		Manager::register( $id, $config );
	}
}

if ( ! function_exists( 'create_page_callback' ) ) {
	/**
	 * Create a page render callback for a registered table
	 *
	 * Returns a callable that will render the specified table when invoked.
	 * Useful for WordPress menu registration to avoid creating separate render functions.
	 *
	 * @param string $table_id Table identifier
	 *
	 * @return callable Callback function for add_menu_page/add_submenu_page
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * add_menu_page(
	 *     'Orders',
	 *     'Orders',
	 *     'manage_options',
	 *     'my-orders',
	 *     create_page_callback( 'my_orders_table' )
	 * );
	 */
	function create_page_callback( string $table_id ): callable {
		return function () use ( $table_id ) {
			Manager::render_table( $table_id );
		};
	}
}