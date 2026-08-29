<?php
/**
 * Bulk Edit
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types = 1 );

namespace ArrayPress\RegisterTables\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Core's bulk edit, on a table that is not a post type.
 *
 * The markup, the classes and the behaviour are copied from
 * WP_Posts_List_Table::inline_edit() and inline-edit-post.js deliberately,
 * not approximated. Core already styles `.inline-edit-row`, `.bulk-edit-row`,
 * `.inline-edit-col`, `.ntdelitem` and the rest in list-tables.css, which is
 * loaded on every list screen -- so being identical means this needs no
 * stylesheet of its own, and it means somebody who has bulk-edited a post
 * already knows how this works.
 *
 * The flow is core's too, and worth stating because it is not the obvious
 * one: bulk edit is a plain form POST, not an ajax save. The script only
 * reveals the row and fills in the list of what was selected; pressing
 * Update submits the whole list form, selection included, and the page
 * reloads. Only Quick Edit uses ajax in core, and Quick Edit is not here.
 *
 * @since 1.0.0
 */
trait BulkEdit {

	/**
	 * Whether this table has anything to bulk edit.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 */
	public static function has_bulk_edit( array $config ): bool {
		return ! empty( $config['bulk_edit'] ) && is_array( $config['bulk_edit'] );
	}

	/**
	 * The hidden per-row data the script reads a title out of.
	 *
	 * Core keeps this in a div inside the title cell and reads it back with
	 * `$( '#inline_' + id + ' .post_title' )`. The same idea, because the
	 * alternative is scraping the rendered cell -- which contains row
	 * actions, a thumbnail and whatever else a column decided to draw.
	 *
	 * @param int    $item_id The row id.
	 * @param string $title   The row's title.
	 *
	 * @return string
	 */
	public static function inline_data( int $item_id, string $title ): string {
		return sprintf(
			'<div class="hidden" id="inline_%d"><div class="row-title">%s</div></div>',
			$item_id,
			esc_html( $title )
		);
	}

	/**
	 * The bulk edit row, hidden until the script moves it into the table.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 * @param int    $columns  How many columns the table has.
	 *
	 * @return void
	 */
	public static function render_bulk_edit_row( string $table_id, array $config, int $columns ): void {
		if ( ! self::has_bulk_edit( $config ) ) {
			return;
		}

		$fields = $config['bulk_edit'];
		?>
		<table style="display: none"><tbody id="inlineedit">
		<tr id="bulk-edit" class="inline-edit-row inline-edit-row-page bulk-edit-row bulk-edit-row-page bulk-edit-<?php echo esc_attr( $table_id ); ?>" style="display: none">
			<td colspan="<?php echo (int) $columns; ?>" class="colspanchange">
				<div class="inline-edit-wrapper" role="region" aria-labelledby="bulk-edit-legend">
					<fieldset class="inline-edit-col-left">
						<legend class="inline-edit-legend" id="bulk-edit-legend"><?php esc_html_e( 'Bulk Edit', 'arraypress' ); ?></legend>
						<div class="inline-edit-col">
							<div id="bulk-title-div">
								<div id="bulk-titles"></div>
							</div>
						</div>
					</fieldset>

					<fieldset class="inline-edit-col-right">
						<div class="inline-edit-col">
							<?php foreach ( $fields as $name => $field ) : ?>
								<label class="inline-edit-status alignleft">
									<span class="title"><?php echo esc_html( $field['label'] ?? $name ); ?></span>
									<select name="<?php echo esc_attr( $name ); ?>">
										<?php
										/*
										 * "No change" first and selected. A bulk
										 * editor that applies its own defaults to
										 * everything selected is how somebody
										 * sets forty rows to draft by opening it
										 * and pressing Update.
										 */
										?>
										<option value="-1"><?php esc_html_e( '&mdash; No Change &mdash;', 'arraypress' ); ?></option>
										<?php foreach ( (array) ( $field['options'] ?? [] ) as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>"><?php echo esc_html( (string) $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="submit inline-edit-save">
						<?php wp_nonce_field( 'bulk-edit-' . $table_id, '_bulk_edit_nonce', false ); ?>
						<input type="hidden" name="table_id" value="<?php echo esc_attr( $table_id ); ?>" />
						<?php submit_button( __( 'Update', 'arraypress' ), 'primary', 'bulk_edit', false ); ?>
						<button type="button" class="button cancel"><?php esc_html_e( 'Cancel', 'arraypress' ); ?></button>
					</div>
				</div>
			</td>
		</tr>
		</tbody></table>
		<?php
	}

	/**
	 * Apply a submitted bulk edit.
	 *
	 * Runs before the table is built, so the rows redraw with the change
	 * already in them -- the same as core, where the page reloads.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 *
	 * @return void
	 */
	public static function process_bulk_edit( string $table_id, array $config ): void {
		if ( ! self::has_bulk_edit( $config ) || ! isset( $_REQUEST['bulk_edit'] ) ) {
			return;
		}

		$submitted_table = isset( $_REQUEST['table_id'] )
			? sanitize_key( wp_unslash( $_REQUEST['table_id'] ) )
			: '';

		if ( $submitted_table !== $table_id ) {
			return;
		}

		$nonce = isset( $_REQUEST['_bulk_edit_nonce'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['_bulk_edit_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'bulk-edit-' . $table_id ) ) {
			return;
		}

		$capability = (string) ( $config['capability'] ?? 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// The checkboxes post under the plural label, which is also what the
		// bulk-action nonce is scoped to. Reading 'ids' would have found
		// nothing, every time, silently.
		$plural = (string) ( $config['labels']['plural'] ?? 'items' );

		$ids = array_values(
			array_filter( array_map( 'absint', (array) ( $_REQUEST[ $plural ] ?? [] ) ) )
		);

		if ( [] === $ids ) {
			return;
		}

		$values = [];

		foreach ( $config['bulk_edit'] as $name => $field ) {
			$submitted = isset( $_REQUEST[ $name ] )
				? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) )
				: '-1';

			// Core's sentinel. Not an empty string, because an empty string is
			// a value somebody might genuinely want to set.
			if ( '-1' === $submitted ) {
				continue;
			}

			if ( ! array_key_exists( $submitted, (array) ( $field['options'] ?? [] ) ) ) {
				continue;
			}

			$values[ $name ] = $submitted;
		}

		if ( [] === $values ) {
			return;
		}

		/**
		 * Fires when a bulk edit is applied.
		 *
		 * @param array<int, int>      $ids    The rows being edited.
		 * @param array<string, mixed> $values What to set on them.
		 *
		 * @since 1.0.0
		 */
		do_action( 'arraypress_table_bulk_edit_' . $table_id, $ids, $values );
	}
}
