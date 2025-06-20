<?php
/**
 * Admin Tables Registration Manager
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Tables
 *
 * Manages WordPress admin table registration and display.
 *
 * @since 1.0.0
 */
class Tables {

	use Traits\Table\URL;
	use Traits\Table\Notice;
	use Traits\Table\AJAX;
	use Traits\Table\Column;
	use Traits\Table\Register;
	use Traits\Table\Render;
	use Traits\Table\Action;
	use Traits\Table\Help;
	use Traits\Table\ScreenOptions;
	use Traits\Table\Utils;

	/**
	 * Instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Debug mode status
	 *
	 * @var bool
	 */
	private bool $debug;

	/**
	 * Get instance of this class.
	 *
	 * @return self Instance of this class.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - Sets up actions and filters.
	 */
	private function __construct() {
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Process row actions very early
		add_action( 'admin_init', [ $this, 'process_row_actions' ], 5 );

		// Register admin pages
		add_action( 'init', [ $this, 'register_admin_pages' ] );

		// Screen options and help tabs
		add_action( 'current_screen', [ $this, 'setup_screen' ] );

		// Register assets
		add_action( 'admin_init', [ AssetsManager::class, 'register' ] );

		// AJAX handler for interactive columns
		add_action( 'wp_ajax_table_column_update', [ $this, 'handle_column_update' ] );
	}

}