<?php
/**
 * Page Addresses
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Request;

/**
 * Where a table's page lives, and what identifies it.
 *
 * One place decides this, because the bug it replaced was eight copies of
 * the same wrong line -- and the copy that was missed was the one in the
 * search form, which is a different shape and so did not look like the
 * others.
 */
trait Urls {

    /**
     * The URL of a table's own admin page.
     *
     * Every URL this library builds used to be hardcoded to admin.php, which
     * is right only for a table with no parent. WordPress puts a submenu page
     * under whatever file its parent slug names — a table under
     * `edit.php?post_type=book` lives at
     * `edit.php?post_type=book&page=my-table`, and asking for
     * `admin.php?page=my-table` gets "Sorry, you are not allowed to access
     * this page." Views, filters, the search form's action, and every
     * post-action redirect were all built that way, so a table anywhere but
     * the top level linked to a page that refuses to load.
     *
     * The rule is core's own: a parent slug naming a .php file is the file;
     * anything else is a plugin page under admin.php. Query arguments in the
     * parent slug are part of the address and are kept.
     *
     * @param array $config Table configuration.
     * @param array $args   Extra query arguments.
     *
     * @return string
     * @since 1.0.0
     */
    public static function page_url( array $config, array $args = [] ): string {
        $parent = (string) ( $config['parent_slug'] ?? '' );
        $file   = 'admin.php';
        $extra  = [];

        if ( '' !== $parent ) {
            $parts = explode( '?', $parent, 2 );

            // A parent that is itself a plugin page — 'my-plugin' — is served
            // by admin.php like any other.
            if ( str_contains( $parts[0], '.php' ) ) {
                $file = $parts[0];

                if ( isset( $parts[1] ) ) {
                    parse_str( $parts[1], $extra );
                }
            }
        }

        return add_query_arg(
            array_merge( $extra, [ 'page' => $config['menu_slug'] ], $args ),
            admin_url( $file )
        );
    }

    /**
     * The query arguments that identify this page.
     *
     * Everything page_url() puts in the query string, read back out of it --
     * `page`, plus whatever the parent menu carries, which for a table under
     * a post type is `post_type`.
     *
     * A GET form replaces the query string outright, so these have to be
     * written into it as hidden inputs or they are gone the moment it
     * submits. Losing post_type is losing the screen: WordPress builds the
     * page hook from $pagenow plus $typenow, and without it answers "Sorry,
     * you are not allowed to access this page."
     *
     * Derived rather than listed a second time, so the form cannot disagree
     * with the links and redirects about what identifies this page.
     *
     * @param array $config Table configuration.
     *
     * @return array<string, string>
     * @since 1.0.0
     */
    public static function page_args( array $config ): array {
        $args  = [];
        $query = (string) wp_parse_url( self::page_url( $config ), PHP_URL_QUERY );

        parse_str( $query, $args );

        return array_map( 'strval', $args );
    }

    private static function get_clean_base_url( array $config ): string {
        $url = self::page_url( $config );

        // Preserve status filter
        if ( Request::filled( 'status' ) ) {
            $url = add_query_arg( 'status', Request::key( 'status' ), $url );
        }

        // Preserve search
        if ( Request::filled( 's' ) ) {
            $url = add_query_arg( 's', Request::text( 's' ), $url );
        }

        // Preserve custom filters
        if ( ! empty( $config['filters'] ) ) {
            foreach ( $config['filters'] as $filter_key => $filter ) {
                if ( Request::filled( $filter_key ) ) {
                    $url = add_query_arg( $filter_key, Request::text( $filter_key ), $url );
                }
            }
        }

        return $url;
    }

    /* =========================================================================
     * BODY CLASSES
     * ========================================================================= */
}
