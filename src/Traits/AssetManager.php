<?php
/**
 * Asset Loading
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

/**
 * Loading the stylesheet and script, and only where a table is.
 *
 * Admin CSS loaded on every screen is how a library becomes something people
 * remove, so the enqueue is gated on the screen belonging to a registered
 * table. The dynamic styles are the per-table column widths, which cannot be
 * in a static file because they come from configuration.
 */
trait AssetManager {

    /**
     * Enqueue assets callback
     *
     * Hooked to admin_enqueue_scripts. Checks if current page matches
     * a registered table and enqueues styles if so.
     *
     * @param string $hook Current admin page hook suffix.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function enqueue_assets( string $hook ): void {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                self::do_enqueue_assets( $config );
                break;
            }
        }
    }

    /**
     * Actually enqueue the assets
     *
     * Enqueues the main stylesheet and outputs dynamic inline styles
     * for column widths and alignments.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function do_enqueue_assets( array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        // Not the kit's stylesheet. It was loaded for the kit's header, and
        // the header is core's own h1 now; what remains of the kit here is
        // Display::placeholder(), whose markup wants only core's
        // .screen-reader-text. A stylesheet and a script on every list
        // screen, for a class neither of them defines, is a request too many.

        // Enqueue CSS from composer assets package
        arraypress_enqueue_composer_style(
                'list-table-styles',
                __FILE__,
                'css/admin-tables.css'
        );

        // Only when there is something to edit inline. A script that binds
        // nothing is still a request.
        if ( ! empty( $config['bulk_edit'] ) || ! empty( $config['quick_edit'] ) ) {
            arraypress_enqueue_composer_script(
                    'list-table-inline-edit',
                    __FILE__,
                    'js/inline-edit.js',
                    [ 'jquery', 'wp-a11y' ]
            );

            // Every string the script can show, and where to post to.
            // Hard-coded English in the script would be untranslatable, and
            // the four places it can speak are all failure or announcement
            // text -- exactly the moments where a reader needs their own
            // language.
            wp_localize_script(
                    'list-table-inline-edit',
                    'arrayPressInlineEdit',
                    [
						'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
						'error'          => __( 'The change could not be saved.', 'arraypress' ),
						'saved'          => __( 'Changes saved.', 'arraypress' ),
						'removeFromBulk' => __( 'Remove from Bulk Edit', 'arraypress' ),
                    ]
            );
        }

        // Output dynamic styles for this table's configuration
        self::output_dynamic_styles( $config );
    }

    /**
     * Output dynamic inline styles
     *
     * Generates CSS for custom column widths and text alignments
     * based on the column configuration.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function output_dynamic_styles( array $config ): void {
        $styles = '';

        if ( ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $column => $col_config ) {
                if ( ! is_array( $col_config ) ) {
                    continue;
                }

                // Column width
                if ( ! empty( $col_config['width'] ) ) {
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { width: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $col_config['width'] )
                    );
                }

                // Column alignment
                if ( ! empty( $col_config['align'] ) ) {
                    $align  = in_array( $col_config['align'], [ 'left', 'center', 'right' ], true )
                            ? $col_config['align']
                            : 'left';
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { text-align: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $align )
                    );
                }
            }
        }

        if ( ! empty( $styles ) ) {
            wp_add_inline_style( 'list-table-styles', $styles );
        }
    }
}
