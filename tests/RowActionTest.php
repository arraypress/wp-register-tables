<?php
/**
 * Row action tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;

/**
 * A row action with a handler is a link that does something.
 *
 * Reached from `admin_init` by matching `?page=`, like a bulk action, and
 * gated the same way: a nonce named for the action and the row, and a
 * capability. The capability is the half that was missing -- an action
 * naming none of its own was checked against nothing at all, so past the
 * nonce anyone who could see the row could act on it.
 */
final class RowActionTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * Register a table whose archive action records the row it was given.
	 *
	 * @param array<string, mixed> $config Extra table configuration.
	 * @param array<string, mixed> $action Extra action configuration.
	 * @param int|null             $ran    Filled with the id the handler ran on.
	 *
	 * @return void
	 */
	private function register( array $config, array $action, ?int &$ran ): void {
		$ran = null;

		Manager::register(
			'demo',
			array_merge(
				[
					'menu_slug'   => 'demo',
					'labels'      => [ 'singular' => 'item', 'plural' => 'items' ],
					'columns'     => [ 'name' => 'Name' ],
					'row_actions' => [
						'archive' => array_merge(
							[
								'label'   => 'Archive',
								'handler' => function ( int $id ) use ( &$ran ) {
									$ran = $id;

									return true;
								},
							],
							$action
						),
					],
				],
				$config
			)
		);
	}

	/**
	 * Follow the archive link for row five.
	 *
	 * The redirect a finished action ends with is thrown by the stub, so the
	 * assertions after it can run.
	 *
	 * @return string|null Where it redirected, or null if it did not.
	 */
	private function follow(): ?string {
		$_GET = [
			'page'     => 'demo',
			'action'   => 'archive',
			'item'     => '5',
			'_wpnonce' => 'nonce',
		];

		try {
			Manager::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			return $redirect->location;
		}

		return null;
	}

	/**
	 * The handler runs, with the row.
	 */
	public function test_the_handler_gets_the_row(): void {
		$this->register( [], [], $ran );

		$this->assertNotNull( $this->follow() );
		$this->assertSame( 5, $ran );
	}

	/**
	 * An action with no capability of its own asks for the table's edit one.
	 *
	 * The view capability is what gets somebody to the page. Acting on a
	 * row is editing, and a table can declare that separately.
	 */
	public function test_an_action_falls_back_to_the_edit_capability(): void {
		$this->register(
			[ 'capability' => 'read', 'capabilities' => [ 'edit' => 'edit_things' ] ],
			[],
			$ran
		);

		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'permission' );

		try {
			$this->follow();
		} finally {
			$this->assertNull( $ran );
		}
	}

	/**
	 * With it, the action runs.
	 */
	public function test_the_edit_capability_is_enough(): void {
		$this->register(
			[ 'capability' => 'read', 'capabilities' => [ 'edit' => 'edit_things' ] ],
			[],
			$ran
		);

		$GLOBALS['rt_caps'] = [ 'read', 'edit_things' ];

		$this->follow();

		$this->assertSame( 5, $ran );
	}

	/**
	 * An action's own capability wins over the table's.
	 */
	public function test_an_action_can_demand_its_own_capability(): void {
		$this->register(
			[ 'capability' => 'read', 'capabilities' => [ 'edit' => 'edit_things' ] ],
			[ 'capability' => 'archive_things' ],
			$ran
		);

		// The table's edit capability, which is not the action's.
		$GLOBALS['rt_caps'] = [ 'read', 'edit_things' ];

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'permission' );

		$this->follow();
	}
}
