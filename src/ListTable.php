<?php
/**
 * Table Instance Class
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Table_Instance class
 *
 * Handles the actual rendering and functionality of the table
 */
class ListTable extends \WP_List_Table {

	use Traits\ListTable\Core;
	use Traits\ListTable\Filters;
	use Traits\ListTable\Data;
	use Traits\ListTable\Columns;
	use Traits\ListTable\Filters;
	use Traits\ListTable\FilterUI;
	use Traits\ListTable\FilterFields;
	use Traits\ListTable\Assets;
	use Traits\ListTable\BulkActions;
	use Traits\ListTable\Overrides;
	use Traits\ListTable\Views;

	/**
	 * Constructor
	 *
	 * @param string $table_id     Table ID
	 * @param array  $table_config Table configuration
	 */
	public function __construct( string $table_id, array $table_config ) {
		parent::__construct( [
			'singular' => $table_config['singular'] ?? 'item',
			'plural'   => $table_config['plural'] ?? 'items',
			'ajax'     => false,
		] );

		// Initialize core trait
		$this->init( $table_id, $table_config );

		// Load assets for this table
		$this->load_assets();
	}

}