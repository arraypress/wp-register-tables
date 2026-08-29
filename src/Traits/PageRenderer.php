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

use ArrayPress\RegisterTables\InlineEdit;

use ArrayPress\RegisterTables\Request;
use ArrayPress\RegisterTables\Table;

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

        /*
         * The shape of wp-admin/edit.php, in its order:
         *
         *     <div class="wrap">
         *       <h1 class="wp-heading-inline">
         *       <a class="page-title-action">
         *       <span class="subtitle">          only while searching
         *       <hr class="wp-header-end">
         *       ...notices...
         *       views()
         *       <form> search_box() hidden inputs display() </form>
         *     </div>
         *
         * The heading inside the wrap is the part that matters: core's own
         * spacing then applies, and none of it has to be re-stated. Outside,
         * as this drew it before, every bit of that had to be approximated in
         * a stylesheet -- and never quite matched.
         */
        ?>
        <div class="wrap">
            <?php self::render_header( $id, $config ); ?>
            <?php self::render_admin_notices( $id, $config ); ?>

            <?php
            // Above the form, as core has it: the views are links rather than
            // controls, and nothing inside the form submits them.
            $table->views();
            ?>

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

                if ( $config['searchable'] !== false ) {
                    $table->search_box(
                            $config['labels']['search'] ?: __( 'Search', 'arraypress' ),
                            $config['labels']['singular'] ?: 'item'
                    );
                }

                $table->display();

                InlineEdit::render_inline_rows( $id, $config, count( $table->get_columns() ) );
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

        if ( '' === (string) $header_title ) {
            return;
        }

        /*
         * core's own list-table header, and nothing else: an h1 with
         * .wp-heading-inline, the actions after it, and the <hr> that tells
         * WordPress where to put admin notices.
         *
         * This used to draw the privacy-settings header -- a white band, a
         * logo slot, a badge, its own type scale. It looked like a settings
         * page above a list of rows, it needed a growing pile of CSS to sit
         * where core puts things, and every screen using it had a heading
         * that lined up with nothing else in the admin. A list of products
         * should look like a list of posts.
         *
         * The count is not a badge any more either. core writes it as a
         * subtitle after the heading, which is where a reader of any other
         * list already knows to look.
         */
        ?>
        <h1 class="wp-heading-inline"><?php echo esc_html( $header_title ); ?></h1>
        <?php

        self::render_sync_buttons( $config );
        self::render_add_button( $config );

        /*
         * core's subtitle, in core's words and core's place: after the add
         * button, before the rule, and only while something is being searched
         * for. This library drew a banner of its own instead -- its own div,
         * a magnifying glass, a Clear link -- below the heading, which is a
         * component no other list screen in WordPress has.
         *
         * Clearing a search is what the empty search box already does, and
         * what the browser's back button does.
         */
        $search = Request::text( 's' );

        if ( '' !== $search ) {
            echo '<span class="subtitle">';
            printf(
                /* translators: %s: Search query. */
                esc_html__( 'Search results for: %s', 'arraypress' ),
                '<strong>' . esc_html( $search ) . '</strong>'
            );
            echo '</span>';
        }

        // Where admin notices land. Not optional -- without it every notice
        // on the screen renders above the heading instead of below it.
        echo '<hr class="wp-header-end">';
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
}
