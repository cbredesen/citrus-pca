<?php

use PHPUnit\Framework\TestCase;

class RosterParserTest extends TestCase {

	private string $valid_csv;
	private string $invalid_csv;

	protected function setUp(): void {
		$this->valid_csv   = __DIR__ . '/../data/roster-valid.csv';
		$this->invalid_csv = __DIR__ . '/../data/roster-invalid.csv';
	}

	// -------------------------------------------------------------------------
	// Valid file — basic structure
	// -------------------------------------------------------------------------

	public function test_valid_file_produces_no_errors(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 1, 2026 );
		$this->assertEmpty( $result['errors'], 'Expected no parse errors in the valid CSV' );
	}

	public function test_valid_file_returns_milestones_array(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2026 );
		$this->assertIsArray( $result['milestones'] );
		$this->assertNotEmpty( $result['milestones'] );
	}

	public function test_valid_file_milestones_sorted_ascending(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2026 );
		$keys   = array_keys( $result['milestones'] );
		$sorted = $keys;
		sort( $sorted );
		$this->assertSame( $sorted, $keys, 'Milestone groups should be sorted by years ascending' );
	}

	// -------------------------------------------------------------------------
	// Valid file — May 2026 milestone content
	// roster-valid.csv has one member per milestone group in May:
	//   5yr  → Marcus Dubois    (5/1/2021)
	//   10yr → Alec Stanton     (5/1/2016)
	//   15yr → Opal Sorensen    (5/1/2011)
	//   20yr → Christine Mwangi (5/1/2006)
	//   25yr → Quincy Stanton   (5/1/2001)
	//   30yr → Eugene Tanaka    (5/1/1996)
	//   35yr → Peter Watanabe   (5/1/1991)
	//   40yr → Dennis Holbrook  (5/1/1986)
	//   45yr → Rachel Nwosu     (5/1/1981)
	//   50yr → Frank Nakamura   (5/1/1976)
	// -------------------------------------------------------------------------

	public function test_valid_may_2026_has_ten_milestone_groups(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2026 );
		$this->assertCount( 10, $result['milestones'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'valid_may_2026_provider' )]
	public function test_valid_may_2026_specific_names( int $years, string $name ): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2026 );
		$this->assertArrayHasKey( $years, $result['milestones'] );
		$this->assertContains( $name, $result['milestones'][ $years ] );
	}

	public static function valid_may_2026_provider(): array {
		return [
			'5yr  Marcus Dubois'    => [ 5,  'Marcus Dubois' ],
			'10yr Alec Stanton'     => [ 10, 'Alec Stanton' ],
			'15yr Opal Sorensen'    => [ 15, 'Opal Sorensen' ],
			'20yr Christine Mwangi' => [ 20, 'Christine Mwangi' ],
			'25yr Quincy Stanton'   => [ 25, 'Quincy Stanton' ],
			'30yr Eugene Tanaka'    => [ 30, 'Eugene Tanaka' ],
			'35yr Peter Watanabe'   => [ 35, 'Peter Watanabe' ],
			'40yr Dennis Holbrook'  => [ 40, 'Dennis Holbrook' ],
			'45yr Rachel Nwosu'     => [ 45, 'Rachel Nwosu' ],
			'50yr Frank Nakamura'   => [ 50, 'Frank Nakamura' ],
		];
	}

	// -------------------------------------------------------------------------
	// Valid file — January 2026 milestone content
	// roster-valid.csv has one member per milestone group in January:
	//   5yr  → Ian Tanaka     (1/1/2021)
	//   10yr → Willa Osei     (1/1/2016)
	//   15yr → Kara Okeke     (1/1/2011)
	//   20yr → Yolanda Dubois (1/1/2006)
	//   25yr → Michael Guerrero (1/1/2001)
	//   30yr → Arnold Nakamura (1/1/1996)
	//   35yr → Lewis Fujimoto (1/1/1991)
	//   40yr → Zachary Petrov (1/1/1986)
	//   45yr → Norbert Castillo (1/1/1981)
	//   50yr → Alice Thornton  (1/1/1976)
	// -------------------------------------------------------------------------

	public function test_valid_jan_2026_has_ten_milestone_groups(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 1, 2026 );
		$this->assertCount( 10, $result['milestones'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'valid_jan_2026_provider' )]
	public function test_valid_jan_2026_specific_names( int $years, string $name ): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 1, 2026 );
		$this->assertArrayHasKey( $years, $result['milestones'] );
		$this->assertContains( $name, $result['milestones'][ $years ] );
	}

	public static function valid_jan_2026_provider(): array {
		return [
			'5yr  Ian Tanaka'       => [ 5,  'Ian Tanaka' ],
			'10yr Willa Osei'       => [ 10, 'Willa Osei' ],
			'15yr Kara Okeke'       => [ 15, 'Kara Okeke' ],
			'20yr Yolanda Dubois'   => [ 20, 'Yolanda Dubois' ],
			'25yr Michael Guerrero' => [ 25, 'Michael Guerrero' ],
			'30yr Arnold Nakamura'  => [ 30, 'Arnold Nakamura' ],
			'35yr Lewis Fujimoto'   => [ 35, 'Lewis Fujimoto' ],
			'40yr Zachary Petrov'   => [ 40, 'Zachary Petrov' ],
			'45yr Norbert Castillo' => [ 45, 'Norbert Castillo' ],
			'50yr Alice Thornton'   => [ 50, 'Alice Thornton' ],
		];
	}

	// -------------------------------------------------------------------------
	// Valid file — filter correctness
	// -------------------------------------------------------------------------

	public function test_all_milestone_years_are_divisible_by_five(): void {
		foreach ( range( 1, 12 ) as $month ) {
			$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, $month, 2026 );
			foreach ( array_keys( $result['milestones'] ) as $years ) {
				$this->assertSame( 0, $years % 5, "Year $years (month $month) is not divisible by 5" );
				$this->assertGreaterThan( 0, $years );
			}
		}
	}

	public function test_may_members_absent_from_june_results(): void {
		$may_result  = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2026 );
		$june_result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 6, 2026 );

		$may_names  = array_merge( ...array_values( $may_result['milestones'] ) );
		$june_names = array_merge( ...( $june_result['milestones'] ?: [[]] ) );

		foreach ( $may_names as $name ) {
			$this->assertNotContains( $name, $june_names, "$name joined in May but appeared in June results" );
		}
	}

	public function test_non_milestone_year_returns_empty_milestones(): void {
		// 2027 - no one in valid file joined in May of a year that gives a multiple of 5 in 2027.
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->valid_csv, 5, 2027 );
		$this->assertEmpty( $result['milestones'] );
		$this->assertEmpty( $result['errors'] );
	}

	// -------------------------------------------------------------------------
	// File-level errors
	// -------------------------------------------------------------------------

	public function test_missing_file_returns_key_zero_error(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( '/tmp/does-not-exist-roster.csv', 5, 2026 );
		$this->assertArrayHasKey( 0, $result['errors'] );
		$this->assertEmpty( $result['milestones'] );
	}

	public function test_missing_file_error_message_mentions_path(): void {
		$path   = '/tmp/does-not-exist-roster.csv';
		$result = FLC_Anniversaries_Roster_Parser::parse( $path, 5, 2026 );
		$this->assertStringContainsString( $path, $result['errors'][0] );
	}

	// -------------------------------------------------------------------------
	// Invalid file — the parser must not throw; it collects row-level errors
	// -------------------------------------------------------------------------

	public function test_invalid_file_does_not_throw(): void {
		$this->expectNotToPerformAssertions();
		FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
	}

	public function test_invalid_file_returns_array_shape(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		$this->assertArrayHasKey( 'milestones', $result );
		$this->assertArrayHasKey( 'errors', $result );
	}

	public function test_invalid_file_has_no_key_zero_error(): void {
		// The file exists, so there should be no file-level error.
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		$this->assertArrayNotHasKey( 0, $result['errors'] );
	}

	// -------------------------------------------------------------------------
	// Invalid file — specific line-level errors
	//
	// Line numbers in roster-invalid.csv (1-based, header = line 1):
	//   3  → empty name      (",5/1/1996")
	//   4  → empty date      ("Bob Nolan,")
	//   5  → non-date string ("Carol Perez,not-a-date")
	//   6  → ISO format      ("Dave Kim,2026-05-01")
	//   7  → month 13        ("Eve Jackson,13/1/2001")
	//   8  → month 0         ("Frank Burns,0/1/2006")
	//   9  → Feb 30          ("Grace Kelly,2/30/2011")
	//  12  → unquoted comma  ("Jane, Smith,5/1/1991" → CSV splits name/date wrong)
	//  14  → empty row       (",")
	//  19  → non-date string ("Petra Ramos,abc")
	//  20  → dash separators ("Quinn Davis,01-01-2011")
	// -------------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_error_line_provider' )]
	public function test_invalid_specific_line_has_error( int $line ): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		$this->assertArrayHasKey( $line, $result['errors'], "Expected an error at line $line" );
	}

	public static function invalid_error_line_provider(): array {
		return [
			'line 3  empty name'       => [ 3 ],
			'line 4  empty date'       => [ 4 ],
			'line 5  non-date string'  => [ 5 ],
			'line 6  ISO date format'  => [ 6 ],
			'line 7  month 13'         => [ 7 ],
			'line 8  month 0'          => [ 8 ],
			'line 9  Feb 30'           => [ 9 ],
			'line 12 unquoted comma'   => [ 12 ],
			'line 14 empty row'        => [ 14 ],
			'line 19 abc date'         => [ 19 ],
			'line 20 dash separators'  => [ 20 ],
		];
	}

	public function test_invalid_error_messages_include_line_number(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		foreach ( $result['errors'] as $line => $message ) {
			if ( $line === 0 ) {
				continue; // file-level errors have no line prefix
			}
			$this->assertStringContainsString(
				"Line $line:",
				$message,
				"Error message for line $line should contain 'Line $line:'"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Invalid file — valid rows still produce milestones
	//
	//  Line 10: Harold Moon,5/1/2016  → May 2016 → 10yr milestone in May 2026
	//  Line 11: Irene Voss,5/1/2021   → May 2021 → 5yr milestone in May 2026
	// -------------------------------------------------------------------------

	public function test_invalid_file_valid_rows_produce_milestones(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		$this->assertArrayHasKey( 10, $result['milestones'] );
		$this->assertContains( 'Harold Moon', $result['milestones'][10] );
		$this->assertArrayHasKey( 5, $result['milestones'] );
		$this->assertContains( 'Irene Voss', $result['milestones'][5] );
	}

	public function test_invalid_file_errors_do_not_suppress_valid_milestones(): void {
		$result = FLC_Anniversaries_Roster_Parser::parse( $this->invalid_csv, 5, 2026 );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertNotEmpty( $result['milestones'], 'Valid rows should still produce milestones even when errors exist' );
	}
}
