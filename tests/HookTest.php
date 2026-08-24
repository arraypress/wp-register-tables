<?php
/**
 * Hook tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every hook is scoped by table id, and that is the point.
 *
 * These names are not derived from the namespace, so a Strauss-prefixed copy
 * fires them unchanged. That is what makes them reachable by a third party —
 * and what makes an unscoped one shared between every plugin on the site that
 * bundles this library. A filter meant for one plugin's orders table applied
 * to another's.
 *
 * There were twenty-nine unscoped ones, twenty-one of which were per-column
 * formatters doing a third time what a column's own `callback` and `value`
 * already do.
 */
final class HookTest extends TestCase {

	/**
	 * The library's own source.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function sourceProvider(): array {
		$files = [];

		foreach ( glob( dirname( __DIR__ ) . '/src/*.php' ) as $path ) {
			$files[ basename( $path ) ] = [ $path ];
		}

		return $files;
	}

	/**
	 * Every hook this library fires carries a table id.
	 *
	 * @dataProvider sourceProvider
	 *
	 * @param string $path A source file.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'sourceProvider' )]
	public function test_every_hook_is_scoped_to_a_table( string $path ): void {
		$unscoped = [];

		preg_match_all(
			"/(?:apply_filters|do_action)\(\s*'(arraypress_[a-z_]+)'/",
			(string) file_get_contents( $path ),
			$matches
		);

		$unscoped = $matches[1];

		$this->assertSame(
			[],
			$unscoped,
			sprintf(
				'%s fires %s with no table id, so every bundled copy of this library shares it.',
				basename( $path ),
				implode( ', ', $unscoped )
			)
		);
	}

	/**
	 * There is no filter for formatting a column.
	 *
	 * A column that wants different formatting says so in its own
	 * configuration — `callback` renders the cell, `value` transforms what
	 * gets formatted. Twenty-one filters were a third way to do it, and the
	 * only one that leaked between plugins.
	 */
	public function test_there_are_no_column_format_filters(): void {
		foreach ( self::sourceProvider() as [ $path ] ) {
			$this->assertStringNotContainsString(
				'arraypress_column_format_',
				(string) file_get_contents( $path ),
				sprintf( '%s has a column formatting filter again.', basename( $path ) )
			);
		}
	}

	/**
	 * The README documents what the library fires, and nothing else.
	 *
	 * A documented hook that nothing fires is worse than an undocumented one:
	 * somebody writes against it and it silently never runs.
	 */
	public function test_the_readme_and_the_source_agree(): void {
		$documented = self::names( (string) file_get_contents( dirname( __DIR__ ) . '/README.md' ) );
		$fired      = [];

		foreach ( self::sourceProvider() as [ $path ] ) {
			$fired = array_merge( $fired, self::names( (string) file_get_contents( $path ) ) );
		}

		$fired = array_values( array_unique( $fired ) );

		sort( $documented );
		sort( $fired );

		$this->assertSame(
			$fired,
			$documented,
			sprintf(
				"Documented but never fired: %s\nFired but not documented: %s",
				implode( ', ', array_diff( $documented, $fired ) ) ?: 'none',
				implode( ', ', array_diff( $fired, $documented ) ) ?: 'none'
			)
		);
	}

	/**
	 * The hook names in a piece of text, with the variable parts removed.
	 *
	 * `{table_id}` in the README and `{$this->id}` in the source are the same
	 * hole, so both are stripped before comparing.
	 *
	 * @param string $text Source or markdown.
	 *
	 * @return string[]
	 */
	private static function names( string $text ): array {
		preg_match_all( '/arraypress_[a-z_]+(?:\{[^}]+\}[a-z_]*)*/', $text, $matches );

		$names = [];

		foreach ( $matches[0] as $name ) {
			$names[] = rtrim( (string) preg_replace( '/\{[^}]+\}/', '', $name ), '_' );
		}

		return array_values( array_unique( $names ) );
	}
}
