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

use ArrayPress\WP\Register\Tables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'register_admin_table' ) ):
	/**
	 * Register a WordPress admin table.
	 *
	 * @param string $id     Unique identifier for the table
	 * @param array  $config Configuration for the table
	 *
	 * @return Tables Instance of Tables class for method chaining
	 */
	function register_admin_table( string $id, array $config ): Tables {
		return Tables::instance()->register( $id, $config );
	}
endif;

if ( ! function_exists( 'register_admin_tables' ) ):
	/**
	 * Register multiple WordPress admin tables at once.
	 *
	 * @param array $tables Array of table configurations with IDs as keys
	 *
	 * @return Tables Instance of Tables class for method chaining
	 */
	function register_admin_tables( array $tables ): Tables {
		$instance = Tables::instance();

		foreach ( $tables as $id => $config ) {
			$instance->register( $id, $config );
		}

		return $instance;
	}
endif;