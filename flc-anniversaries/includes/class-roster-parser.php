<?php

class FLC_Anniversaries_Roster_Parser {

	/**
	 * Parse the CSV roster and return milestone members for the given month/year.
	 *
	 * Errors are keyed by line number. Key 0 is reserved for file-level errors
	 * (not found, unreadable). Row-level errors use the 1-based line number.
	 *
	 * @return array{milestones: array<int, list<string>>, errors: array<int, string>}
	 */
	public static function parse( string $csv_path, int $month, int $year ): array {
		if ( ! file_exists( $csv_path ) ) {
			return [
				'milestones' => [],
				'errors'     => [ 0 => "File not found: $csv_path" ],
			];
		}

		$handle = fopen( $csv_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return [
				'milestones' => [],
				'errors'     => [ 0 => "Cannot open file: $csv_path" ],
			];
		}

		fgetcsv( $handle, 0, ',', '"', '' ); // skip header row

		$milestones = [];
		$errors     = [];
		$line       = 1;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line++;

			if ( $row === [ null ] ) { // blank line
				continue;
			}

			if ( count( $row ) < 2 ) {
				$errors[ $line ] = "Line $line: expected at least 2 columns, got " . count( $row );
				continue;
			}

			$name     = trim( $row[0] );
			$date_str = trim( $row[1] );

			if ( $name === '' ) {
				$errors[ $line ] = "Line $line: name is empty";
				continue;
			}

			if ( $date_str === '' ) {
				$errors[ $line ] = "Line $line: date is empty for '$name'";
				continue;
			}

			$date         = DateTime::createFromFormat( 'n/j/Y', $date_str );
			$parse_errors = DateTime::getLastErrors();

			if ( ! $date || ( $parse_errors !== false && ( $parse_errors['error_count'] > 0 || $parse_errors['warning_count'] > 0 ) ) ) {
				$errors[ $line ] = "Line $line: invalid date '$date_str' for '$name'";
				continue;
			}

			$join_month = (int) $date->format( 'n' );
			$join_year  = (int) $date->format( 'Y' );

			if ( $join_month !== $month ) {
				continue;
			}

			$years = $year - $join_year;

			if ( $years <= 0 || $years % 5 !== 0 ) {
				continue;
			}

			$milestones[ $years ][] = $name;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		ksort( $milestones );

		return [ 'milestones' => $milestones, 'errors' => $errors ];
	}
}
