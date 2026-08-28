<?php
/**
 * Column Rendering
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Row;

use ArrayPress\RegisterTables\Columns;
use ArrayPress\RegisterTables\Manager;

/**
 * Turning one value from one row into one cell.
 *
 * A column is drawn from a callback if the table gave one, from a structured
 * definition if it gave that, and otherwise from what the column's own name
 * suggests it is — `email` is an email, `created_at` is a date. Guessing is
 * convenient and quietly fragile, which is why the detection rules are pinned
 * down by tests rather than left to be discovered.
 *
 * Everything here escapes. A list table prints hundreds of cells straight out
 * of a database row, and one that does not escape is one XSS per column.
 */
trait ColumnRenderer {

    /**
     * Default column renderer
     *
     * Renders column content when no specific column_* method exists.
     * Checks for configured callbacks, then falls back to automatic
     * formatting based on column name patterns.
     *
     * @param object $item        Data object (typically a BerlinDB Row).
     * @param string $column_name Column identifier.
     *
     * @return string Rendered column HTML content.
     * @since 1.0.0
     *
     */
    public function column_default( $item, $column_name ) {
        // Check for column-specific config
        if ( isset( $this->config['columns'][ $column_name ] ) ) {
            $column_config = $this->config['columns'][ $column_name ];

            if ( is_array( $column_config ) ) {
                // Structured format with before/title/after/link
                if ( isset( $column_config['title'] ) ) {
                    return $this->render_structured_column( $column_config, $item );
                }

                // Legacy callback format (still supported)
                if ( isset( $column_config['callback'] ) && is_callable( $column_config['callback'] ) ) {
                    return call_user_func( $column_config['callback'], $item );
                }

                // Value resolver — transform the value before auto-formatting
                if ( isset( $column_config['value'] ) && is_callable( $column_config['value'] ) ) {
                    $value = call_user_func( $column_config['value'], $item );

                    return $this->auto_format_column( $column_name, $value, $item );
                }
            }
        }

        // A getter, a property or an array key — whichever the row has.
        // method_exists() and property_exists() both throw a TypeError on an
        // array, so a column with no callback used to fatal for any table
        // whose query returned arrays.
        if ( Row::has( $item, $column_name ) ) {
            return $this->auto_format_column( $column_name, Row::get( $item, $column_name ), $item );
        }

        // No value found
        return Columns::render_empty();
    }

    /**
     * Render a structured column with before/title/after/link support
     *
     * Allows columns to be defined with separate components:
     * - `before`: Content before the title (e.g., avatar)
     * - `title`: The main clickable title text
     * - `after`: Content after the title
     * - `link`: How to link the title ('view_flyout', 'edit_flyout', callable, or URL)
     *
     * @param array  $config Column configuration.
     * @param object $item   Data object.
     *
     * @return string Rendered column HTML.
     * @since 1.0.0
     *
     */
    private function render_structured_column( array $config, $item ): string {
        $output = '';

        // Render "before" content (e.g., avatar)
        //
        // Wrapped, because an avatar and a title emitted as bare siblings
        // stack: the title is a block-level <strong> in most themes, and a
        // A 40px image followed by a block-level title puts the name
        // underneath the picture rather than beside it. Core floats the
        // equivalent -- `.column-username img` in list-tables.css -- and the
        // stylesheet here does the same for a primary column's image, so a
        // consumer supplying one does not have to write the CSS.
        if ( isset( $config['before'] ) ) {
            $before = is_callable( $config['before'] )
                    ? call_user_func( $config['before'], $item )
                    : $config['before'];

            if ( '' !== (string) $before ) {
                /*
                 * Unwrapped, and followed by a space, which is exactly what
                 * users.php does:
                 *
                 *     $avatar = get_avatar( $user_object->ID, 32 );
                 *     ... "$avatar $edit"
                 *
                 * A span around it took the float, leaving the image inside
                 * it floated a second time by the rule meant for the image,
                 * and carried a border radius and a block display that no
                 * avatar in the admin has. Bare, one rule applies, and it is
                 * core's own.
                 */
                $output .= $before . ' ';
            }
        }

        // Render title (with optional link)
        $title = '';
        if ( isset( $config['title'] ) ) {
            if ( is_callable( $config['title'] ) ) {
                $title = call_user_func( $config['title'], $item );
            } else {
                $title = $config['title'];
            }
        }

        if ( ! empty( $title ) ) {
            $title_html = $this->render_column_title_link( $config, $item, $title );
            $output     .= $title_html;
        }

        // Render "after" content
        if ( isset( $config['after'] ) ) {
            if ( is_callable( $config['after'] ) ) {
                $output .= call_user_func( $config['after'], $item );
            } else {
                $output .= $config['after'];
            }
        }

        return $output;
    }

    /**
     * Render the title with optional link
     *
     * Handles different link types including flyouts which need special attributes.
     *
     * @param array  $config Column configuration.
     * @param object $item   Data object.
     * @param string $title  Title text.
     *
     * @return string Title HTML with link if configured.
     * @since 1.0.0
     *
     */
    private function render_column_title_link( array $config, $item, string $title ): string {
        if ( ! isset( $config['link'] ) ) {
            return '<strong>' . esc_html( $title ) . '</strong>';
        }

        $link    = $config['link'];
        $item_id = $this->get_item_id( $item );

        // View flyout link
        if ( $link === 'view_flyout' && ! empty( $this->config['flyouts']['view'] ) ) {
            return $this->build_flyout_title_link( $this->config['flyouts']['view'], $item_id, $title );
        }

        // Edit flyout link
        if ( $link === 'edit_flyout' && ! empty( $this->config['flyouts']['edit'] ) ) {
            return $this->build_flyout_title_link( $this->config['flyouts']['edit'], $item_id, $title );
        }

        // Callable returns URL
        if ( is_callable( $link ) ) {
            $url = call_user_func( $link, $item );
            if ( $url ) {
                return sprintf(
                        '<a href="%s"><strong>%s</strong></a>',
                        esc_url( $url ),
                        esc_html( $title )
                );
            }
        }

        // Direct URL string
        if ( is_string( $link ) && ! empty( $link ) ) {
            return sprintf(
                    '<a href="%s"><strong>%s</strong></a>',
                    esc_url( $link ),
                    esc_html( $title )
            );
        }

        return '<strong>' . esc_html( $title ) . '</strong>';
    }

    /**
     * Build a flyout trigger link for column title
     *
     * Uses get_flyout_link() if available to ensure proper attributes.
     *
     * @param string $flyout_id Full flyout identifier (e.g., 'ate_view_customer').
     * @param int    $item_id   Item ID.
     * @param string $title     Title text.
     *
     * @return string Flyout link HTML.
     * @since 1.0.0
     *
     */
    private function build_flyout_title_link( string $flyout_id, int $item_id, string $title ): string {
        // Use the flyout library's link function if available
        if ( function_exists( 'get_flyout_link' ) ) {
            $link = \get_flyout_link( $flyout_id, [
				'id'   => $item_id,
				'text' => $title,
            ] );

            if ( ! empty( $link ) ) {
                // Wrap the text in <strong> tags
                return preg_replace(
                        '/>([^<]+)<\/a>$/',
                        '><strong>$1</strong></a>',
                        $link
                );
            }
        }

        // Fallback: plain text (won't trigger flyout properly but shows something)
        return '<strong>' . esc_html( $title ) . '</strong>';
    }

    /**
     * Auto-format column value based on naming patterns
     *
     * Passes the column config directly to the Columns utility, which
     * extracts what it needs (styles, size, decimals, etc.).
     *
     * @param string $column_name Column identifier.
     * @param mixed  $value       Raw column value.
     * @param object $item        Data object for context.
     *
     * @return string Formatted HTML content.
     * @since 1.0.0
     */
    private function auto_format_column( string $column_name, $value, $item ): string {
        $config = $this->config['columns'][ $column_name ] ?? [];
        $config = is_array( $config ) ? $config : [];

        // Build filter URL if configured
        if ( ! empty( $config['filter'] ) && is_object( $value ) ) {
            $filter_key = $config['filter'];
            $filter_id  = method_exists( $value, 'get_id' ) ? $value->get_id() : null;

            if ( $filter_id ) {
                $config['_filter_url'] = Manager::page_url( $this->config, [ $filter_key => $filter_id ] );
            }
        }

        return Columns::auto_format(
                $column_name,
                $value,
                $item,
                $config
        );
    }

    /**
     * Checkbox column renderer
     *
     * Renders the checkbox for bulk action selection.
     *
     * @param object $item Data object.
     *
     * @return string Checkbox input HTML.
     * @since 1.0.0
     *
     */
    public function column_cb( $item ): string {
        $id = $this->get_item_id( $item );

        return sprintf(
                '<input type="checkbox" name="%s[]" value="%s" />',
                esc_attr( $this->config['labels']['plural'] ?? 'items' ),
                esc_attr( $id )
        );
    }
}
