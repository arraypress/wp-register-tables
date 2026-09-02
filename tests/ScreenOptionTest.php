<?php
/**
 * Screen option tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Saving the "items per page" preference, and nobody else's.
 *
 * Core hands every screen option on the site through one filter and expects
 * each plugin to claim its own. Claiming everything ending in `_per_page`
 * saved other plugins' options as integers whether or not that was what
 * they held -- and their own filter, running after, never saw them.
 */
final class ScreenOptionTest extends TestCase {

	/**
	 * A clean slate, with this library's filter back on it.
	 *
	 * The filter is added once, when the first table registers, and the
	 * reset between tests forgets it -- so it is put back by hand.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		Manager::register( 'demo', [ 'columns' => [ 'name' => 'Name' ] ] );

		( new ReflectionMethod( Manager::class, 'handle_screen_options' ) )->invoke( null );
	}

	/**
	 * The table's own option is saved, as a number.
	 */
	public function test_the_tables_option_is_saved(): void {
		$this->assertSame( 25, apply_filters( 'set-screen-option', false, 'demo_per_page', '25' ) );
	}

	/**
	 * Another plugin's is left alone.
	 */
	public function test_another_plugins_option_is_left_alone(): void {
		$this->assertFalse( apply_filters( 'set-screen-option', false, 'edit_post_per_page', '25' ) );
	}
}
