<?php

/**
 * Orchestrates a monthly roster upload: parse the report, replace the
 * anniversaries table with the active in-chapter members it contains, and
 * guarantee the uploaded file (and every PII column in it) is discarded
 * once ingestion is done -- regardless of whether ingestion succeeded.
 */
class FLC_Anniversaries_Ingestion_Service {

	private FLC_Anniversaries_DB_Interface $db;

	public function __construct( ?FLC_Anniversaries_DB_Interface $db = null ) {
		$this->db = $db ?? new FLC_Anniversaries_DB();
	}

	/**
	 * @param string        $tmp_file_path    Path to the uploaded CSV. Always deleted before returning.
	 * @param list<string>  $allowed_chapters Chapter codes to ingest; others are skipped and reported.
	 *
	 * @return array{
	 *     applied: bool,
	 *     active_count: int,
	 *     skipped_chapters: array<string, int>,
	 *     skipped_inactive: int,
	 *     errors: array<int, string>,
	 * }
	 */
	public function ingest( string $tmp_file_path, array $allowed_chapters ): array {
		$result = FLC_Anniversaries_Report_Parser::parse( $tmp_file_path, $allowed_chapters );

		if ( file_exists( $tmp_file_path ) ) {
			unlink( $tmp_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		}

		$summary = [
			'applied'          => false,
			'active_count'     => count( $result['members'] ),
			'skipped_chapters' => $result['skipped_chapters'],
			'skipped_inactive' => $result['skipped_inactive'],
			'errors'           => $result['errors'],
		];

		// A file-level error (missing/unreadable) or an upload with zero
		// matching active members leaves the existing table untouched --
		// a bad upload should never wipe out a good one.
		if ( empty( $result['members'] ) ) {
			return $summary;
		}

		$this->db->replace_all( $result['members'] );
		$summary['applied'] = true;

		return $summary;
	}
}
