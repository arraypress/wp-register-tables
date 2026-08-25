<?php
/**
 * Row reading tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Row;
use PHPUnit\Framework\TestCase;

/**
 * Reading a field off a row, whatever shape the row is.
 *
 * `get_items()` hands back whatever the plugin's query returned: an object
 * for anything with a model layer, an array for everything else. Three places
 * assumed an object and reached for method_exists() or property_exists(),
 * both of which throw a TypeError on an array — so a table built the ordinary
 * way died on its first row, and only tables whose rows happened to be
 * objects worked.
 */
final class RowTest extends TestCase {

	/**
	 * A field is read off an array.
	 */
	public function test_a_field_is_read_off_an_array(): void {
		$this->assertSame( 'Widget', Row::get( [ 'title' => 'Widget' ], 'title' ) );
		$this->assertSame( 12, Row::id( [ 'id' => 12 ] ) );
	}

	/**
	 * And off an object's property.
	 */
	public function test_a_field_is_read_off_a_property(): void {
		$this->assertSame( 'Widget', Row::get( (object) [ 'title' => 'Widget' ], 'title' ) );
		$this->assertSame( 12, Row::id( (object) [ 'id' => 12 ] ) );
	}

	/**
	 * And off a getter, which wins.
	 *
	 * A model that has one usually means it: an Order::get_total() may add
	 * tax that $order->total does not.
	 */
	public function test_a_getter_wins_over_a_property(): void {
		$this->assertSame( 'From the getter', Row::get( new RowWithGetter(), 'title' ) );
		$this->assertSame( 99, Row::id( new RowWithGetter() ) );
	}

	/**
	 * A field that is not there gives the fallback rather than throwing.
	 *
	 * @dataProvider shapeProvider
	 *
	 * @param mixed $item A row, in some shape.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'shapeProvider' )]
	public function test_a_missing_field_gives_the_fallback( mixed $item ): void {
		$this->assertSame( 'nothing', Row::get( $item, 'not_a_field', 'nothing' ) );
		$this->assertFalse( Row::has( $item, 'not_a_field' ) );
	}

	/**
	 * Every shape a query can hand back, including the ones that are not rows.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function shapeProvider(): array {
		return [
			'an array'  => [ [ 'title' => 'Widget' ] ],
			'an object' => [ (object) [ 'title' => 'Widget' ] ],
			'a getter'  => [ new RowWithGetter() ],
			'a string'  => [ 'not a row at all' ],
			'null'      => [ null ],
		];
	}

	/**
	 * A field that is there but empty is still there.
	 *
	 * A column whose value is nought should render nought, not the
	 * placeholder for a column that does not apply.
	 */
	public function test_a_field_that_is_empty_is_still_a_field(): void {
		$this->assertTrue( Row::has( [ 'total' => 0 ], 'total' ) );
		$this->assertTrue( Row::has( [ 'note' => '' ], 'note' ) );
		$this->assertTrue( Row::has( (object) [ 'total' => 0 ], 'total' ) );

		$this->assertSame( 0, Row::get( [ 'total' => 0 ], 'total', 'fallback' ) );
	}

	/**
	 * A null property is a property.
	 *
	 * isset() says no to a property set to null, so reading one has to fall
	 * through to property_exists() or a column that is legitimately null
	 * renders as a column that is missing.
	 */
	public function test_a_null_property_is_still_a_property(): void {
		$item = (object) [ 'note' => null ];

		$this->assertTrue( Row::has( $item, 'note' ) );
		$this->assertNull( Row::get( $item, 'note', 'fallback' ) );
	}

	/**
	 * A row with no id at all is nought rather than an error.
	 */
	public function test_a_row_with_no_id_is_nought(): void {
		$this->assertSame( 0, Row::id( [ 'title' => 'Widget' ] ) );
		$this->assertSame( 0, Row::id( 'not a row' ) );
	}
}

/**
 * A row with getters, as a model layer would give.
 */
final class RowWithGetter {

	/**
	 * Its title, which the getter overrides.
	 *
	 * @var string
	 */
	public string $title = 'From the property';

	/**
	 * Its title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return 'From the getter';
	}

	/**
	 * Its id.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return 99;
	}
}
