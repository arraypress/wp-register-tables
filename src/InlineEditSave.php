<?php
/**
 * Inline Edit Save
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types = 1 );

namespace ArrayPress\RegisterTables;

defined( 'ABSPATH' ) || exit;

/**
 * Applying what an inline editor submitted.
 *
 * Separate from the rendering because the two run in different requests and
 * fail in different ways. InlineEdit draws markup during a page load; this
 * reads a POST, and everything in it is a check on something an attacker
 * controls -- the nonce, the capability, the table, and every value against
 * the options the field actually offered.
 *
 * That last one matters more than it looks. The markup constrains a browser
 * and nothing constrains a POST, so a select offering three statuses will
 * happily receive a fourth. Both save paths run every submitted value past
 * the field that claims to own it, and drop anything the field would not
 * have offered.
 *
 * Neither path writes a row. They validate, then fire an action -- the
 * caller decides what saving means, because only the caller knows whether a
 * status change also has to reach Stripe.
 *
 * @since 1.0.0
 */
final class InlineEditSave {

	/**
	 * Apply a submitted bulk edit.
	 *
	 * Runs before the table is built, so the rows redraw with the change
	 * already in them -- the same as core, where the page reloads.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 *
	 * @return int How many rows were edited, 0 if nothing was.
	 */
	public static function process_bulk_edit( string $table_id, array $config ): int {
		if ( ! InlineEdit::has_bulk_edit( $config ) || ! isset( $_REQUEST['bulk_edit'] ) ) {
			return 0;
		}

		$submitted_table = isset( $_REQUEST['table_id'] )
			? sanitize_key( wp_unslash( $_REQUEST['table_id'] ) )
			: '';

		if ( $submitted_table !== $table_id ) {
			return 0;
		}

		$nonce = isset( $_REQUEST['_bulk_edit_nonce'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['_bulk_edit_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'bulk-edit-' . $table_id ) ) {
			return 0;
		}

		if ( ! current_user_can( self::edit_capability( $config ) ) ) {
			return 0;
		}

		// The checkboxes post under the plural label, which is also what the
		// bulk-action nonce is scoped to. Reading 'ids' would have found
		// nothing, every time, silently.
		$plural = (string) ( $config['labels']['plural'] ?? 'items' );

		$ids = array_values(
			array_filter( array_map( 'absint', (array) ( $_REQUEST[ $plural ] ?? [] ) ) )
		);

		if ( [] === $ids ) {
			return 0;
		}

		$values = [];

		// Read through fields() rather than off the config, so a field added by
		// the filter saves as well as renders. Reading the raw config here
		// would have given an add-on a control that looks like it works.
		foreach ( InlineEdit::fields( $table_id, $config, 'bulk_edit' ) as $name => $field ) {
			$submitted = isset( $_REQUEST[ $name ] )
				? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) )
				: '-1';

			// Core's sentinel. Not an empty string, because an empty string is
			// a value somebody might genuinely want to set.
			if ( '-1' === $submitted ) {
				continue;
			}

			if ( ! self::accepts( $field, $submitted ) ) {
				continue;
			}

			$values[ $name ] = $submitted;
		}

		if ( [] === $values ) {
			return 0;
		}

		/**
		 * Fires when a bulk edit is applied.
		 *
		 * @param array<int, int>      $ids    The rows being edited.
		 * @param array<string, mixed> $values What to set on them.
		 *
		 * @since 1.0.0
		 */
		do_action( "arraypress_table_bulk_edit_{$table_id}", $ids, $values );

		return count( $ids );
	}

	/**
	 * Save one row, and hand back the row as it now reads.
	 *
	 * Core's flow: the script posts the fields, the server saves and renders
	 * the single row, and the script swaps the markup in. Returning the row
	 * rather than a success flag is what keeps the table honest -- a status
	 * column, a formatted amount and a row action set all change with the
	 * save, and a client rebuilding them from the submitted values would be
	 * a second renderer drifting from the first.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function handle_quick_edit(): void {
		$table_id = isset( $_POST['table_id'] ) ? sanitize_key( wp_unslash( $_POST['table_id'] ) ) : '';

		$config = Manager::get_table( $table_id );

		if ( ! $config || ! InlineEdit::has_quick_edit( $config ) ) {
			wp_die( -1, 400 );
		}

		check_ajax_referer( 'inline-edit-' . $table_id, '_inline_edit' );

		if ( ! current_user_can( self::edit_capability( $config ) ) ) {
			wp_die( -1, 403 );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;

		if ( $item_id < 1 ) {
			wp_die( -1, 400 );
		}

		$values = [];

		foreach ( InlineEdit::fields( $table_id, $config, 'quick_edit' ) as $name => $field ) {
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}

			$submitted = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

			if ( ! self::accepts( $field, $submitted ) ) {
				continue;
			}

			$values[ $name ] = $submitted;
		}

		/**
		 * Fires when a row is quick edited.
		 *
		 * @param int                  $item_id The row.
		 * @param array<string, mixed> $values  What to set on it.
		 *
		 * @since 1.0.0
		 */
		do_action( "arraypress_table_quick_edit_{$table_id}", $item_id, $values );

		self::render_saved_row( $table_id, $config, $item_id );

		wp_die();
	}

	/**
	 * The capability an inline edit needs.
	 *
	 * The edit one, not the view one. A table can be readable by anyone who
	 * can see the menu and editable only by a shop manager -- that is what
	 * `capabilities['edit']` declares -- and both editors used to test the
	 * view capability, so declaring the edit one changed nothing.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return string
	 */
	private static function edit_capability( array $config ): string {
		return (string) ( $config['capabilities']['edit'] ?? $config['capability'] ?? 'manage_options' );
	}

	/**
	 * Draw one row, the way the table would.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 * @param int    $item_id  The row.
	 *
	 * @return void
	 */
	private static function render_saved_row( string $table_id, array $config, int $item_id ): void {
		$get = $config['callbacks']['get_item'] ?? null;

		if ( ! is_callable( $get ) ) {
			return;
		}

		$item = $get( $item_id );

		if ( ! $item ) {
			return;
		}

		// The column headers and nothing else. prepare_items() would also
		// fetch the counts and a whole page of rows in order to draw this
		// one, which the get_item callback has already handed over.
		$table = new Table( $table_id, $config );

		$table->prepare_columns();
		$table->single_row( $item );
	}

	/**
	 * Whether a field will accept the value submitted for it.
	 *
	 * A select may only be set to something it actually offered -- the markup
	 * constrains a browser, and nothing constrains a POST. A field with no
	 * options is a text input, which takes what it is given, already
	 * sanitised by the caller.
	 *
	 * @param array  $field     The field.
	 * @param string $submitted What came in.
	 *
	 * @return bool
	 */
	private static function accepts( array $field, string $submitted ): bool {
		$options = (array) ( $field['options'] ?? [] );

		return [] === $options || array_key_exists( $submitted, $options );
	}
}
