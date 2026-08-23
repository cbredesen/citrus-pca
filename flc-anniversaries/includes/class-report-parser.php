<?php

/**
 * Parses the PCA National region-report CSV export.
 *
 * Pure PHP, no WordPress dependency: given a file path and the list of
 * chapter codes this site cares about, returns the subset of active,
 * in-chapter members reduced to only the fields the anniversaries feature
 * needs (first name, last initial, anniversary date). Every other column
 * in the report -- including all PII (last name, email, address, phone,
 * etc.) -- is read only long enough to make that decision and is never
 * included in the return value.
 */
class FLC_Anniversaries_Report_Parser {

	const EXPECTED_COLUMNS = 27;

	const COL_CHAPTER          = 0;
	const COL_STATUS           = 5;
	const COL_LAST_NAME        = 6;
	const COL_FIRST_NAME       = 7;
	const COL_ANNIVERSARY_DATE = 11;

	/**
	 * @return array{
	 *     members: list<array{first_name: string, last_initial: string, anniversary_date: string}>,
	 *     errors: array<int, string>,
	 *     skipped_chapters: array<string, int>,
	 *     skipped_inactive: int,
	 * }
	 */
	public static function parse( string $csv_path, array $allowed_chapters ): array {
		$allowed_chapters = array_map( static fn( $c ) => strtoupper( trim( (string) $c ) ), $allowed_chapters );

		if ( ! file_exists( $csv_path ) ) {
			return self::empty_result( [ 0 => "File not found: $csv_path" ] );
		}

		$raw = file_get_contents( $csv_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
		if ( $raw === false ) {
			return self::empty_result( [ 0 => "Cannot open file: $csv_path" ] );
		}

		// Exports (Excel, older tools) sometimes use classic-Mac (\r only) or
		// Windows (\r\n) line endings. fgetcsv() only recognizes \n, so a
		// \r-only file would otherwise be read as a single line and silently
		// yield zero rows. Normalize before parsing, and strip a leading
		// UTF-8 BOM some exporters prepend.
		$raw = str_replace( [ "\r\n", "\r" ], "\n", $raw );
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $handle, $raw );
		rewind( $handle );

		fgetcsv( $handle, 0, ',', '"', '' ); // skip header row

		$members          = [];
		$errors           = [];
		$skipped_chapters = [];
		$skipped_inactive = 0;
		$line             = 1;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line++;

			if ( $row === [ null ] ) { // blank line
				continue;
			}

			if ( count( $row ) !== self::EXPECTED_COLUMNS ) {
				$errors[ $line ] = "Line $line: expected " . self::EXPECTED_COLUMNS . ' columns, got ' . count( $row );
				continue;
			}

			$chapter    = trim( $row[ self::COL_CHAPTER ] );
			$status     = trim( $row[ self::COL_STATUS ] );
			$last_name  = trim( $row[ self::COL_LAST_NAME ] );
			$first_name = trim( $row[ self::COL_FIRST_NAME ] );
			$date_str   = trim( $row[ self::COL_ANNIVERSARY_DATE ] );

			if ( $chapter === '' ) {
				$errors[ $line ] = "Line $line: chapter is empty";
				continue;
			}

			if ( $status === '' ) {
				$errors[ $line ] = "Line $line: status is empty";
				continue;
			}

			if ( $last_name === '' ) {
				$errors[ $line ] = "Line $line: last name is empty";
				continue;
			}

			if ( $first_name === '' ) {
				$errors[ $line ] = "Line $line: first name is empty";
				continue;
			}

			if ( $date_str === '' ) {
				$errors[ $line ] = "Line $line: anniversary date is empty";
				continue;
			}

			$date         = DateTime::createFromFormat( 'm/d/Y', $date_str );
			$parse_errors = DateTime::getLastErrors();

			if ( ! $date || ( $parse_errors !== false && ( $parse_errors['error_count'] > 0 || $parse_errors['warning_count'] > 0 ) ) ) {
				$errors[ $line ] = "Line $line: invalid anniversary date '$date_str'";
				continue;
			}

			// Not an error: rows for chapters outside our configured list, or
			// members whose STATUS isn't Active, are simply excluded.
			if ( ! in_array( strtoupper( $chapter ), $allowed_chapters, true ) ) {
				$skipped_chapters[ $chapter ] = ( $skipped_chapters[ $chapter ] ?? 0 ) + 1;
				continue;
			}

			if ( strcasecmp( $status, 'Active' ) !== 0 ) {
				++$skipped_inactive;
				continue;
			}

			$members[] = [
				'first_name'       => $first_name,
				'last_initial'     => mb_substr( $last_name, 0, 1 ),
				'anniversary_date' => $date->format( 'Y-m-d' ),
			];
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return [
			'members'          => $members,
			'errors'           => $errors,
			'skipped_chapters' => $skipped_chapters,
			'skipped_inactive' => $skipped_inactive,
		];
	}

	private static function empty_result( array $errors ): array {
		return [
			'members'          => [],
			'errors'           => $errors,
			'skipped_chapters' => [],
			'skipped_inactive' => 0,
		];
	}
}
