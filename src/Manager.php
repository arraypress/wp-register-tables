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
     * @since 1.0.0
     *
     * @param string $id     Unique table identifier
     * @param array  $config {
     *     Table configuration array.
     *
     *     @type array    $labels {
     *         Labels for display purposes.
     *
     *         @type string $singular      Singular name (e.g., 'order')
     *         @type string $plural        Plural name (e.g., 'orders')
     *         @type string $title         Page title (e.g., 'Orders')
     *         @type string $add_new       Add new button label (e.g., 'Add New Order')
     *         @type string $search        Search button label (e.g., 'Search Orders')
     *         @type string $not_found     No items message (e.g., 'No orders found.')
     *         @type string $not_found_search No items found in search (e.g., 'No orders found for your search.')
     *     }
     *     @type array    $callbacks {
     *         Data operation callbacks.
     *
     *         @type callable $get_items   Callback to get items. Receives query args.
     *         @type callable $get_counts  Callback to get status counts.
     *         @type callable $delete      Callback to delete single item. Receives ID.
     *         @type callable $update      Callback to update single item. Receives ID and data array.
     *     }
     *     @type string   $page           Admin page slug
     *     @type string   $flyout         Flyout identifier for edit/view actions
     *     @type array    $columns        Column definitions
     *     @type array    $sortable       Sortable column configurations
     *     @type string   $primary_column Primary column key
     *     @type array    $row_actions    Row action definitions
     *     @type array    $bulk_actions   Bulk action definitions
     *     @type array    $views          View/filter definitions
     *     @type array    $filters        Additional filter dropdowns
     *     @type int      $per_page       Items per page (default: 30)
     *     @type bool     $searchable     Whether to show search box (default: true)
     *     @type array    $capabilities   Permission requirements
     *     @type bool     $show_count     Show item count in title (default: false)
     * }
     *
     * @return void
     */
    public static function register( string $id, array $config ): void {
        $defaults = [
                'labels'         => [],
                'callbacks'      => [],
                'page'           => '',
                'flyout'         => '',
                'columns'        => [],
                'sortable'       => [],
                'primary_column' => '',
                'row_actions'    => [],
                'bulk_actions'   => [],
                'views'          => [],
                'filters'        => [],
                'per_page'       => 30,
                'searchable'     => true,
                'capabilities'   => [],
                'show_count'     => false
        ];

        $config = wp_parse_args( $config, $defaults );

        // Parse labels with defaults
        $config['labels'] = wp_parse_args( $config['labels'], [
                'singular'        => '',
                'plural'          => '',
                'title'           => '',
                'add_new'         => '',
                'search'          => '',
                'not_found'       => '',
                'not_found_search'=> ''
        ] );

        // Parse callbacks with defaults
        $config['callbacks'] = wp_parse_args( $config['callbacks'], [
                'get_items'  => null,
                'get_counts' => null,
                'delete'     => null,
                'update'     => null
        ] );

        // Auto-generate missing labels
        if ( empty( $config['labels']['title'] ) && ! empty( $config['labels']['plural'] ) ) {
            $config['labels']['title'] = ucfirst( $config['labels']['plural'] );
        }

        if ( empty( $config['labels']['add_new'] ) && ! empty( $config['labels']['singular'] ) ) {
            $config['labels']['add_new'] = sprintf(
                    __( 'Add New %s', 'arraypress' ),
                    ucfirst( $config['labels']['singular'] )
            );
        }

        if ( empty( $config['labels']['search'] ) && ! empty( $config['labels']['plural'] ) ) {
            $config['labels']['search'] = sprintf(
                    __( 'Search %s', 'arraypress' ),
                    $config['labels']['plural']
            );
        }

        // Auto-detect primary column if not set
        if ( empty( $config['primary_column'] ) && ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $key => $column ) {
                if ( is_array( $column ) && ! empty( $column['primary'] ) ) {
                    $config['primary_column'] = $key;
                    break;
                }
            }
            // If still empty, use first non-cb column
            if ( empty( $config['primary_column'] ) ) {
                foreach ( $config['columns'] as $key => $column ) {
                    if ( $key !== 'cb' ) {
                        $config['primary_column'] = $key;
                        break;
                    }
                }
            }
        }

        // Store configuration
        self::$tables[ $id ] = $config;
    }

    /**
     * Render a registered table
     *
     * Outputs the complete admin page with the table.
     *
     * @since 1.0.0
     *
     * @param string $id Table identifier
     *
     * @return void
     */
    public static function render_table( string $id ): void {
        if ( ! isset( self::$tables[ $id ] ) ) {
            return;
        }

        $config = self::$tables[ $id ];

        // Check capabilities if set
        if ( ! empty( $config['capabilities']['view'] ) ) {
            if ( ! current_user_can( $config['capabilities']['view'] ) ) {
                wp_die( __( 'Sorry, you are not allowed to access this page.', 'arraypress' ) );
            }
        }

        // Enqueue styles if needed
        self::maybe_enqueue_styles();

        // Create table instance
        $table = new Table( $id, $config );

        // Process bulk actions if needed
        $table->process_bulk_action();

        // Prepare items
        $table->prepare_items();

        // Get total count for title if needed
        $total_count = '';
        if ( $config['show_count'] ) {
            $counts = $table->get_counts();
            $total = $counts['total'] ?? 0;
            if ( $total > 0 ) {
                $total_count = sprintf( ' <span class="count">(%s)</span>', number_format_i18n( $total ) );
            }
        }

        // Render the page
        ?>
        <div class="wrap arraypress-table-wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html( $config['labels']['title'] ); ?><?php echo $total_count; ?>
            </h1>

            <?php if ( ! empty( $config['labels']['add_new'] ) ) : ?>
                <?php if ( ! empty( $config['flyout'] ) ) : ?>
                    <a href="#"
                       class="page-title-action"
                       data-flyout-trigger="<?php echo esc_attr( $config['flyout'] ); ?>"
                       data-flyout-action="load">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: text-top;"></span>
                        <?php echo esc_html( $config['labels']['add_new'] ); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'action', 'add', $_SERVER['REQUEST_URI'] ) ); ?>"
                       class="page-title-action">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: text-top;"></span>
                        <?php echo esc_html( $config['labels']['add_new'] ); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php
            /**
             * Fires before the table is rendered
             *
             * @since 1.0.0
             *
             * @param string $id     Table identifier
             * @param array  $config Table configuration
             */
            do_action( 'arraypress_before_render_table', $id, $config );

            /**
             * Fires before a specific table is rendered
             *
             * @since 1.0.0
             *
             * @param array $config Table configuration
             */
            do_action( "arraypress_before_render_table_{$id}", $config );
            ?>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? $config['page'] ); ?>">

                <?php
                // Preserve other query args
                foreach ( $_GET as $key => $value ) {
                    if ( ! in_array( $key, ['page', 'paged', '_wpnonce', '_wp_http_referer'], true ) ) {
                        if ( is_array( $value ) ) {
                            foreach ( $value as $v ) {
                                printf( '<input type="hidden" name="%s[]" value="%s">', esc_attr( $key ), esc_attr( $v ) );
                            }
                        } else {
                            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $key ), esc_attr( $value ) );
                        }
                    }
                }

                $table->views();

                if ( $config['searchable'] !== false ) {
                    $table->search_box(
                            $config['labels']['search'] ?: __( 'Search', 'arraypress' ),
                            $config['labels']['singular'] ?: 'item'
                    );
                }

                $table->display();
                ?>
            </form>

            <?php
            /**
             * Fires after the table is rendered
             *
             * @since 1.0.0
             *
             * @param string $id     Table identifier
             * @param array  $config Table configuration
             */
            do_action( 'arraypress_after_render_table', $id, $config );

            /**
             * Fires after a specific table is rendered
             *
             * @since 1.0.0
             *
             * @param array $config Table configuration
             */
            do_action( "arraypress_after_render_table_{$id}", $config );
            ?>
        </div>
        <?php
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

}