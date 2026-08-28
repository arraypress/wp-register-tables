<?php
/**
 * The status links above a table.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use ArrayPress\RegisterTables\Table;
use PHPUnit\Framework\TestCase;

final class ViewsTest extends TestCase {

	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * A table whose counts say what this test needs them to.
	 *
	 * @param array<string, int> $counts What get_counts returns.
	 *
	 * @return Table
	 */
	private function table( array $counts ): Table {
		Manager::register(
			'demo',
			[
				'columns'   => [ 'name' => 'Name' ],
				'views'     => [
					'all'     => 'All',
					'active'  => 'Active',
					'expired' => 'Expired',
				],
				'callbacks' => [
					'get_items'  => static fn(): array => [],
					'get_counts' => static fn(): array => $counts,
				],
			]
		);

		return new Table( 'demo', Manager::get_table( 'demo' ) );
	}

	/**
	 * Nothing exists at all, so there is nothing to navigate between.
	 *
	 * The links would read "All (0)" directly above an empty-state panel
	 * already saying the same thing, in a heavier way.
	 */
	public function test_no_views_when_nothing_exists(): void {
		$this->assertSame( [], $this->table( [ 'total' => 0 ] )->get_views() );
	}

	/**
	 * Rows exist, so the links are wanted.
	 */
	public function test_views_when_rows_exist(): void {
		$views = $this->table( [ 'total' => 5, 'active' => 3, 'expired' => 2 ] )->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'active', $views );
		$this->assertArrayHasKey( 'expired', $views );
		$this->assertStringContainsString( '(5)', $views['all'] );
	}

	/**
	 * A status nothing is in is left out, but the rest stay.
	 */
	public function test_an_empty_status_is_skipped(): void {
		$views = $this->table( [ 'total' => 3, 'active' => 3, 'expired' => 0 ] )->get_views();

		$this->assertArrayHasKey( 'active', $views );
		$this->assertArrayNotHasKey( 'expired', $views );
	}

	/**
	 * A filter matching nothing keeps the links.
	 *
	 * This is exactly when they are most wanted: something has to get the
	 * reader back to a status that does have rows. The check is on the
	 * unfiltered total, so it does not fire here.
	 */
	public function test_a_filter_matching_nothing_keeps_the_links(): void {
		$_GET['status']     = 'expired';
		$_REQUEST['status'] = 'expired';

		$views = $this->table( [ 'total' => 4, 'active' => 4, 'expired' => 0 ] )->get_views();

		$this->assertNotSame( [], $views );
		$this->assertArrayHasKey( 'all', $views );
	}
}
