<?php
/**
 * Page Rendering
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Request;
use ArrayPress\RegisterTables\Table;
use ArrayPress\FieldKit\Support\PageHeader;

/**
 * Drawing the page around the list table.
 *
 * The heading, the badge, the add button, the search banner — everything core
 * does not draw for you, in core's own markup so that a table from this
 * library is indistinguishable from one WordPress ships.
 */
trait PageRenderer {

    /**
     * Render a registered table
     *
     * Outputs the complete admin page including header, notices, search banner,
     * views, search box, and the table itself. Called automatically by the
     * menu page callback, or can be called manually via get_table_renderer().
     *
     * @param string $id Table identifier (as passed to register()).
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

        // Check view capability
        if ( ! empty( $config['capabilities']['view'] ) ) {
            if ( ! current_user_can( $config['capabilities']['view'] ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'arraypress' ) );
            }
        }

        // Create and prepare table instance
        $table = new Table( $id, $config );
        $table->process_bulk_action();
        $table->prepare_items();

        // Render header outside .wrap (EDD pattern).
        //
        // No count beside the heading: core puts none on any list table, and
        // the number is already in the views above the table and in the
        // pagination beside it. A third copy in a different shape is the one
        // part of the screen that does not look like WordPress.
        self::render_header( $id, $config );

        // Start WordPress wrap
        ?>
        <div class="wrap">
            <?php self::render_admin_notices( $id, $config ); ?>
            <?php self::render_search_results_banner( $config ); ?>

            <?php

            /**
             * Fires before a specific table is rendered
             *
             * @param array $config Table configuration.
             *
             * @since 1.0.0
             *
             */
            do_action( "arraypress_before_render_table_{$id}", $config );
            ?>

            <form method="get">
                <?php
                /*
                 * Every argument the page's own URL carries.
                 *
                 * A GET form replaces the query string outright, so anything
                 * not written here as a hidden input is simply gone when the
                 * form submits -- and for a table hanging off
                 * `edit.php?post_type=X`, losing post_type is losing the
                 * screen. WordPress builds the page hook from $pagenow plus
                 * $typenow; with no post_type it looks for the page under
                 * plain `edit.php`, finds nothing, and answers "Sorry, you
                 * are not allowed to access this page." Which is what every
                 * bulk action and every filter did.
                 *
                 * Read back out of page_url() rather than listed again here,
                 * so the form cannot disagree with the links and redirects
                 * the rest of the library builds about what identifies this
                 * page.
                 */
                foreach ( self::page_args( $config ) as $key => $value ) {
                    printf(
                            '<input type="hidden" name="%s" value="%s">',
                            esc_attr( (string) $key ),
                            esc_attr( (string) $value )
                    );
                }

                /*
                 * The status views are links rather than controls, so the
                 * one being viewed has to be carried by hand. So is the
                 * sort, which is otherwise lost the moment anything is
                 * searched or filtered.
                 *
                 * The filters are deliberately not here: each renders its
                 * own select inside this form, and a hidden input beside it
                 * would post the same key twice.
                 */
                foreach ( [ 'status', 'orderby', 'order' ] as $key ) {
                    if ( Request::filled( $key ) ) {
                        printf(
                                '<input type="hidden" name="%s" value="%s">',
                                esc_attr( $key ),
                                esc_attr( Request::key( $key ) )
                        );
                    }
                }

                // Render table components
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
             * Fires after a specific table is rendered
             *
             * @param array $config Table configuration.
             *
             * @since 1.0.0
             *
             */
            do_action( "arraypress_after_render_table_{$id}", $config );
            ?>
        </div>
        <?php
    }

    /**
     * Render the modern header
     *
     * Outputs the EDD-style header with optional logo, title, and add button.
     * Placed outside .wrap for proper WordPress admin styling. Title is only
     * rendered if header_title is set or labels title is non-empty.
     *
     * @param string $id          Table identifier.
     * @param array  $config      Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function render_header( string $id, array $config ): void {
        $header_title = ! empty( $config['header_title'] )
                ? $config['header_title']
                : ( $config['labels']['title'] ?? '' );

        // The kit's header, which is core's own privacy-settings header. This
        // library used to draw its own — a different height, a different type
        // scale, and a slash between the logo and the title — so a plugin
        // with a settings page and a list table had two headers that were
        // nearly but not quite the same. There is one now.
        //
        // The count rides in the badge slot, which is what it was already
        // trying to be inside the title, and the buttons in the actions slot
        // on the right — where a date range and a refresh control go on a
        // reports screen.
        ob_start();
        self::render_sync_buttons( $config );
        self::render_add_button( $config );
        $actions = (string) ob_get_clean();

        $header = PageHeader::render(
                [
					'title'         => $header_title,
					'logo'          => (string) ( $config['logo'] ?? '' ),
					'logo_position' => (string) ( $config['logo_position'] ?? 'beside' ),

					// A list table's heading and its Add button sit side by side at
					// the left, as core's own do and as EDD's do. Centred is the
					// settings-page shape and looks wrong with a list under it.
					'align'         => (string) ( $config['align'] ?? 'left' ),
					'badge'         => self::header_badge( $config ),
					'actions'       => $actions,
                ]
        );

        echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the kit escapes as it builds.
    }

    /**
     * What goes in the header's badge slot.
     *
     * A consumer's own badge if there is one, otherwise the item count. The
     * count reads as a badge — "People (8)" — which is what it was already
     * trying to be while it sat inside the title.
     *
     * @param array  $config      Table configuration.
     *
     * @return mixed
     */
    private static function header_badge( array $config ) {
        $badge = $config['header_badge'] ?? '';

        // A callable was one of the three shapes this accepted. The kit's
        // badge takes a string or an array, so the callable is resolved here
        // and its answer used — which does mean one returning markup now has
        // that markup escaped rather than printed. Nothing in these
        // repositories passes one, and a badge is a word.
        if ( ! is_string( $badge ) && is_callable( $badge ) ) {
            $badge = call_user_func( $badge );
        }

        return $badge;
    }

    /**
     * Render sync trigger buttons
     *
     * Outputs sync trigger buttons if the wp-inline-sync library is available
     * and one or more sync IDs are configured. The sync library handles all
     * behavior — this method only renders the buttons.
     *
     * Supports a single sync ID string or an array of sync IDs for screens
     * that have multiple sync operations.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function render_sync_buttons( array $config ): void {
        if ( empty( $config['sync'] ) || ! function_exists( 'render_sync_button' ) ) {
            return;
        }

        $sync_ids = (array) $config['sync'];

        foreach ( $sync_ids as $sync_id ) {
            \render_sync_button( $sync_id );
        }
    }

    /**
     * Render the add new button
     *
     * Outputs the "Add New" button using the configured method. The add_button
     * config supports three formats:
     * 1. Callable — full control over button output
     * 2. URL string — renders a link button
     * 3. String — assumed to be a flyout ID
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function render_add_button( array $config ): void {
        if ( empty( $config['labels']['add_new'] ) || empty( $config['add_button'] ) ) {
            return;
        }

        $add_button = $config['add_button'];

        // Callable — full control over output
        if ( is_callable( $add_button ) ) {
            // A consumer callback returning markup. Through kses rather
            // than printed raw: it is markup this library did not build, and
            // whatever a filter put into it comes out here.
            echo wp_kses_post( call_user_func( $add_button ) );

            return;
        }

        // URL string — render as link
        if ( is_string( $add_button ) && filter_var( $add_button, FILTER_VALIDATE_URL ) ) {
            printf(
                    '<a href="%s" class="page-title-action">%s</a>',
                    esc_url( $add_button ),
                    esc_html( $config['labels']['add_new'] )
            );

            return;
        }

        // String — assume flyout ID
        if ( is_string( $add_button ) && function_exists( 'render_flyout_button' ) ) {
            // No icon: core's own Add New is a plain button, everywhere in
            // the admin, and a plus beside it is the one thing on the screen
            // that does not look like WordPress.
            \render_flyout_button( $add_button, [
				'text'  => $config['labels']['add_new'],
				'class' => 'page-title-action',
            ] );
        }
    }

    /**
     * Render search results banner
     *
     * Shows a banner when search results are being displayed,
     * with a link to clear the search.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function render_search_results_banner( array $config ): void {
        $search = Request::text( 's' );

        if ( empty( $search ) ) {
            return;
        }

        $clear_url = remove_query_arg( 's', self::get_clean_base_url( $config ) );
        $plural    = $config['labels']['plural'] ?? 'items';

        ?>
        <div class="list-table-search-banner">
			<span class="list-table-search-banner__text">
				<span class="dashicons dashicons-search"></span>
				<?php
                printf(
                /* translators: 1: search term, 2: plural item name */
                        esc_html__( 'Search results for %1$s in %2$s', 'arraypress' ),
                        '<strong>"' . esc_html( $search ) . '"</strong>',
                        esc_html( $plural )
                );
                ?>
			</span>
            <a href="<?php echo esc_url( $clear_url ); ?>" class="list-table-search-banner__clear">
                <?php esc_html_e( 'Clear search', 'arraypress' ); ?>
            </a>
        </div>
        <?php
    }
}
