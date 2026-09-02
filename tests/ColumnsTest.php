<?php
/**
 * Column formatting tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Columns;
use PHPUnit\Framework\TestCase;

/**
 * This library is a list-table builder, not a field renderer, so it is the
 * one of the set that does not belong on wp-field-kit. What it does have is
 * 1,425 lines of column formatting reached entirely by *guessing from a
 * column's name* — `email` is an email, `_at` is a date, `amount` is money —
 * and nothing asserting any of it.
 *
 * Detection by convention is convenient and quietly fragile: a column called
 * `status_updated_at` is a date and a column called `email_verified` is a
 * boolean, and both look like something else to a rule written for the other.
 * These pin the rules down so changing one is a decision.
 */
final class ColumnsTest extends TestCase {

	/**
	 * Reset the stubbed globals.
	 */
	protected function setUp(): void {
		rt_reset_globals();
	}

	/**
	 * A column's type is guessed from its name.
	 *
	 * @dataProvider detectionProvider
	 *
	 * @param string      $column   Column name.
	 * @param string|null $expected The type it should be taken for.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'detectionProvider' )]
	public function test_a_type_is_detected_from_the_column_name( string $column, ?string $expected ): void {
		$this->assertSame( $expected, Columns::detect_type( $column ) );
	}

	/**
	 * One column name per rule the detector applies.
	 *
	 * @return array<string, array{0: string, 1: string|null}>
	 */
	public static function detectionProvider(): array {
		return [
			'email'            => [ 'email', 'email' ],
			'user email'       => [ 'user_email', 'email' ],
			'date suffix'      => [ 'created_at', 'date' ],
			'date exact'       => [ 'date_created', 'date' ],
			'status'           => [ 'status', 'status' ],
			'a plain name'     => [ 'title', null ],
			'not a known type' => [ 'some_arbitrary_column', null ],
		];
	}

	/**
	 * is_type() is the same question asked the other way round.
	 */
	public function test_is_type_agrees_with_detection(): void {
		$this->assertTrue( Columns::is_type( 'user_email', 'email' ) );
		$this->assertFalse( Columns::is_type( 'user_email', 'date' ) );
		$this->assertFalse( Columns::is_type( 'title', 'email' ) );
	}

	/**
	 * Emptiness is what decides whether a column formats at all.
	 *
	 * Zero is not empty. A count of nought, a price of nought and a rate of
	 * nought are all values, and treating them as absent would print a dash
	 * where a number belongs — which is the mistake this rule exists to avoid.
	 *
	 * Two edges are the library's own decisions rather than obvious ones, so
	 * they are pinned here: `false` counts as empty, and an empty array does
	 * not. The array case is deliberate — auto_format() exempts `items` from
	 * the check entirely, so a list column formats its own emptiness.
	 */
	public function test_what_counts_as_empty(): void {
		$this->assertFalse( Columns::is_empty( 0 ) );
		$this->assertFalse( Columns::is_empty( '0' ) );
		$this->assertFalse( Columns::is_empty( 0.0 ) );
		$this->assertFalse( Columns::is_empty( [] ) );

		$this->assertTrue( Columns::is_empty( '' ) );
		$this->assertTrue( Columns::is_empty( null ) );
		$this->assertTrue( Columns::is_empty( false ) );
	}

	/**
	 * An empty column renders a placeholder, not a blank cell.
	 *
	 * A blank cell in a list table reads as a column that failed to load.
	 */
	public function test_an_empty_column_renders_a_placeholder(): void {
		$this->assertNotSame( '', Columns::render_empty() );
	}

	/**
	 * An email is a mailto link, and is escaped.
	 */
	public function test_an_email_is_a_link(): void {
		$html = Columns::format_email( 'someone@example.test', [], 'email' );

		$this->assertStringContainsString( 'mailto:someone@example.test', $html );
		$this->assertStringContainsString( 'someone@example.test', $html );
	}

	/**
	 * A value a consumer supplied is escaped.
	 *
	 * Every formatter here takes a value straight out of a database row, and
	 * a list table prints hundreds of them.
	 */
	public function test_a_formatted_value_is_escaped(): void {
		$html = Columns::format_email( '<script>alert(1)</script>@example.test', [], 'email' );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * A status renders with a class derived from its own value.
	 *
	 * Which is why it goes through sanitize_html_class: a status is whatever
	 * the row holds, and it lands in a class attribute.
	 */
	public function test_a_status_class_is_safe(): void {
		$html = Columns::format_status( 'not a class"><script>', [], 'status' );

		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * An empty value renders the placeholder, whatever the column is.
	 *
	 * Through auto_format() rather than the formatters, because that is where
	 * the check lives — a formatter reached directly with an empty value is
	 * out of contract, and asserting otherwise would pin down something the
	 * library never promised.
	 *
	 * @dataProvider emptyColumnProvider
	 *
	 * @param string $column A column whose type is detected.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'emptyColumnProvider' )]
	public function test_an_empty_value_renders_the_placeholder( string $column ): void {
		$this->assertSame(
			Columns::render_empty(),
			Columns::auto_format( $column, '', (object) [] ),
			sprintf( 'An empty %s column does not render the placeholder.', $column )
		);
	}

	/**
	 * One column of each detected type.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function emptyColumnProvider(): array {
		// Columns whose type is detected. A column with no detected type is
		// not formatted at all — auto_format() escapes it and returns — so it
		// has no placeholder to render and belongs in its own assertion.
		$columns = [ 'email', 'created_at', 'status' ];

		return array_combine(
			$columns,
			array_map( static fn( $one ) => [ $one ], $columns )
		);
	}

	/**
	 * A column with no detected type is escaped and left alone.
	 *
	 * Not formatted, and not replaced with a placeholder either: the library
	 * has nothing to say about it, so it says nothing.
	 */
	public function test_an_undetected_column_is_only_escaped(): void {
		$this->assertSame(
			'&lt;b&gt;raw&lt;/b&gt;',
			Columns::auto_format( 'title', '<b>raw</b>', (object) [] )
		);
	}

	/**
	 * Formatting can be turned off for a column that would be detected.
	 */
	public function test_formatting_can_be_disabled(): void {
		$this->assertSame(
			'someone@example.test',
			Columns::auto_format( 'email', 'someone@example.test', (object) [], [ 'type' => false ] )
		);
	}

	/**
	 * A zero-valued column is formatted, not treated as absent.
	 */
	public function test_a_zero_value_is_formatted(): void {
		$this->assertNotSame(
			Columns::render_empty(),
			Columns::auto_format( 'count', 0, (object) [] )
		);
	}

	/**
	 * A colour column paints its swatch only from a colour.
	 *
	 * The value lands in a style attribute, and a style attribute is a place
	 * a stored string can do more than colour a box. Anything that is not a
	 * hex or an rgb() triple is printed and not painted.
	 */
	public function test_a_swatch_is_painted_only_from_a_colour(): void {
		$this->assertStringContainsString(
			'background-color:#ff0000;',
			Columns::format_color( '#ff0000', [], 'color' )
		);
		$this->assertStringContainsString(
			'background-color:rgb(255, 0, 0);',
			Columns::format_color( 'rgb(255, 0, 0)', [], 'color' )
		);

		$html = Columns::format_color( 'red;background:url(evil)', [], 'color' );

		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringContainsString( 'red;background:url(evil)', $html );
	}

	/**
	 * A date is rendered in the site's format, not a hardcoded one.
	 */
	public function test_a_date_uses_the_sites_format(): void {
		$GLOBALS['rt_options']['date_format'] = 'Y/m/d';

		$html = Columns::format_date( '2026-08-24 12:00:00', [], 'created_at' );

		$this->assertStringContainsString( '2026/08/24', $html );
	}

	/**
	 * A price column renders the row's own currency.
	 *
	 * The test that was missing when this trait was rewritten: nothing
	 * exercised resolve_currency(), so a call to a method that does not exist
	 * -- Money::supports() rather than Currencies::supports() -- sat in the
	 * file and the suite stayed green. It would have fatalled on the first
	 * row that carried a currency.
	 */
	public function test_a_price_column_uses_the_rows_currency(): void {
		$row = (object) [ 'total' => 4999 ];

		$this->assertStringContainsString(
			'$49.99',
			Columns::auto_format( 'total', 4999, $row, [ 'type' => 'price' ] )
		);

		// A row that names its own currency is formatted in it, decimals and
		// all -- 1000 is a thousand yen, not ten.
		$japanese = new class() {
			/**
			 * @return string
			 */
			public function get_currency(): string {
				return 'JPY';
			}
		};

		$rendered = Columns::auto_format( 'total', 1000, $japanese, [ 'type' => 'price' ] );

		$this->assertStringContainsString( '1,000', $rendered );
		$this->assertStringNotContainsString( '10.00', $rendered );
	}

	/**
	 * A rate column asks the row whether it is a percentage.
	 *
	 * A discount of 20 is twenty percent or twenty pence and the number
	 * cannot say which.
	 */
	public function test_a_rate_column_reads_the_rows_type(): void {
		$percent = (object) [ 'rate' => 20, 'rate_type' => 'percent' ];
		$flat    = (object) [ 'rate' => 20, 'rate_type' => 'flat' ];

		$this->assertStringContainsString( '20%', Columns::auto_format( 'rate', 20, $percent, [ 'type' => 'rate' ] ) );
		$this->assertStringContainsString( '0.20', Columns::auto_format( 'rate', 20, $flat, [ 'type' => 'rate' ] ) );
	}
}
