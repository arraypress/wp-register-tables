<?php
/**
 * Derived label tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;

/**
 * The labels a table gets without being told any.
 *
 * A registration usually gives a singular and a plural and nothing else, so
 * the strings a user actually reads are the ones derived here. They are
 * cosmetic in the sense that nothing breaks when they are wrong -- which is
 * exactly why they drift: a button reading "Add New Product" beside core's
 * "Add Post" is wrong in a way that never fails and never gets reported, it
 * just quietly looks like a third-party plugin.
 */
final class LabelsTest extends TestCase {

	/**
	 * A clean registry between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();
	}

	/**
	 * Register a table and hand back its finished labels.
	 *
	 * @param array<string, mixed> $labels Label configuration.
	 *
	 * @return array<string, string>
	 */
	private function labels( array $labels ): array {
		Manager::register(
			'demo',
			[
				'labels'    => $labels,
				'columns'   => [ 'name' => 'Name' ],
				'callbacks' => [ 'get_items' => static fn() => [] ],
			]
		);

		return Manager::get_table( 'demo' )['labels'];
	}

	/**
	 * The add button says what core's says.
	 *
	 * WordPress dropped the "New" -- class-wp-post-type.php declares
	 * 'add_new_item' => array( __( 'Add Post' ), __( 'Add Page' ) ), and that
	 * is the label edit.php prints beside the heading. Our screens sit in the
	 * same menu, so they use the same phrasing.
	 */
	public function test_add_label_matches_core_wording(): void {
		$labels = $this->labels( [ 'singular' => 'product', 'plural' => 'products' ] );

		$this->assertSame( 'Add Product', $labels['add_new'] );
		$this->assertStringNotContainsString( 'New', $labels['add_new'] );
	}

	/**
	 * A given label is left alone.
	 */
	public function test_explicit_label_is_not_overwritten(): void {
		$labels = $this->labels(
			[
				'singular' => 'product',
				'plural'   => 'products',
				'add_new'  => 'Import Products',
			]
		);

		$this->assertSame( 'Import Products', $labels['add_new'] );
	}

	/**
	 * Title and search still come from the plural.
	 */
	public function test_title_and_search_derive_from_plural(): void {
		$labels = $this->labels( [ 'singular' => 'product', 'plural' => 'products' ] );

		$this->assertSame( 'Products', $labels['title'] );
		$this->assertSame( 'Search products', $labels['search'] );
	}

	/**
	 * No singular, no invented button.
	 *
	 * The add button is drawn from this label, so a bare "Add " would be a
	 * button rather than the absence of one.
	 */
	public function test_no_singular_leaves_the_label_empty(): void {
		$labels = $this->labels( [ 'plural' => 'products' ] );

		$this->assertSame( '', $labels['add_new'] );
	}
}
