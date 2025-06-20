<?php
/**
 * Table Instance UI Trait
 *
 * Provides UI functionality for the Table_Instance class.
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Trait_Table_Instance_UI
 *
 * UI functionality for the Table_Instance class
 */
trait Overrides {

	/**
	 * Extra controls to be displayed between bulk actions and pagination
	 *
	 * @param string $which 'top' or 'bottom'
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			$this->top_filters_bar();
		}

		// Support for custom callback in both new and legacy formats
		if ( isset( $this->table_config['callbacks']['extra_tablenav'] ) && is_callable( $this->table_config['callbacks']['extra_tablenav'] ) ) {
			call_user_func( $this->table_config['callbacks']['extra_tablenav'], $which, $this );
		}

		// Action for adding custom content to the tablenav
		do_action( "{$this->hook_prefix}extra_tablenav", $which, $this );
	}

	/**
	 * Generate the table navigation above or below the table.
	 * Modified to fix layout issues when no items are found or fewer items than per page.
	 *
	 * @param string $which Which part of the table nav we're rendering, top or bottom.
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'], '_wpnonce', false );
		}

		// Get item count
		$has_items = $this->has_items();
		// Check if we need pagination
		$needs_pagination = $has_items && ( $this->get_pagination_arg( 'total_pages' ) > 1 );
		?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php if ( $has_items || $which === 'top' ): ?>
                <div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
                </div>
			<?php endif; ?>

			<?php $this->extra_tablenav( $which ); ?>

			<?php if ( $needs_pagination ): ?>
				<?php $this->pagination( $which ); ?>
			<?php elseif ( $which === 'top' ): ?>
                <div class="tablenav-pages-placeholder"></div>
			<?php endif; ?>

            <br class="clear"/>
        </div>
		<?php
	}

}