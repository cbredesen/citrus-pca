<?php

use PHPUnit\Framework\TestCase;

class ReportParserTest extends TestCase {

	private string $valid_csv;
	private string $invalid_csv;
	private string $bulk_csv;

	protected function setUp(): void {
		$this->valid_csv   = __DIR__ . '/../data/report-valid.csv';
		$this->invalid_csv = __DIR__ . '/../data/report-invalid.csv';
		$this->bulk_csv     = __DIR__ . '/../data/FLC-Active-and-Expired-Members - TEST ANON.csv';
	}

	// -------------------------------------------------------------------------
	// Valid file — basic structure
	// -------------------------------------------------------------------------

	public function test_valid_file_produces_no_errors(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_valid_file_returns_expected_shape(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertArrayHasKey( 'members', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'skipped_chapters', $result );
		$this->assertArrayHasKey( 'skipped_inactive', $result );
	}

	// -------------------------------------------------------------------------
	// Valid file — report-valid.csv has:
	//   - 20 Active/FLC rows forming the May & January 2026 milestone sets
	//   - 1 Active/FLC row (Nadia Offcycle) whose join year isn't a milestone
	//   - 2 rows excluded by status (Expired, Lapsed)
	//   - 2 rows excluded by chapter (GC, SFL)
	// The parser itself doesn't filter by month/milestone-year — only by
	// chapter and status — so 21 members come back (20 milestone + 1 off-cycle).
	// -------------------------------------------------------------------------

	public function test_valid_file_member_count(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertCount( 21, $result['members'] );
	}

	public function test_valid_file_members_contain_only_non_pii_fields(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		foreach ( $result['members'] as $member ) {
			$this->assertSame( [ 'first_name', 'last_initial', 'anniversary_date' ], array_keys( $member ) );
		}
	}

	public function test_valid_file_last_initial_is_single_character(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		foreach ( $result['members'] as $member ) {
			$this->assertSame( 1, mb_strlen( $member['last_initial'] ) );
		}
	}

	public function test_valid_file_excludes_non_active_status(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertSame( 2, $result['skipped_inactive'] );

		$first_names = array_column( $result['members'], 'first_name' );
		$this->assertNotContains( 'Edith', $first_names );
		$this->assertNotContains( 'Larry', $first_names );
	}

	public function test_valid_file_excludes_other_chapters_and_reports_them(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertSame( [ 'GC' => 1, 'SFL' => 1 ], $result['skipped_chapters'] );

		$first_names = array_column( $result['members'], 'first_name' );
		$this->assertNotContains( 'Gary', $first_names );
		$this->assertNotContains( 'Sonia', $first_names );
	}

	public function test_multiple_allowed_chapters_are_all_included(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC', 'GC', 'SFL' ] );
		$this->assertEmpty( $result['skipped_chapters'] );
		$this->assertCount( 23, $result['members'] ); // 21 FLC + Gary (GC) + Sonia (SFL)
	}

	public function test_chapter_matching_is_case_insensitive(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'flc' ] );
		$this->assertCount( 21, $result['members'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'valid_may_2026_provider' )]
	public function test_valid_may_2026_milestones( int $years, string $first_name, string $last_initial ): void {
		$result     = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$milestones = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $result['members'], 5, 2026 );
		$this->assertArrayHasKey( $years, $milestones );
		$this->assertContains( "$first_name $last_initial.", $milestones[ $years ] );
	}

	public static function valid_may_2026_provider(): array {
		return [
			'5yr  Marcus Dubois'    => [ 5,  'Marcus', 'D' ],
			'10yr Alec Stanton'     => [ 10, 'Alec', 'S' ],
			'15yr Opal Sorensen'    => [ 15, 'Opal', 'S' ],
			'20yr Christine Mwangi' => [ 20, 'Christine', 'M' ],
			'25yr Quincy Stanton'   => [ 25, 'Quincy', 'S' ],
			'30yr Eugene Tanaka'    => [ 30, 'Eugene', 'T' ],
			'35yr Peter Watanabe'   => [ 35, 'Peter', 'W' ],
			'40yr Dennis Holbrook'  => [ 40, 'Dennis', 'H' ],
			'45yr Rachel Nwosu'     => [ 45, 'Rachel', 'N' ],
			'50yr Frank Nakamura'   => [ 50, 'Frank', 'N' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'valid_jan_2026_provider' )]
	public function test_valid_jan_2026_milestones( int $years, string $first_name, string $last_initial ): void {
		$result     = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$milestones = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $result['members'], 1, 2026 );
		$this->assertArrayHasKey( $years, $milestones );
		$this->assertContains( "$first_name $last_initial.", $milestones[ $years ] );
	}

	public static function valid_jan_2026_provider(): array {
		return [
			'5yr  Ian Tanaka'       => [ 5,  'Ian', 'T' ],
			'10yr Willa Osei'       => [ 10, 'Willa', 'O' ],
			'15yr Kara Okeke'       => [ 15, 'Kara', 'O' ],
			'20yr Yolanda Dubois'   => [ 20, 'Yolanda', 'D' ],
			'25yr Michael Guerrero' => [ 25, 'Michael', 'G' ],
			'30yr Arnold Nakamura'  => [ 30, 'Arnold', 'N' ],
			'35yr Lewis Fujimoto'   => [ 35, 'Lewis', 'F' ],
			'40yr Zachary Petrov'   => [ 40, 'Zachary', 'P' ],
			'45yr Norbert Castillo' => [ 45, 'Norbert', 'C' ],
			'50yr Alice Thornton'   => [ 50, 'Alice', 'T' ],
		];
	}

	public function test_offcycle_year_never_forms_a_milestone(): void {
		$result     = FLC_Anniversaries_Report_Parser::parse( $this->valid_csv, [ 'FLC' ] );
		$this->assertContains( 'Nadia', array_column( $result['members'], 'first_name' ), 'Nadia should still be ingested as a member' );

		$milestones  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $result['members'], 5, 2026 );
		$all_names   = array_merge( ...array_values( $milestones ) );
		$this->assertNotContains( 'Nadia O.', $all_names );
	}

	// -------------------------------------------------------------------------
	// File-level errors
	// -------------------------------------------------------------------------

	public function test_missing_file_returns_key_zero_error(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( '/tmp/does-not-exist-report.csv', [ 'FLC' ] );
		$this->assertArrayHasKey( 0, $result['errors'] );
		$this->assertEmpty( $result['members'] );
	}

	// -------------------------------------------------------------------------
	// Line-ending / encoding robustness
	//
	// fgetcsv() only recognizes \n. A file with classic-Mac (\r-only) line
	// endings — which some export tools produce — would otherwise be read as
	// a single line, silently yielding zero rows with zero errors: exactly
	// the symptom of an empty-looking upload. The parser normalizes line
	// endings before parsing to guard against this.
	// -------------------------------------------------------------------------

	/**
	 * Writes $source_path's content to a throwaway file with every line
	 * ending converted to $newline, and returns the throwaway path.
	 */
	private function with_line_ending( string $source_path, string $newline ): string {
		$normalized = str_replace( "\r\n", "\n", file_get_contents( $source_path ) );
		$converted  = str_replace( "\n", $newline, $normalized );

		$tmp_path = sys_get_temp_dir() . '/flc-line-ending-test-' . uniqid() . '.csv';
		file_put_contents( $tmp_path, $converted );

		return $tmp_path;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'line_ending_provider' )]
	public function test_valid_file_parses_regardless_of_line_ending_style( string $newline ): void {
		$tmp_path = $this->with_line_ending( $this->valid_csv, $newline );

		try {
			$result = FLC_Anniversaries_Report_Parser::parse( $tmp_path, [ 'FLC' ] );
			$this->assertCount( 21, $result['members'] );
			$this->assertEmpty( $result['errors'] );
		} finally {
			unlink( $tmp_path );
		}
	}

	/**
	 * The line-ending case that matters most in practice: an upload that
	 * *isn't* clean. Line numbers, error messages, and the members that do
	 * parse must all stay identical to the LF baseline regardless of which
	 * line ending the file actually arrived with.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'line_ending_provider' )]
	public function test_invalid_file_errors_are_identical_regardless_of_line_ending_style( string $newline ): void {
		$baseline = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );

		$tmp_path = $this->with_line_ending( $this->invalid_csv, $newline );

		try {
			$result = FLC_Anniversaries_Report_Parser::parse( $tmp_path, [ 'FLC' ] );
			$this->assertSame( $baseline['errors'], $result['errors'] );
			$this->assertSame( $baseline['members'], $result['members'] );
			$this->assertSame( $baseline['skipped_chapters'], $result['skipped_chapters'] );
			$this->assertSame( $baseline['skipped_inactive'], $result['skipped_inactive'] );
		} finally {
			unlink( $tmp_path );
		}
	}

	public static function line_ending_provider(): array {
		return [
			'CR only (classic Mac)' => [ "\r" ],
			'CRLF (Windows)'        => [ "\r\n" ],
			'LF (Unix)'             => [ "\n" ],
		];
	}

	public function test_strips_leading_utf8_bom(): void {
		$tmp_path = sys_get_temp_dir() . '/flc-bom-test-' . uniqid() . '.csv';
		file_put_contents( $tmp_path, "\xEF\xBB\xBF" . file_get_contents( $this->valid_csv ) );

		try {
			$result = FLC_Anniversaries_Report_Parser::parse( $tmp_path, [ 'FLC' ] );
			$this->assertCount( 21, $result['members'] );
			$this->assertEmpty( $result['errors'] );
		} finally {
			unlink( $tmp_path );
		}
	}

	// -------------------------------------------------------------------------
	// Invalid file — the parser must not throw; it collects row-level errors
	// and still returns whatever valid rows exist.
	//
	// Line numbers in report-invalid.csv (1-based, header = line 1):
	//   2  → missing STATUS
	//   3  → missing LAST_NAME
	//   4  → missing FIRST_NAME
	//   5  → missing ANNIVERSARY_DATE
	//   6  → non-date string
	//   7  → ISO date format
	//   8  → dash separators
	//   9  → month 13
	//  10  → month 0
	//  11  → Feb 30
	//  12  → valid (Harold Moon, 10yr milestone in May 2026)
	//  13  → valid (Irene Voss, 5yr milestone in May 2026)
	//  14  → Expired status — excluded, not an error
	//  15  → non-FLC chapter — excluded, not an error
	//  16  → blank line — skipped silently
	//  17  → wrong column count (10 instead of 27)
	//  18  → unquoted comma shifts columns (28 instead of 27)
	//  19  → month 0 (single-digit variant)
	// -------------------------------------------------------------------------

	public function test_invalid_file_does_not_throw(): void {
		$this->expectNotToPerformAssertions();
		FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
	}

	public function test_invalid_file_has_no_key_zero_error(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$this->assertArrayNotHasKey( 0, $result['errors'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_error_line_provider' )]
	public function test_invalid_specific_line_has_error( int $line ): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$this->assertArrayHasKey( $line, $result['errors'], "Expected an error at line $line" );
	}

	public static function invalid_error_line_provider(): array {
		return [
			'line 2  missing STATUS'      => [ 2 ],
			'line 3  missing LAST_NAME'   => [ 3 ],
			'line 4  missing FIRST_NAME'  => [ 4 ],
			'line 5  missing ANNIVERSARY' => [ 5 ],
			'line 6  non-date string'     => [ 6 ],
			'line 7  ISO date format'     => [ 7 ],
			'line 8  dash separators'     => [ 8 ],
			'line 9  month 13'            => [ 9 ],
			'line 10 month 0'             => [ 10 ],
			'line 11 Feb 30'              => [ 11 ],
			'line 17 wrong column count'  => [ 17 ],
			'line 18 unquoted comma'      => [ 18 ],
			'line 19 month 0 variant'     => [ 19 ],
		];
	}

	public function test_invalid_error_count(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$this->assertCount( 13, $result['errors'] );
	}

	public function test_invalid_error_messages_include_line_number(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		foreach ( $result['errors'] as $line => $message ) {
			if ( $line === 0 ) {
				continue;
			}
			$this->assertStringContainsString( "Line $line:", $message );
		}
	}

	public function test_invalid_error_messages_contain_no_pii(): void {
		// Error messages must never leak a name, email, or address from the
		// uploaded report -- only the line number and a generic reason.
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$needles = [ 'Alpha', 'Amy', 'Carter', 'Diaz', 'Dana', 'Ellis', 'Eve', 'Frost', 'Finn', 'Gomez', 'Gail', 'Haas', 'Hugo', 'Ito', 'Ivy', 'Jara', 'Jon', '@nodomain.com' ];
		foreach ( $result['errors'] as $message ) {
			foreach ( $needles as $needle ) {
				$this->assertStringNotContainsString( $needle, $message );
			}
		}
	}

	public function test_invalid_file_valid_rows_still_produce_members(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$this->assertCount( 2, $result['members'] );

		$milestones = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $result['members'], 5, 2026 );
		$this->assertContains( 'Harold M.', $milestones[10] );
		$this->assertContains( 'Irene V.', $milestones[5] );
	}

	public function test_invalid_file_excludes_inactive_and_other_chapter_without_erroring(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->invalid_csv, [ 'FLC' ] );
		$this->assertSame( 1, $result['skipped_inactive'] );
		$this->assertSame( [ 'GC' => 1 ], $result['skipped_chapters'] );
	}

	// -------------------------------------------------------------------------
	// Bulk/volume fixture — 1000+ synthetic rows spanning many chapters,
	// statuses, and years. Not asserted against hardcoded names; instead
	// cross-checked against an independent tally of the raw CSV so the test
	// stays meaningful without being brittle.
	// -------------------------------------------------------------------------

	public function test_bulk_file_parses_without_error_and_matches_independent_tally(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->bulk_csv, [ 'FLC' ] );
		$this->assertEmpty( $result['errors'], 'The bulk fixture is expected to be well-formed' );

		[ $expected_active_flc, $expected_other_chapters, $expected_inactive_flc ] = $this->tally_raw_csv( $this->bulk_csv );

		$this->assertCount( $expected_active_flc, $result['members'] );
		$this->assertSame( $expected_inactive_flc, $result['skipped_inactive'] );
		$this->assertSame( $expected_other_chapters, $result['skipped_chapters'] );

		// Sanity: the bulk fixture is large enough, and mixed enough, to be a
		// meaningful volume test rather than a coincidentally-small sample.
		$this->assertGreaterThan( 300, $result['members'] === [] ? 0 : count( $result['members'] ) );
		$this->assertNotEmpty( $expected_other_chapters );
		$this->assertGreaterThan( 0, $expected_inactive_flc );
	}

	public function test_bulk_file_members_contain_only_non_pii_fields(): void {
		$result = FLC_Anniversaries_Report_Parser::parse( $this->bulk_csv, [ 'FLC' ] );
		foreach ( $result['members'] as $member ) {
			$this->assertSame( [ 'first_name', 'last_initial', 'anniversary_date' ], array_keys( $member ) );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $member['anniversary_date'] );
		}
	}

	/**
	 * Independently tallies the raw CSV (outside the class under test) to
	 * cross-check the parser's counts without duplicating its logic.
	 *
	 * @return array{0: int, 1: array<string,int>, 2: int}
	 */
	private function tally_raw_csv( string $path ): array {
		$handle = fopen( $path, 'r' );
		fgetcsv( $handle, 0, ',', '"', '' ); // header

		$active_flc     = 0;
		$other_chapters = [];
		$inactive_flc   = 0;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			if ( $row === [ null ] ) {
				continue;
			}
			$chapter = trim( $row[0] );
			$status  = trim( $row[5] );

			if ( strtoupper( $chapter ) !== 'FLC' ) {
				$other_chapters[ $chapter ] = ( $other_chapters[ $chapter ] ?? 0 ) + 1;
				continue;
			}

			if ( strcasecmp( $status, 'Active' ) === 0 ) {
				++$active_flc;
			} else {
				++$inactive_flc;
			}
		}

		fclose( $handle );

		return [ $active_flc, $other_chapters, $inactive_flc ];
	}
}
