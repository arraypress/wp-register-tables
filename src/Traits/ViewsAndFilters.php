<?php
/**
 * Views And Filters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Manager;
use ArrayPress\RegisterTables\Request;

/**
 * The status links above the table and the controls beside them.
 *
 * Views are the `All (12) | Published (9) | Trash (3)` row, with the counts
 * coming from the table's own callback rather than from counting the rows on
 * this page. Filters are whatever selects the table declared, rendered into
 * core's tablenav so they sit where every other list table puts them.
 */
trait ViewsAndFilters {

/**
     * Get views (status filter tabs)
     *
     * Builds the list of status links shown above the table.
     * Only shows statuses that have items.
     *
     * @return array View key => HTML link pairs.
     * @since 1.0.0
     *
     */
    public function get_views(): array {
        $views   = [];
        $current = $this->status;

        // Build clean base URL — just the page, no search/filters/status
        $base_url = Manager::page_url( $this->config );

        // Ensure counts are loaded
        $this->get_counts();

        // All items view
        $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url( $base_url ),
                empty( $current ) ? 'current' : '',
                esc_html__( 'All', 'arraypress' ),
                esc_html( number_format_i18n( $this->counts['total'] ?? 0 ) )
        );

        // Status-specific views
        foreach ( $this->config['views'] as $key => $label ) {
            if ( $key === 'all' ) {
                continue;
            }

            // Skip if no items with this status
            if ( ! isset( $this->counts[ $key ] ) || $this->counts[ $key ] < 1 ) {
                continue;
            }

            $url = add_query_arg( 'status', $key, $base_url );

            $views[ $key ] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                    esc_url( $url ),
                    $current === $key ? 'current' : '',
                    esc_html( $label ),
                    esc_html( number_format_i18n( $this->counts[ $key ] ) )
            );
        }

        /**
         * Filter the status views
         *
         * @param array  $views  View links.
         * @param string $status Current status filter.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_views_{$this->id}", $views, $this->status );
    }

/**
     * Extra table navigation (filters)
     *
     * Renders dropdown filters above the table (top position only).
     *
     * @param string $which Position: 'top' or 'bottom'.
     *
     * @since 1.0.0
     *
     */
    protected function extra_tablenav( $which ): void {
        if ( $which !== 'top' ) {
            return;
        }

        $has_filters    = ! empty( $this->config['filters'] );
        $has_query_args = $this->has_active_query_args();

        if ( ! $has_filters && ! $has_query_args ) {
            return;
        }

        ?>
        <div class="alignleft actions">
            <?php
            // Render each filter dropdown
            foreach ( $this->config['filters'] as $key => $filter ) {
                $this->render_filter( $key, $filter );
            }

            // Filter submit button (only if dropdowns exist)
            if ( $has_filters ) {
                submit_button( __( 'Filter', 'arraypress' ), '', 'filter_action', false );
            }

            // Clear button — show when any filter or query arg is active
            $any_active = $has_query_args;
            if ( ! $any_active ) {
                foreach ( $this->config['filters'] as $key => $filter ) {
                    if ( Request::filled( $key ) ) {
                        $any_active = true;
                        break;
                    }
                }
            }

            if ( $any_active ) {
                $clear_url = Manager::page_url( $this->config );
                printf(
                        '<a href="%s" class="button">%s</a>',
                        esc_url( $clear_url ),
                        esc_html__( 'Clear', 'arraypress' )
                );
            }
            ?>
        </div>
        <?php
    }

/**
     * Render a single filter dropdown
     *
     * Generates a select element for filtering table data.
     * Supports static options array or dynamic options callback.
     *
     * @param string $key    Filter identifier (used as form field name).
     * @param mixed  $filter Filter configuration array or simple options array.
     *
     * @since 1.0.0
     *
     */
    private function render_filter( string $key, $filter ): void {
        $options = [];
        $label   = '';
        $current = Request::text( $key );

        if ( is_array( $filter ) ) {
            $label = $filter['label'] ?? '';

            // Options are either listed outright or produced by a callback.
            if ( isset( $filter['options'] ) && is_array( $filter['options'] ) ) {
                $options = $filter['options'];
            } elseif ( isset( $filter['options_callback'] ) && is_callable( $filter['options_callback'] ) ) {
                $options = call_user_func( $filter['options_callback'] );
            }
        }

        if ( empty( $options ) ) {
            return;
        }

        ?>
        <select name="<?php echo esc_attr( $key ); ?>" id="filter-by-<?php echo esc_attr( $key ); ?>">
            <?php if ( $label ) : ?>
                <option value=""><?php echo esc_html( $label ); ?></option>
            <?php endif; ?>

            <?php foreach ( $options as $value => $text ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>>
                    <?php echo esc_html( $text ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
