<?php
/**
 * Bulk edit tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Traits\BulkEdit;
use PHPUnit\Framework\TestCase;

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
final class BulkEditTest extends TestCase {

	/**
	 * An object with the trait on it.
	 *
	 * @return object
	 */
	private function editor(): object {
		return new class {
			use BulkEdit;
		};
	}

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

		$this->editor()::process_bulk_edit( 'demo', $this->config() );

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

		$this->assertFalse( $this->editor()::has_bulk_edit( [ 'labels' => [ 'plural' => 'products' ] ] ) );

		ob_start();
		$this->editor()::render_bulk_edit_row( 'demo', [ 'labels' => [] ], 5 );

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
		$this->editor()::render_bulk_edit_row( 'demo', $this->config(), 5 );
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
		$html = $this->editor()::inline_data( 12, 'Deep House Kit' );

		$this->assertStringContainsString( 'id="inline_12"', $html );
		$this->assertStringContainsString( 'row-title', $html );
		$this->assertStringContainsString( 'Deep House Kit', $html );
	}

	/**
	 * A title with markup in it is escaped.
	 */
	public function test_a_title_is_escaped(): void {
		$html = $this->editor()::inline_data( 1, '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
	}
}
