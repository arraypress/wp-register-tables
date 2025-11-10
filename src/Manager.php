<?php
/**
 * Admin Tables Registration System
 *
 * A declarative system for registering WordPress admin tables with BerlinDB integration.
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Manages registration and rendering of admin tables.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Registered tables
	 *
	 * @since 1.0.0
	 * @var array<string, array>
	 */
	private static array $tables = [];

    /**
     * Whether styles have been enqueued
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $styles_enqueued = false;

	/**
	 * Register an admin table
	 *
	 * Registers a new admin table with configuration for columns, actions, and data handling.
	 *
	 * @param string $id             Unique table identifier
	 * @param array  $config         {
	 *                               Table configuration array.
	 *
	 * @type string  $namespace      Function namespace/prefix (e.g., 'sugarcart')
	 * @type string  $entity         Entity name singular (e.g., 'customer')
	 * @type string  $entity_plural  Entity name plural (e.g., 'customers')
	 * @type string  $page           Admin page slug
	 * @type string  $flyout         Flyout identifier for edit/view actions
	 * @type array   $columns        Column definitions
	 * @type array   $sortable       Sortable column configurations
	 * @type array   $primary_column Primary column key
	 * @type array   $row_actions    Row action definitions
	 * @type array   $bulk_actions   Bulk action definitions
	 * @type array   $views          View/filter definitions
	 * @type array   $filters        Additional filter dropdowns
	 * @type array   $data           Data source callbacks
	 * @type array   $messages       Custom messages
	 * @type int     $per_page       Items per page (default: 30)
	 * @type bool    $searchable     Whether to show search box (default: true)
	 * @type array   $capabilities   Permission requirements
	 *                               }
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public static function register( string $id, array $config ): void {
		$defaults = [
			'namespace'      => '',
			'entity'         => '',
			'entity_plural'  => '',
			'page'           => '',
			'flyout'         => '',
			'columns'        => [],
			'sortable'       => [],
			'primary_column' => '',
			'row_actions'    => [],
			'bulk_actions'   => [],
			'views'          => [],
			'filters'        => [],
			'data'           => [],
			'messages'       => [],
			'per_page'       => 30,
			'searchable'     => true,
			'capabilities'   => []
		];

		$config = wp_parse_args( $config, $defaults );

		// Auto-generate entity_plural if not provided
		if ( empty( $config['entity_plural'] ) && ! empty( $config['entity'] ) ) {
			$config['entity_plural'] = $config['entity'] . 's';
		}

		// Store configuration
		self::$tables[ $id ] = $config;

		// Hook into admin page rendering
		if ( ! empty( $config['page'] ) ) {
			add_action( 'load-toplevel_page_' . $config['page'], [ __CLASS__, 'load_table' ] );
			add_action( 'load-admin_page_' . $config['page'], [ __CLASS__, 'load_table' ] );
		}
	}

	/**
	 * Load table for current admin page
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public static function load_table(): void {
		$page = $_GET['page'] ?? '';

		foreach ( self::$tables as $id => $config ) {
			if ( $config['page'] === $page ) {
				self::render_table( $id );
				break;
			}
		}
	}

	/**
	 * Render a registered table
	 *
	 * @param string $id Table identifier
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public static function render_table( string $id ): void {
		if ( ! isset( self::$tables[ $id ] ) ) {
			return;
		}

		$config = self::$tables[ $id ];
		$table  = new Table( $id, $config );

		// Process bulk actions if needed
		$table->process_bulk_action();

		// Prepare items
		$table->prepare_items();

        self::maybe_enqueue_styles();

        // Output the table
		add_action( 'admin_notices', function () use ( $table, $config ) {
			?>
            <div class="wrap">
                <h1 class="wp-heading-inline">
					<?php echo esc_html( $config['title'] ?? ucfirst( $config['entity_plural'] ) ); ?>
                </h1>

				<?php if ( ! empty( $config['flyout'] ) && ! empty( $config['add_new_label'] ) ) : ?>
                    <a href="#"
                       class="page-title-action"
                       data-flyout-trigger="<?php echo esc_attr( $config['flyout'] ); ?>"
                       data-flyout-action="load">
						<?php echo esc_html( $config['add_new_label'] ); ?>
                    </a>
				<?php endif; ?>

                <hr class="wp-header-end">

                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr( $config['page'] ); ?>">

					<?php
					$table->views();

					if ( $config['searchable'] ) {
						$table->search_box(
							$config['messages']['search_label'] ?? __( 'Search', 'arraypress' ),
							$config['entity']
						);
					}

					$table->display();
					?>
                </form>
            </div>
			<?php
		} );
	}

	/**
	 * Get a registered table configuration
	 *
	 * @param string $id Table identifier
	 *
	 * @return array|null Table configuration or null if not found
	 * @since 1.0.0
	 *
	 */
	public static function get_table( string $id ): ?array {
		return self::$tables[ $id ] ?? null;
	}

	/**
	 * Remove a registered table
	 *
	 * @param string $id Table identifier
	 *
	 * @return bool True if removed, false if not found
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
     * Maybe enqueue styles
     *
     * Enqueues table styles if not already enqueued.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function maybe_enqueue_styles(): void {
        if ( self::$styles_enqueued ) {
            return;
        }

        add_action( 'admin_head', function() {
            ?>
            <style>
                .arraypress-table-wrap .badge {
                    display: inline-block;
                    padding: 3px 8px;
                    font-size: 12px;
                    line-height: 1.2;
                    font-weight: 600;
                    border-radius: 3px;
                    background: #dcdcde;
                    color: #2c3338;
                }
                .arraypress-table-wrap .badge-success {
                    background: #d4f4dd;
                    color: #00a32a;
                }
                .arraypress-table-wrap .badge-warning {
                    background: #fcf0e4;
                    color: #996800;
                }
                .arraypress-table-wrap .badge-error {
                    background: #facfd2;
                    color: #d63638;
                }
                .arraypress-table-wrap .badge-info {
                    background: #e5f5fa;
                    color: #0073aa;
                }
                .arraypress-table-wrap .badge-default {
                    background: #f0f0f1;
                    color: #50575e;
                }
                .arraypress-table-wrap .price {
                    font-weight: 600;
                    color: #00a32a;
                }
                .arraypress-table-wrap .text-muted {
                    color: #a7aaad;
                }
                .arraypress-table-wrap .column-cb {
                    width: 2.2em;
                }
                .arraypress-table-wrap .delete-link {
                    color: #b32d2e;
                }
                .arraypress-table-wrap .delete-link:hover {
                    color: #dc3545;
                }
            </style>
            <?php
        } );

        self::$styles_enqueued = true;
    }

}