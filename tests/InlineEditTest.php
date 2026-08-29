<?php
/**
 * Inline edit tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\InlineEdit;
use ArrayPress\RegisterTables\InlineEditSave;
use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Applying one change to a selection.
 *
 * The dangerous shape is a bulk editor that writes its own defaults: open
 * it, press Update, and forty rows become drafts. Core avoids that with a
 * "— No Change —" option carrying the sentinel `-1`, and everything here is
 * about that sentinel holding.
 *
 * The rest is the plumbing that fails silently when it is wrong -- reading
 * the ids under a name nothing posts, or accepting a value the select never
 * offered.
 */
final class InlineEditTest extends TestCase {

	/**
	 * A table that can bulk edit its status.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'labels'    => [ 'plural' => 'products' ],
			'bulk_edit' => [
				'status' => [
					'label'   => 'Status',
					'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
				],
			],
		];
	}

	/**
	 * A submitted edit, as the form would post it.
	 *
	 * @param array<string, mixed> $extra Anything to add or override.
	 *
	 * @return void
	 */
	private function submit( array $extra = [] ): void {
		$_REQUEST = array_merge(
			[
				'bulk_edit'        => 'Update',
				'table_id'         => 'demo',
				'_bulk_edit_nonce' => 'valid',
				'products'         => [ '4', '5', '6' ],
				'status'           => 'draft',
			],
			$extra
		);
	}

	/**
	 * What the edit fired with, if it fired.
	 *
	 * @return array<int, mixed>|null
	 */
	private function applied(): ?array {
		$fired = null;

		add_action(
			'arraypress_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$fired ): void {
				$fired = [ $ids, $values ];
			}
		);

		InlineEditSave::process_bulk_edit( 'demo', $this->config() );

		return $fired;
	}

	protected function setUp(): void {
		rt_reset_globals();

		$_REQUEST = [];
	}

	/**
	 * A chosen value reaches the selection.
	 */
	public function test_a_submitted_edit_applies(): void {
		$this->submit();

		$this->assertSame( [ [ 4, 5, 6 ], [ 'status' => 'draft' ] ], $this->applied() );
	}

	/**
	 * "No change" changes nothing.
	 *
	 * Core's sentinel is -1 rather than an empty string, because an empty
	 * string is a value somebody might genuinely want to set.
	 */
	public function test_no_change_applies_nothing(): void {
		$this->submit( [ 'status' => '-1' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A field the form did not post is left alone.
	 */
	public function test_an_absent_field_applies_nothing(): void {
		$this->submit();

		unset( $_REQUEST['status'] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A value the select never offered is refused.
	 */
	public function test_an_unoffered_value_is_refused(): void {
		$this->submit( [ 'status' => 'deleted' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * The ids are read under the name the checkboxes post them with.
	 *
	 * Which is the plural label, not "ids". Reading the wrong one would have
	 * found nothing, every time, without complaining.
	 */
	public function test_ids_come_from_the_plural_name(): void {
		$this->submit();

		unset( $_REQUEST['products'] );

		$_REQUEST['ids'] = [ '4' ];

		$this->assertNull( $this->applied() );
	}

	/**
	 * Nothing selected does nothing.
	 */
	public function test_an_empty_selection_does_nothing(): void {
		$this->submit( [ 'products' => [] ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * Another table's submission is not this table's.
	 *
	 * Two list screens can be registered by one plugin, and a stray table_id
	 * would let one apply the other's edit.
	 */
	public function test_another_tables_submission_is_ignored(): void {
		$this->submit( [ 'table_id' => 'somethingelse' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A bad nonce is refused.
	 */
	public function test_a_bad_nonce_is_refused(): void {
		$this->submit();

		// The stub verifies against this rather than the value, so the value
		// stays realistic and the refusal is the thing being asserted.
		$GLOBALS['rt_nonce_ok'] = false;

		$this->assertNull( $this->applied() );

		unset( $GLOBALS['rt_nonce_ok'] );
	}

	/**
	 * A table with nothing to bulk edit renders nothing and does nothing.
	 */
	public function test_a_table_without_bulk_edit_is_inert(): void {
		$this->submit();

		$this->assertFalse( InlineEdit::has_bulk_edit( [ 'labels' => [ 'plural' => 'products' ] ] ) );

		ob_start();
		InlineEdit::render_inline_rows( 'demo', [ 'labels' => [] ], 5 );

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * The row carries core's classes, because core styles them.
	 *
	 * Matching core is what makes this need no stylesheet: list-tables.css is
	 * already loaded on every list screen.
	 */
	public function test_the_row_uses_core_markup(): void {
		ob_start();
		InlineEdit::render_inline_rows( 'demo', $this->config(), 5 );
		$html = (string) ob_get_clean();

		foreach (
			[
				'id="bulk-edit"',
				'inline-edit-row',
				'bulk-edit-row',
				'inline-edit-wrapper',
				'id="bulk-titles"',
				'colspanchange',
				'colspan="5"',
				'name="bulk_edit"',
			] as $marker
		) {
			$this->assertStringContainsString( $marker, $html, $marker );
		}

		// And the sentinel is first, so opening the row and pressing Update
		// changes nothing.
		$this->assertMatchesRegularExpression( '/<select name="status">\s*<option value="-1"/', $html );
	}

	/**
	 * Each row carries the title the editor lists it under.
	 */
	public function test_a_row_carries_its_title(): void {
		$html = InlineEdit::inline_data( 12, 'Deep House Kit' );

		$this->assertStringContainsString( 'id="inline_12"', $html );
		$this->assertStringContainsString( 'row_title', $html );
		$this->assertStringContainsString( 'Deep House Kit', $html );
	}

	/**
	 * A title with markup in it is escaped.
	 */
	public function test_a_title_is_escaped(): void {
		$html = InlineEdit::inline_data( 1, '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/* =========================================================================
	 * Quick Edit
	 * ========================================================================= */

	/**
	 * A table that can quick edit one row.
	 *
	 * @return array<string, mixed>
	 */
	private function quick_config(): array {
		return [
			'labels'     => [ 'plural' => 'products' ],
			'capability' => 'manage_options',
			'quick_edit' => [
				'status' => [
					'label'   => 'Status',
					'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
				],
				'sku'    => [ 'label' => 'SKU' ],
			],
		];
	}

	/**
	 * Register a table and post a quick edit at it.
	 *
	 * @param array<string, mixed> $post   What the row submits.
	 * @param array<string, mixed> $config Table configuration.
	 *
	 * @return void
	 */
	private function quick_submit( array $post, array $config = [] ): void {
		Manager::register( 'demo', $config ?: $this->quick_config() );

		$_POST = $_REQUEST = array_merge(
			[
				'table_id'     => 'demo',
				'item_id'      => '9',
				'_inline_edit' => 'valid',
			],
			$post
		);

		try {
			InlineEditSave::handle_quick_edit();
		} catch ( RuntimeException $e ) {
			// wp_die(), which is where every refused request and every
			// finished one ends. What matters is whether the action fired
			// before it, which the caller asserts.
			return;
		}
	}

	/**
	 * A quick edit reaches the handler with the row and its values.
	 */
	public function test_a_quick_edit_applies(): void {
		$seen = [];

		add_action(
			'arraypress_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = [ $id, $values ];
			}
		);

		$this->quick_submit( [ 'status' => 'draft', 'sku' => 'DHK-01' ] );

		$this->assertSame( 9, $seen[0] ?? null );
		$this->assertSame( [ 'status' => 'draft', 'sku' => 'DHK-01' ], $seen[1] ?? null );
	}

	/**
	 * A value the select never offered is refused.
	 *
	 * The markup constrains a browser and nothing constrains a POST, so this
	 * is the only thing standing between a hand-rolled request and any status
	 * the sender fancies.
	 */
	public function test_a_quick_edit_refuses_an_unoffered_value(): void {
		$seen = null;

		add_action(
			'arraypress_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->quick_submit( [ 'status' => 'deleted', 'sku' => 'DHK-01' ] );

		// The SKU is a text field and survives; the status does not.
		$this->assertSame( [ 'sku' => 'DHK-01' ], $seen );
	}

	/**
	 * A bad nonce stops it.
	 */
	public function test_a_quick_edit_needs_its_nonce(): void {
		$GLOBALS['rt_nonce_ok'] = false;

		$this->quick_submit( [ 'status' => 'draft' ] );

		$this->assertNotContains( 'arraypress_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * Without the capability, nothing happens.
	 */
	public function test_a_quick_edit_needs_the_capability(): void {
		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->quick_submit( [ 'status' => 'draft' ] );

		$this->assertNotContains( 'arraypress_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * A table that never declared quick edit cannot be quick edited.
	 *
	 * The endpoint is one URL for every table on the site, so the only thing
	 * scoping it is this check reading the table's own configuration.
	 */
	public function test_a_table_without_quick_edit_refuses(): void {
		$this->quick_submit(
			[ 'status' => 'draft' ],
			[ 'labels' => [ 'plural' => 'products' ] ]
		);

		$this->assertNotContains( 'arraypress_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * A field added by the filter saves as well as renders.
	 *
	 * Reading the raw config to save would give an add-on a control that
	 * appears in the row, accepts a value, and quietly drops it.
	 */
	public function test_a_filtered_field_saves(): void {
		$seen = null;

		add_filter(
			'arraypress_table_quick_edit_fields_demo',
			static function ( $fields ) {
				$fields['vendor'] = [ 'label' => 'Vendor' ];

				return $fields;
			}
		);

		add_action(
			'arraypress_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->quick_submit( [ 'vendor' => 'Acme' ] );

		$this->assertSame( [ 'vendor' => 'Acme' ], $seen );
	}

	/**
	 * The same filter reaches the bulk row too.
	 */
	public function test_a_filtered_field_saves_in_bulk(): void {
		$seen = null;

		add_filter(
			'arraypress_table_bulk_edit_fields_demo',
			static function ( $fields ) {
				$fields['vendor'] = [ 'label' => 'Vendor' ];

				return $fields;
			}
		);

		add_action(
			'arraypress_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->submit( [ 'vendor' => 'Acme' ] );

		InlineEditSave::process_bulk_edit( 'demo', $this->config() );

		$this->assertSame( 'Acme', $seen['vendor'] ?? null );
	}

	/**
	 * A table declaring only one editor gets the other's fields.
	 */
	public function test_one_declaration_serves_both_editors(): void {
		$config = $this->quick_config();

		$this->assertSame(
			array_keys( $config['quick_edit'] ),
			array_keys( InlineEdit::fields( 'demo', $config, 'bulk_edit' ) )
		);
	}

	/**
	 * The quick row carries what the ajax endpoint needs to find the table.
	 *
	 * Without the hidden table_id the endpoint cannot tell which table is
	 * saving, and refuses every request -- from a row that looks fine.
	 */
	public function test_the_quick_row_carries_its_table(): void {
		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $this->quick_config(), 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'id="inline-edit"', $html );
		$this->assertStringContainsString( 'name="table_id" value="demo"', $html );
		$this->assertStringContainsString( 'name="item_id"', $html );
		$this->assertStringContainsString( 'name="_inline_edit"', $html );
		$this->assertStringContainsString( 'class="button button-primary save"', $html );

		// No "No Change" sentinel on the quick row: it edits one row, and
		// every field shows what that row currently is.
		$this->assertStringNotContainsString( '<option value="-1"', $html );
	}

	/**
	 * A row's hidden block names each div for the input it fills.
	 */
	public function test_the_data_block_names_its_fields(): void {
		$html = InlineEdit::inline_data( 9, 'Deep House Kit', [ 'status' => 'draft' ] );

		$this->assertStringContainsString( '<div class="row_title">Deep House Kit</div>', $html );
		$this->assertStringContainsString( '<div class="status">draft</div>', $html );
	}
}
