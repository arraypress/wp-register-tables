<?php
/**
 * Table Render Trait
 *
 * Provides rendering functionality for the Tables class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\Table;

// Exit if accessed directly
use ArrayPress\CustomTables\ListTable;

defined( 'ABSPATH' ) || exit;

/**
 * Trait TableRenderTrait
 *
 * Rendering functionality for the Tables class
 */
trait Render {

	/**
	 * Render the table page.
	 *
	 * @param string $table_id ID of the table to render
	 */
	public function render_table_page( string $table_id ): void {
		// Check if table config exists
		if ( ! isset( $this->registered_tables[ $table_id ] ) ) {
			echo '<div class="wrap"><p>Table configuration not found.</p></div>';

			return;
		}

		$table_config = $this->registered_tables[ $table_id ];
		$hook_prefix  = $table_config['hook_prefix'] ?? 'table_' . $table_id . '_';

		// Use custom render callback if provided
		if ( ! empty( $table_config['callbacks']['render'] ) && is_callable( $table_config['callbacks']['render'] ) ) {
			call_user_func( $table_config['callbacks']['render'], $table_config );

			return;
		}

		// Permission check based on capability
		$capability = $table_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				sprintf( __( 'You do not have permission to manage %s.', 'arraypress' ), $table_config['plural'] ),
				__( 'Error', 'arraypress' ),
				[ 'response' => 403 ]
			);
		}

		// Create table instance
		$table_instance = new ListTable( $table_id, $table_config );
		$table_instance->prepare_items();

		// Action before the table
		do_action( "{$hook_prefix}page_top", $table_id, $table_config );

		// Render modern header outside the wrap div
		$this->render_table_header( $table_id, $table_config, $hook_prefix );

		// Display the table
		?>
        <div class="wrap">
			<?php
			// Display admin notices AFTER the header but INSIDE the wrap
			$this->display_admin_notices();

			// Action before the form
			do_action( "{$hook_prefix}before_form", $table_id, $table_config );
			?>

            <!-- Use method="get" to match WordPress core behavior -->
            <form id="<?php echo esc_attr( $table_id ); ?>-filter" method="get">
				<?php
				// Add necessary hidden fields to maintain the current page
				$this->add_hidden_fields();

				// Action before the table content
				do_action( "{$hook_prefix}before_table", $table_id, $table_config, $table_instance );

				// Search box
				$table_instance->search_box(
					sprintf( __( 'Search %s', 'arraypress' ), $table_config['plural'] ),
					$table_id
				);

				// Display the table views and content
				$table_instance->views();
				$table_instance->display();

				// Action after the table content
				do_action( "{$hook_prefix}after_table", $table_id, $table_config, $table_instance );
				?>
            </form>

			<?php
			// Action after the form
			do_action( "{$hook_prefix}after_form", $table_id, $table_config );
			?>
        </div>
		<?php

		// Action after the table
		do_action( "{$hook_prefix}page_bottom", $table_id, $table_config );
	}

	/**
	 * Render the modern table header with enhanced URL handling
	 *
	 * @param string $table_id     The table ID
	 * @param array  $table_config The table configuration
	 * @param string $hook_prefix  Action hook prefix
	 */
	private function render_table_header( string $table_id, array $table_config, string $hook_prefix ): void {
		?>
        <div class="wp-header-modern">
            <div class="wp-header-wrapper">
				<?php if ( ! empty( $table_config['header_logo'] ) ) : ?>
                    <span class="wp-header-branding">
                    <img class="wp-header-logo" alt="" src="<?php echo esc_url( $table_config['header_logo'] ); ?>">
                </span>
				<?php endif; ?>

                <span class="wp-header-title-wrap">
                <?php if ( ! empty( $table_config['header_logo'] ) ) : ?>
                    <span class="wp-header-separator">/</span>
                <?php endif; ?>
                <h1 class="wp-header-title"><?php echo esc_html( $table_config['title'] ); ?></h1>

                <?php if ( isset( $table_config['add_new_url'] ) && $table_config['add_new_url'] !== '' ) : ?>
                    <a href="<?php echo esc_url( $table_config['add_new_url'] ); ?>"
                       class="page-title-action button">
                        <?php
                        echo esc_html(
	                        sprintf(
		                        $table_config['add_new_text'] ?? __( 'Add New %s', 'arraypress' ),
		                        $table_config['singular']
	                        )
                        );
                        ?>
                    </a>
                <?php endif; ?>
            </span>

				<?php do_action( "{$hook_prefix}header_content", $table_id, $table_config ); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Add hidden fields to maintain current page parameters.
	 */
	private function add_hidden_fields(): void {
		$parameters = [ 'page', 'post_type', 'status', 'paged', 'orderby', 'order' ];
		foreach ( $parameters as $param ) {
			if ( ! empty( $_REQUEST[ $param ] ) ) {
				echo '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( sanitize_text_field( $_REQUEST[ $param ] ) ) . '" />';
			}
		}
	}

	/**
	 * Setup screen options and help tabs.
	 *
	 * @param \WP_Screen $screen Current screen
	 */
	public function setup_screen( $screen ) {
		// Check if we're on one of our table pages
		$table_id = $this->get_table_id_for_screen( $screen );
		if ( ! $table_id || ! isset( $this->registered_tables[ $table_id ] ) ) {
			return;
		}

		$table_config = $this->registered_tables[ $table_id ];

		// Setup screen options
		$this->setup_screen_options( $screen, $table_id, $table_config );

		// Setup help tabs
		$this->setup_help_tabs( $screen, $table_config );

		// Setup help sidebar
		$this->setup_help_sidebar( $screen, $table_config );

		// Fire action to allow further screen customization
		do_action( "table_{$table_id}_setup_screen", $screen, $table_config );
	}

}