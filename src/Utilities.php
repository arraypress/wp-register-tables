<?php
/**
 * Admin Tables Registration Helper Functions
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\CustomTables\Tables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'register_custom_table' ) ):
	/**
	 * Register a WordPress admin table.
	 *
	 * @param string $id     Unique identifier for the table
	 * @param array  $config Configuration for the table
	 *
	 * @return Tables Instance of Tables class for method chaining
	 */
	function register_custom_table( string $id, array $config ): Tables {
		return Tables::instance()->register( $id, $config );
	}
endif;

if ( ! function_exists( 'register_custom_tables' ) ):
	/**
	 * Register multiple WordPress admin tables at once.
	 *
	 * @param array $tables Array of table configurations with IDs as keys
	 *
	 * @return Tables Instance of Tables class for method chaining
	 */
	function register_custom_tables( array $tables ): Tables {
		$instance = Tables::instance();

		foreach ( $tables as $id => $config ) {
			$instance->register( $id, $config );
		}

		return $instance;
	}
endif;

if ( ! function_exists( 'get_table_url' ) ):
	/**
	 * Get a URL for a table operation
	 *
	 * @param string       $table_id The table ID
	 * @param string|array $action   The action name or array of query parameters (optional)
	 * @param int|string   $item_id  Optional item ID
	 * @param array        $extra    Optional extra query parameters
	 *
	 * @return string The generated URL
	 */
	function get_table_url( string $table_id, $action = '', $item_id = null, array $extra = [] ): string {
		return Tables::instance()->get_url( $table_id, $action, $item_id, $extra );
	}
endif;