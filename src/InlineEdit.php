<?php
/**
 * Inline Edit
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types = 1 );

namespace ArrayPress\RegisterTables;

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
 * one: the two editors do not work the same way. Bulk edit is a plain form
 * POST -- the script only reveals the row and lists what was selected, and
 * pressing Update submits the whole list form, selection included, and the
 * page reloads. Quick Edit is the ajax one: it saves a single row and swaps
 * the rendered markup back in without a reload.
 *
 * Both are extensible the way core's are. A per-table fields filter
 * adds inputs, `arraypress_table_inline_edit_{$table_id}` prints markup at
 * the bottom of either row, and the save fires an action rather than writing
 * anything itself -- this renders and validates, the caller decides what a
 * save means.
 *
 * @since 1.0.0
 */
final class InlineEdit {

	/**
	 * Whether this table has anything to bulk edit.
	 *
	 * Either key counts, the same way fields() takes either. A table that
	 * declares only `quick_edit` still gets a bulk row, because the two are
	 * the same question asked of the same columns.
	 *
	 * Asking for the literal `bulk_edit` key here was a real bug: the fields
	 * resolved through the fallback, so every test passed, and the bulk row
	 * was never rendered at all -- leaving "Bulk edit" in the actions
	 * dropdown opening nothing, on a page that looked fine.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 */
	public static function has_bulk_edit( array $config ): bool {
		return self::declares( $config );
	}

	/**
	 * The hidden per-row data the script reads a title out of.
	 *
	 * Core keeps this in a div inside the title cell and reads it back with
	 * `$( '#inline_' + id + ' .post_title' )`. The same idea, because the
	 * alternative is scraping the rendered cell -- which contains row
	 * actions, a thumbnail and whatever else a column decided to draw.
	 *
	 * Each div's class is the name of the input it fills, so the script can
	 * populate the editor by walking its own fields rather than being told
	 * about each one. A field added by the `fields` filter needs no script
	 * change to open with its current value in it.
	 *
	 * @param int                   $item_id The row id.
	 * @param string                $title   The row's title.
	 * @param array<string, scalar> $values  Current value per field name.
	 *
	 * @return string
	 */
	public static function inline_data( int $item_id, string $title, array $values = [] ): string {
		$fields = sprintf( '<div class="row_title">%s</div>', esc_html( $title ) );

		foreach ( $values as $name => $value ) {
			$fields .= sprintf(
				'<div class="%s">%s</div>',
				esc_attr( (string) $name ),
				esc_html( (string) $value )
			);
		}

		return sprintf( '<div class="hidden" id="inline_%d">%s</div>', $item_id, $fields );
	}

	/**
	 * Whether this table can quick edit a row.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 */
	public static function has_quick_edit( array $config ): bool {
		return self::declares( $config );
	}

	/**
	 * Whether either inline editor was configured.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 */
	private static function declares( array $config ): bool {
		foreach ( [ 'quick_edit', 'bulk_edit' ] as $key ) {
			if ( ! empty( $config[ $key ] ) && is_array( $config[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The fields a row exposes to the inline editors.
	 *
	 * Quick edit and bulk edit are usually the same set with a different
	 * question asked of it -- "what should this be" against "what should
	 * these all become" -- so a table that declares one gets the other unless
	 * it says otherwise.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 * @param string $which    'quick_edit' or 'bulk_edit'.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( string $table_id, array $config, string $which ): array {
		$fields = $config[ $which ] ?? [];

		if ( [] === $fields ) {
			$fields = $config[ 'quick_edit' === $which ? 'bulk_edit' : 'quick_edit' ] ?? [];
		}

		/**
		 * Filter the fields an inline editor offers.
		 *
		 * How an add-on puts its own control in the row without the table
		 * having to know about it.
		 *
		 * Named for the table, not just the editor. Several plugins bundle
		 * this library and each registers several tables, so an unscoped
		 * `..._bulk_edit_fields` would hand every one of them the same
		 * fields -- an add-on adding a control to its own products table
		 * would add it to somebody else's orders table too.
		 *
		 * @param array<string, array<string, mixed>> $fields The fields.
		 * @param array<string, mixed>                $config Table configuration.
		 *
		 * @since 1.0.0
		 */
		// Spelled out rather than built from $which, so both names can be
		// found by grepping for them -- a hook assembled out of a variable is
		// a hook nobody discovers.
		if ( 'bulk_edit' === $which ) {
			return (array) apply_filters( "arraypress_table_bulk_edit_fields_{$table_id}", $fields, $config );
		}

		return (array) apply_filters( "arraypress_table_quick_edit_fields_{$table_id}", $fields, $config );
	}

	/**
	 * One control in an inline editor.
	 *
	 * A select when it has options and a text box when it does not, which
	 * covers what fits on a row. Anything needing more than that is a panel,
	 * and the row already has an Edit action leading to one.
	 *
	 * @param string $name     Field name.
	 * @param array  $field    Field configuration.
	 * @param bool   $bulk     Whether this is the bulk row.
	 *
	 * @return void
	 */
	private static function render_field( string $name, array $field, bool $bulk ): void {
		$options = (array) ( $field['options'] ?? [] );
		?>
		<label class="inline-edit-<?php echo esc_attr( $name ); ?> alignleft">
			<span class="title"><?php echo esc_html( $field['label'] ?? $name ); ?></span>
			<?php if ( [] !== $options ) : ?>
				<select name="<?php echo esc_attr( $name ); ?>">
					<?php if ( $bulk ) : ?>
						<?php
						/*
						 * Bulk only, and first. A bulk editor that applies its
						 * own defaults to everything selected is how somebody
						 * sets forty rows to draft by opening it and pressing
						 * Update. Quick edit has no such option because it is
						 * editing one row whose values it already knows.
						 */
						?>
						<option value="-1"><?php esc_html_e( '&mdash; No Change &mdash;', 'arraypress' ); ?></option>
					<?php endif; ?>
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>"><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<span class="input-text-wrap">
					<input type="text" name="<?php echo esc_attr( $name ); ?>" value="" autocomplete="off" />
				</span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Both editors, hidden until the script moves one into the table.
	 *
	 * One hidden table holding two rows, which is exactly what core does --
	 * `while ( $bulk < 2 )` in WP_Posts_List_Table::inline_edit(). The script
	 * clones #inline-edit into a row's place, or moves #bulk-edit to the top.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 * @param int    $columns  How many columns the table has.
	 *
	 * @return void
	 */
	public static function render_inline_rows( string $table_id, array $config, int $columns ): void {
		$has_bulk  = self::has_bulk_edit( $config );
		$has_quick = self::has_quick_edit( $config );

		if ( ! $has_bulk && ! $has_quick ) {
			return;
		}
		?>
		<table style="display: none"><tbody id="inlineedit">
		<?php foreach ( [ false, true ] as $bulk ) : ?>
			<?php
			if ( $bulk ? ! $has_bulk : ! $has_quick ) {
				continue;
			}

			$fields = self::fields( $table_id, $config, $bulk ? 'bulk_edit' : 'quick_edit' );

			if ( [] === $fields ) {
				continue;
			}

			$classes = $bulk
				? 'inline-edit-row inline-edit-row-page bulk-edit-row bulk-edit-row-page bulk-edit-' . $table_id
				: 'inline-edit-row inline-edit-row-page quick-edit-row quick-edit-row-page inline-edit-' . $table_id;
			?>
			<tr id="<?php echo $bulk ? 'bulk-edit' : 'inline-edit'; ?>" class="<?php echo esc_attr( $classes ); ?>" style="display: none">
				<td colspan="<?php echo (int) $columns; ?>" class="colspanchange">
					<div class="inline-edit-wrapper" role="region" aria-labelledby="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
						<fieldset class="inline-edit-col-left">
							<legend class="inline-edit-legend" id="<?php echo $bulk ? 'bulk' : 'quick'; ?>-edit-legend">
								<?php echo $bulk ? esc_html__( 'Bulk Edit', 'arraypress' ) : esc_html__( 'Quick Edit', 'arraypress' ); ?>
							</legend>
							<div class="inline-edit-col">
								<?php if ( $bulk ) : ?>
									<div id="bulk-title-div">
										<div id="bulk-titles"></div>
									</div>
								<?php else : ?>
									<label>
										<span class="title"><?php esc_html_e( 'Name', 'arraypress' ); ?></span>
										<span class="input-text-wrap">
											<input type="text" name="row_title" class="ptitle" value="" />
										</span>
									</label>
								<?php endif; ?>
							</div>
						</fieldset>

						<fieldset class="inline-edit-col-right">
							<div class="inline-edit-col">
								<?php foreach ( $fields as $name => $field ) : ?>
									<?php self::render_field( (string) $name, (array) $field, $bulk ); ?>
								<?php endforeach; ?>

								<?php
								/**
								 * Print extra markup inside an inline editor.
								 *
								 * The escape hatch for a control the `fields`
								 * filter cannot describe -- a media picker, a
								 * pair of inputs that only make sense
								 * together. Whatever is printed here posts
								 * with the rest of the row, and the save
								 * action receives it.
								 *
								 * @param bool                 $bulk   True on
								 *                                     the bulk
								 *                                     row.
								 * @param array<string, mixed> $config Table
								 *                                     configuration.
								 *
								 * @since 1.0.0
								 */
								do_action( "arraypress_table_inline_edit_{$table_id}", $bulk, $config );
								?>
							</div>
						</fieldset>

						<div class="submit inline-edit-save">
							<?php if ( $bulk ) : ?>
								<?php wp_nonce_field( 'bulk-edit-' . $table_id, '_bulk_edit_nonce', false ); ?>
								<input type="hidden" name="table_id" value="<?php echo esc_attr( $table_id ); ?>" />
								<?php submit_button( __( 'Update', 'arraypress' ), 'primary', 'bulk_edit', false ); ?>
							<?php else : ?>
								<?php wp_nonce_field( 'inline-edit-' . $table_id, '_inline_edit', false ); ?>
								<input type="hidden" name="table_id" value="<?php echo esc_attr( $table_id ); ?>" />
								<input type="hidden" name="item_id" value="0" />
								<button type="button" class="button button-primary save"><?php esc_html_e( 'Update', 'arraypress' ); ?></button>
							<?php endif; ?>

							<button type="button" class="button cancel"><?php esc_html_e( 'Cancel', 'arraypress' ); ?></button>

							<?php if ( ! $bulk ) : ?>
								<span class="spinner"></span>
							<?php endif; ?>

							<div class="notice notice-error notice-alt inline hidden"><p class="error"></p></div>
						</div>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}
}
