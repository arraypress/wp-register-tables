<?php
/**
 * The Empty Table
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

/**
 * What to show when there is nothing to show.
 *
 * Two different nothings, and they need different words. A table with no rows
 * at all is a table waiting to be filled in, and gets an explanation and a
 * button. A search that matched nothing is a table that is working correctly,
 * and telling that user to add their first item is telling them the wrong
 * thing — they want the search cleared.
 */
trait EmptyState {

/**
     * Display the table or the empty state
     *
     * Overrides the parent display() method to render a styled empty state
     * with a call-to-action when the table has zero items total (not filtered).
     * When the table is empty due to search or filters, the standard "no items"
     * message is shown instead.
     *
     * The empty state follows the same pattern used in WooCommerce and WordPress
     * core post type screens when no content exists yet.
     *
     * @since 2.0.0
     */
    public function display(): void {
        // Only show empty state when table is truly empty (no filters/search active)
        if ( $this->should_show_empty_state() ) {
            $this->render_empty_state();

            return;
        }

        parent::display();
    }

/**
     * Determine whether the empty state CTA should be shown
     *
     * Returns true only when the table has zero total items and no
     * search or filter is active. When search/filters produce zero
     * results, the standard no_items() message is more appropriate.
     *
     * @return bool True if the empty state should be rendered.
     * @since 2.0.0
     */
    private function should_show_empty_state(): bool {
        // Must have no items
        if ( ! empty( $this->items ) ) {
            return false;
        }

        // Must have zero total (not just filtered to zero)
        $total = (int) ( $this->counts['total'] ?? 0 );
        if ( $total > 0 ) {
            return false;
        }

        // Must not be searching or filtering
        if ( ! empty( $this->get_search() ) || ! empty( $this->status ) ) {
            return false;
        }

        if ( $this->has_active_filters() ) {
            return false;
        }

        return true;
    }

/**
     * Render the empty state with call-to-action
     *
     * Displays a centered message with optional icon, heading, description,
     * and action button when the table has no items. The button inherits
     * from the add_button configuration if no explicit empty_state config
     * is provided.
     *
     * Configuration via 'empty_state' key:
     * - `icon`        (string)          Dashicon class (default: 'dashicons-welcome-add-post')
     * - `heading`     (string)          Main heading text (auto-generated from labels if empty)
     * - `description` (string)          Subtext below the heading
     * - `button`      (string|callable) Override for add_button behavior
     *
     * @since 2.0.0
     */
    private function render_empty_state(): void {
        $empty_state = $this->config['empty_state'] ?? [];
        $singular    = $this->config['labels']['singular'] ?? 'item';
        $plural      = $this->config['labels']['plural'] ?? 'items';

        // Resolve heading
        $heading = $empty_state['heading'] ?? '';
        if ( empty( $heading ) ) {
            $heading = ! empty( $this->config['labels']['not_found'] )
                    ? $this->config['labels']['not_found']
                    : sprintf(
                    /* translators: %s: plural item label */
                            __( 'No %s yet.', 'arraypress' ),
                            $plural
                    );
        }

        // Resolve description
        $description = $empty_state['description'] ?? '';
        if ( empty( $description ) ) {
            $description = sprintf(
            /* translators: %s: singular item label */
                    __( 'Create your first %s to get started.', 'arraypress' ),
                    $singular
            );
        }

        // Resolve icon
        $icon = $empty_state['icon'] ?? 'dashicons-welcome-add-post';

        // Resolve button — explicit empty_state button overrides add_button
        $button_config = $empty_state['button'] ?? $this->config['add_button'] ?? '';
        $add_new_label = $this->config['labels']['add_new'] ?? '';

        ?>
        <div class="list-table-empty-state">
            <?php if ( ! empty( $icon ) ) : ?>
                <div class="list-table-empty-state__icon">
                    <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                </div>
            <?php endif; ?>

            <h2 class="list-table-empty-state__heading">
                <?php echo esc_html( $heading ); ?>
            </h2>

            <?php if ( ! empty( $description ) ) : ?>
                <p class="list-table-empty-state__description">
                    <?php echo esc_html( $description ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $button_config ) && ! empty( $add_new_label ) ) : ?>
                <div class="list-table-empty-state__action">
                    <?php $this->render_empty_state_button( $button_config, $add_new_label ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

/**
     * Render the empty state action button
     *
     * Supports the same formats as add_button: callable, URL string,
     * or flyout ID string.
     *
     * @param string|callable $button_config Button configuration.
     * @param string          $label         Button text.
     *
     * @since 2.0.0
     */
    private function render_empty_state_button( $button_config, string $label ): void {
        // Callable — full control. Through kses rather than printed raw: it
        // is markup this library did not build, and whatever a filter put
        // into it comes out here.
        if ( is_callable( $button_config ) ) {
            echo wp_kses_post( call_user_func( $button_config ) );

            return;
        }

        // URL string — render as link button
        if ( is_string( $button_config ) && filter_var( $button_config, FILTER_VALIDATE_URL ) ) {
            printf(
                    '<a href="%s" class="button button-primary">%s</a>',
                    esc_url( $button_config ),
                    esc_html( $label )
            );

            return;
        }

        // String — assume flyout ID
        if ( is_string( $button_config ) && function_exists( 'render_flyout_button' ) ) {
            \render_flyout_button( $button_config, [
				'text'  => $label,
				'class' => 'button button-primary',
				'icon'  => 'plus-alt',
            ] );
        }
    }

/**
     * Display message when no items found
     *
     * Shows contextual message based on current filters/search.
     * This is only called when items exist but filters/search returned
     * zero results — the true empty state is handled by render_empty_state().
     *
     * @since 1.0.0
     */
    public function no_items(): void {
        $search = $this->get_search();
        $plural = $this->config['labels']['plural'] ?? 'items';

        // Custom messages from config
        if ( ! empty( $search ) && ! empty( $this->config['labels']['not_found_search'] ) ) {
            $message = $this->config['labels']['not_found_search'];
        } elseif ( ! empty( $this->config['labels']['not_found'] ) ) {
            $message = $this->config['labels']['not_found'];
        } else {
            // Default contextual messages
            if ( ! empty( $search ) ) {
                $message = sprintf(
                /* translators: %s: plural item label */
                        __( 'No %s found for your search.', 'arraypress' ),
                        $plural
                );
            } elseif ( ! empty( $this->status ) ) {
                $status_label = \ArrayPress\StatusBadge\StatusBadge::format_label( $this->status );
                $message      = sprintf(
                /* translators: 1: status label, 2: plural item label */
                        __( 'No %1$s %2$s found.', 'arraypress' ),
                        strtolower( $status_label ),
                        $plural
                );
            } else {
                $message = sprintf(
                /* translators: %s: plural item label */
                        __( 'No %s found.', 'arraypress' ),
                        $plural
                );
            }
        }

        echo esc_html( $message );
    }
}
