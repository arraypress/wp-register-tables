<?php
/**
 * Table Instance UI Trait
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use ArrayPress\CustomTables\ListTable;
use ArrayPress\CustomTables\Utils;
use Elementify\Create;

/**
 * UI Trait
 *
 * Provides UI components and rendering functionality for table instances.
 */
trait FilterUI {
	use FilterFields;

	/**
	 * Renders the top filter bar
	 */
	public function top_filters_bar(): void {
		// Skip if no filters defined
		if ( empty( $this->filters ) ) {
			return;
		}

		$base_url           = $this->get_clean_url();
		$has_active_filters = $this->has_active_filters();
		?>
        <div class="wp-filter-bar">
            <div class="wp-filters-left">
				<?php
				// Render quick filters
				$this->render_quick_filters();
				?>

				<?php Create::button_render( __( 'Filter', 'arraypress' ), 'submit', [ 'class' => 'button' ] ); ?>

				<?php if ( $this->has_advanced_filters() ): ?>
					<?php Create::button_render( __( 'More', 'arraypress' ), 'button', [
						'id'    => 'wp-more-filters',
						'class' => 'button button-primary'
					] ); ?>
				<?php endif; ?>

				<?php if ( $has_active_filters ): ?>
					<?php Create::a_render(
						esc_url( $base_url ),
						__( 'Clear', 'arraypress' ),
						[ 'class' => 'button wp-clear-filters' ]
					); ?>
				<?php endif; ?>
            </div>
        </div>

		<?php
		// Display active filters
		$this->render_active_filter_pills();

		// Display advanced filters panel
		$this->render_advanced_filters_panel();
	}

	/**
	 * Check if any advanced filters exist
	 *
	 * @return bool True if advanced filters exist
	 */
	protected function has_advanced_filters(): bool {
		foreach ( $this->filters as $filter ) {
			if ( isset( $filter['placement'] ) && $filter['placement'] === 'advanced' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render quick filters
	 */
	protected function render_quick_filters(): void {
		foreach ( $this->filters as $filter_id => $filter ) {
			// Only process filters with 'quick' placement
			if ( ! isset( $filter['placement'] ) || $filter['placement'] !== 'quick' ) {
				continue;
			}

			// Special handling for date range filter
			if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
				$this->render_date_range_filter( $filter_id, $filter );
				continue;
			}

			// Regular quick filter
			$current_value = $this->get_request_var( $filter_id, '' );
			echo $this->get_filter_input_html( $filter_id, $filter, $current_value );
		}
	}

	/**
	 * Render active filters as pills
	 */
	protected function render_active_filter_pills(): void {
		$search             = $this->get_search();
		$has_active_filters = ! empty( $this->active_filters ) || ! empty( $search );

		if ( ! $has_active_filters ) {
			return;
		}

		?>
        <div class="wp-active-filters">
            <div class="wp-active-filters-heading"><?php _e( 'Active Filters:', 'arraypress' ); ?></div>
            <ul>
				<?php if ( ! empty( $search ) ): ?>
                    <li>
                        <span class="wp-filter-label"><?php _e( 'Search', 'arraypress' ); ?>:</span>
                        <span class="wp-filter-value"><?php echo esc_html( $search ); ?></span>
						<?php Create::a_render(
							esc_url( remove_query_arg( 's', add_query_arg( 'paged', false ) ) ),
							'×',
							[
								'class'      => 'wp-remove-filter',
								'aria-label' => __( 'Remove search filter', 'arraypress' )
							]
						); ?>
                    </li>
				<?php endif; ?>

				<?php foreach ( $this->active_filters as $filter_id => $filter_data ): ?>
                    <li>
                        <span class="wp-filter-label"><?php echo esc_html( $filter_data['label'] ); ?>:</span>
                        <span class="wp-filter-value"><?php echo esc_html( $filter_data['display'] ); ?></span>
						<?php Create::a_render(
							esc_url( remove_query_arg( $filter_id, add_query_arg( 'paged', false ) ) ),
							'×',
							[
								'class'      => 'wp-remove-filter',
								'aria-label' => sprintf( __( 'Remove %s filter', 'arraypress' ), $filter_data['label'] )
							]
						); ?>
                    </li>
				<?php endforeach; ?>
            </ul>
        </div>
		<?php
	}

	/**
	 * Render advanced filters panel
	 */
	protected function render_advanced_filters_panel(): void {
		// Check if there are any advanced filters
		if ( ! $this->has_advanced_filters() ) {
			return;
		}

		?>
        <div id="wp-advanced-filters" class="wp-advanced-filters" style="display: none;">
			<?php Create::h3_render( __( 'Advanced Filters', 'arraypress' ) ); ?>

            <div class="wp-filter-fields">
				<?php
				// Render each advanced filter
				foreach ( $this->filters as $filter_id => $filter ) {
					// Only process filters with 'advanced' placement
					if ( ! isset( $filter['placement'] ) || $filter['placement'] !== 'advanced' ) {
						continue;
					}

					// Skip date range filters - they belong in quick filters
					if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
						continue;
					}

					$title         = $filter['title'] ?? ucfirst( $filter_id );
					$current_value = $this->get_request_var( $filter_id, '' );

					echo '<div class="wp-filter-field">';
					echo '<label for="filter-' . esc_attr( $filter_id ) . '">' . esc_html( $title ) . '</label>';
					echo $this->get_filter_input_html( $filter_id, $filter, $current_value );
					echo '</div>';
				}
				?>
            </div>

            <div class="wp-filter-actions">
				<?php Create::button_render(
					__( 'Apply Filters', 'arraypress' ),
					'submit',
					[ 'class' => 'button button-primary' ]
				); ?>

				<?php Create::a_render(
					esc_url( $this->get_clean_url() ),
					__( 'Reset All', 'arraypress' ),
					[ 'class' => 'button' ]
				); ?>

				<?php Create::button_render(
					__( 'Close', 'arraypress' ),
					'button',
					[ 'class' => 'button wp-close-filters' ]
				); ?>
            </div>
        </div>
		<?php
	}
}