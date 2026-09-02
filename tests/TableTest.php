<?php
/**
 * List table tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use ArrayPress\RegisterTables\Table;
use PHPUnit\Framework\TestCase;

/**
 * What a table decides before it draws anything.
 *
 * Which columns exist, which are sortable, what goes in the query, which view
 * is current. None of it throws when it is wrong — a missing column is a
 * narrower table, a dropped filter is a longer list — so it is the part worth
 * pinning rather than the markup.
 *
 * Tables are built through the manager, because that is the only way one is
 * ever built: registration fills the configuration with three dozen defaults,
 * and a table constructed straight from a bare array is a shape the library
 * never produces.
 */
final class TableTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * Register a table and hand it back, built.
	 *
	 * @param array<string, mixed> $config Table configuration.
	 *
	 * @return Table
	 */
	private function table( array $config = [] ): Table {
		$config = array_merge(
			[
				'columns'   => [ 'name' => 'Name', 'email' => 'Email' ],
				'callbacks' => [ 'get_items' => static fn() => [] ],
			],
			$config
		);

		Manager::register( 'demo', $config );

		return new Table( 'demo', Manager::get_table( 'demo' ) );
	}

	/**
	 * Columns come from the configuration, in order.
	 */
	public function test_columns_come_from_the_configuration(): void {
		$this->assertSame( [ 'name', 'email' ], array_keys( $this->table()->get_columns() ) );
	}

	/**
	 * A column can be a string or an array with a label.
	 */
	public function test_a_column_can_be_a_string_or_an_array(): void {
		$columns = $this->table(
			[
				'columns' => [
					'name'  => 'Name',
					'total' => [ 'label' => 'Total', 'type' => 'price' ],
				],
			]
		)->get_columns();

		$this->assertSame( 'Name', $columns['name'] );
		$this->assertSame( 'Total', $columns['total'] );
	}

	/**
	 * An array column with no label is left out.
	 *
	 * A column with no heading is a blank in the header row, which reads as
	 * a rendering fault rather than a configuration one.
	 */
	public function test_a_column_without_a_label_is_left_out(): void {
		$columns = $this->table( [ 'columns' => [ 'name' => 'Name', 'mystery' => [ 'type' => 'price' ] ] ] )->get_columns();

		$this->assertArrayNotHasKey( 'mystery', $columns );
	}

	/**
	 * The checkbox column appears only when there is something to do with it.
	 *
	 * A column of checkboxes above no bulk actions is a control that does
	 * nothing.
	 */
	public function test_the_checkbox_column_follows_the_bulk_actions(): void {
		$this->assertArrayNotHasKey( 'cb', $this->table()->get_columns() );

		$columns = $this->table( [ 'bulk_actions' => [ 'delete' => 'Delete' ] ] )->get_columns();

		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertSame( 'cb', array_key_first( $columns ) );
	}

	/**
	 * Sortable columns take either spelling.
	 *
	 * A bare list is what anyone writes first; the array form is for when the
	 * column and the order-by differ, or the sort starts descending.
	 */
	public function test_sortable_columns_take_either_spelling(): void {
		$sortable = $this->table( [ 'sortable' => [ 'name', 'email' ] ] )->get_sortable_columns();

		$this->assertSame( [ 'name', false ], $sortable['name'] );

		$sortable = $this->table( [ 'sortable' => [ 'name' => [ 'display_name', true ] ] ] )->get_sortable_columns();

		$this->assertSame( [ 'display_name', true ], $sortable['name'] );
	}

	/**
	 * Nothing is sortable unless it says so.
	 */
	public function test_nothing_is_sortable_by_default(): void {
		$this->assertSame( [], $this->table()->get_sortable_columns() );
	}

	/**
	 * The query gets the page and the ordering.
	 */
	public function test_the_query_carries_the_pagination(): void {
		$seen = null;

		$this->table(
			[
				'per_page'  => 10,
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( 10, $seen['number'] ?? null );
		$this->assertSame( 0, $seen['offset'] ?? null );
	}

	/**
	 * The second page is offset by a page.
	 *
	 * Off-by-one here shows the first row of page two at the bottom of page
	 * one, or skips it entirely, and neither is visible without counting.
	 */
	public function test_the_second_page_is_offset(): void {
		$_GET['paged'] = '2';

		$seen = null;

		$this->table(
			[
				'per_page'  => 10,
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( 10, $seen['offset'] ?? null );
	}

	/**
	 * Only a sortable column can order the query.
	 *
	 * The orderby argument goes into the consumer's query as its ORDER BY,
	 * and the URL is anybody's to write. A column nobody offered falls back
	 * to the configured default.
	 */
	public function test_only_a_sortable_column_can_order_the_query(): void {
		$_GET['orderby'] = 'password';

		$seen = null;

		$this->table(
			[
				'sortable'  => [ 'name' ],
				'orderby'   => 'name',
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( 'name', $seen['orderby'] ?? null );
	}

	/**
	 * A sortable column orders it, under either of its names.
	 *
	 * The link core draws carries the orderby value rather than the column
	 * key, so `['name' => ['display_name', true]]` arrives as display_name.
	 */
	public function test_a_sortable_column_orders_the_query(): void {
		$seen = null;

		$get_items = function ( $args ) use ( &$seen ) {
			$seen = $args;

			return [];
		};

		$_GET['orderby'] = 'email';

		$this->table( [ 'sortable' => [ 'email' ], 'callbacks' => [ 'get_items' => $get_items ] ] )->get_data();

		$this->assertSame( 'email', $seen['orderby'] ?? null );

		$_GET['orderby'] = 'display_name';

		$this->table(
			[
				'sortable'  => [ 'name' => [ 'display_name', true ] ],
				'callbacks' => [ 'get_items' => $get_items ],
			]
		)->get_data();

		$this->assertSame( 'display_name', $seen['orderby'] ?? null );
	}

	/**
	 * A status in the URL becomes a query argument.
	 */
	public function test_a_status_reaches_the_query(): void {
		$_GET['status'] = 'active';

		$seen = null;

		$this->table(
			[
				'views'     => [ 'active' => 'Active' ],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( 'active', $seen['status'] ?? null );
	}

	/**
	 * A filter in the URL becomes a query argument.
	 */
	public function test_a_filter_reaches_the_query(): void {
		$_GET['country'] = 'GB';

		$seen = null;

		$this->table(
			[
				'filters'   => [ 'country' => [ 'label' => 'Country', 'options' => [ 'GB' => 'UK' ] ] ],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( 'GB', $seen['country'] ?? null );
	}

	/**
	 * A value a filter never offered is not a filter.
	 *
	 * A select constrains a browser and nothing constrains a URL, so a
	 * country the dropdown does not list is treated as the dropdown left on
	 * "All" rather than handed to the query as a country.
	 */
	public function test_an_unoffered_filter_value_is_dropped(): void {
		$_GET['country'] = 'XX';

		$seen = null;

		$this->table(
			[
				'filters'   => [ 'country' => [ 'label' => 'Country', 'options' => [ 'GB' => 'UK' ] ] ],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertArrayNotHasKey( 'country', $seen );
	}

	/**
	 * A filter with a callback shapes the query itself.
	 *
	 * Which is what a filter that is not a plain column needs — a date range
	 * becomes two arguments, a search across several columns becomes none of
	 * them.
	 */
	public function test_a_filter_can_shape_the_query_itself(): void {
		$_GET['when'] = 'today';

		$seen = null;

		$this->table(
			[
				'filters'   => [
					'when' => [
						'label'          => 'When',
						'apply_callback' => static function ( &$args, $value ) {
							$args['date_query'] = [ 'after' => $value ];
						},
					],
				],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertSame( [ 'after' => 'today' ], $seen['date_query'] ?? null );
		$this->assertArrayNotHasKey( 'when', $seen );
	}

	/**
	 * An empty filter is not a filter.
	 *
	 * A select left on "All" submits an empty string, and passing that on
	 * would ask for the rows whose country is nothing.
	 */
	public function test_an_empty_filter_is_ignored(): void {
		$_GET['country'] = '';

		$seen = null;

		$this->table(
			[
				'filters'   => [ 'country' => [ 'label' => 'Country' ] ],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertArrayNotHasKey( 'country', $seen );
	}

	/**
	 * A parameter nobody declared does not reach the query.
	 *
	 * The list of filters is the whitelist. Anything in the URL could have
	 * been put there by anyone, and a query argument that reaches a database
	 * layer unasked is how a list table becomes an injection surface.
	 */
	public function test_an_undeclared_parameter_does_not_reach_the_query(): void {
		$_GET['meta_query'] = 'anything';
		$_GET['country']    = 'GB';

		$seen = null;

		$this->table(
			[
				'filters'   => [ 'country' => [ 'label' => 'Country' ] ],
				'callbacks' => [
					'get_items' => function ( $args ) use ( &$seen ) {
						$seen = $args;

						return [];
					},
				],
			]
		)->get_data();

		$this->assertArrayNotHasKey( 'meta_query', $seen );
		$this->assertSame( 'GB', $seen['country'] ?? null );
	}

	/**
	 * Without a get_items callback there are no rows, rather than a fatal.
	 */
	public function test_no_callback_means_no_rows(): void {
		Manager::register( 'demo', [ 'columns' => [ 'name' => 'Name' ] ] );

		$table = new Table( 'demo', Manager::get_table( 'demo' ) );

		$this->assertSame( [], $table->get_data() );
	}
	/**
	 * A row that is an array draws, like a row that is an object.
	 *
	 * `get_items()` returns whatever the plugin's query returned, and that is
	 * very often an array — $wpdb->get_results() with ARRAY_A, or a plugin
	 * that never had objects for its rows. Drawing one used to call
	 * method_exists() on it to see whether it had a status, and
	 * method_exists() takes an object or a class name and throws a TypeError
	 * on anything else.
	 *
	 * So every table built that way fataled on its first row, with a stack
	 * trace where the list should be, and only tables whose rows happened to
	 * be objects worked.
	 *
	 * @dataProvider rowShapeProvider
	 *
	 * @param mixed  $row      One row, in some shape.
	 * @param string $expected The row class it should get.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'rowShapeProvider' )]
	public function test_a_row_draws_whatever_shape_it_is( mixed $row, string $expected ): void {
		$table = $this->table(
			[
				'columns' => [ 'title' => 'Title' ],
			]
		);

		ob_start();

		try {
			$table->single_row( $row );
		} finally {
			$html = (string) ob_get_clean();
		}

		// Every row carries its id, so Quick Edit can find and replace it.
		$this->assertStringContainsString( 'id="item-', $html );

		// The cells are the stub's; what this decides is the row's class.
		if ( '' === $expected ) {
			$this->assertStringNotContainsString( 'class=', $html );

			return;
		}

		$this->assertStringContainsString( $expected, $html );
	}

	/**
	 * One row per shape a query can hand back.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function rowShapeProvider(): array {
		return [
			'an array'                => [ [ 'title' => 'Widget', 'status' => 'complete' ], 'status-complete' ],
			'an array with no status' => [ [ 'title' => 'Widget' ], '' ],
			'an object'               => [ (object) [ 'title' => 'Widget', 'status' => 'pending' ], 'status-pending' ],
			'an object with a method' => [ new RowWithStatus(), 'status-refunded' ],
		];
	}


	/**
	 * A table with no status views still knows how many items it has.
	 *
	 * Counts are for the status links above the table, so a table with no
	 * statuses has no reason to supply them — and one that supplied only
	 * get_total() was told it had nothing. "0 items" above a full page of
	 * them, and no pagination at all, because the pager works from that
	 * number.
	 */
	public function test_a_table_without_counts_uses_its_total_callback(): void {
		$table = $this->table(
			[
				'columns'   => [ 'title' => 'Title' ],
				'callbacks' => [
					'get_items' => static fn(): array => [ [ 'id' => 1, 'title' => 'Widget' ] ],
					'get_total' => static fn(): int => 240,
				],
			]
		);

		$table->prepare_items();

		$this->assertSame( 240, (int) $table->pagination_args()['total_items'] );
	}

	/**
	 * And a counts callback still wins, because it knows about the statuses.
	 */
	public function test_a_counts_callback_wins(): void {
		$table = $this->table(
			[
				'columns'   => [ 'title' => 'Title' ],
				'callbacks' => [
					'get_items'  => static fn(): array => [],
					'get_counts' => static fn(): array => [ 'total' => 12 ],
					'get_total'  => static fn(): int => 240,
				],
			]
		);

		$table->prepare_items();

		$this->assertSame( 12, (int) $table->pagination_args()['total_items'] );
	}

}

/**
 * A row that answers get_status(), as an ORM's model would.
 */
final class RowWithStatus {

	/**
	 * Its title.
	 *
	 * @var string
	 */
	public string $title = 'Widget';

	/**
	 * Its status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return 'refunded';
	}
}
