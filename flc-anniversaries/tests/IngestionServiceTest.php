<?php

use PHPUnit\Framework\TestCase;

/**
 * Records what it was asked to store, without touching a real database, so
 * ingestion behavior can be tested without a live WordPress/MySQL stack.
 */
class FakeAnniversariesDB implements FLC_Anniversaries_DB_Interface {
	public bool $replace_all_called = false;
	public array $stored             = [];

	public function replace_all( array $members ): int {
		$this->replace_all_called = true;
		$this->stored             = $members;
		return count( $members );
	}

	public function get_by_month( int $month ): array {
		return array_values( array_filter(
			$this->stored,
			static fn( $m ) => (int) substr( $m['anniversary_date'], 5, 2 ) === $month
		) );
	}

	public function clear_all(): int {
		$count        = count( $this->stored );
		$this->stored = [];
		return $count;
	}

	public function count_all(): int {
		return count( $this->stored );
	}
}

class IngestionServiceTest extends TestCase {

	private string $tmp_path;

	protected function tearDown(): void {
		if ( isset( $this->tmp_path ) && file_exists( $this->tmp_path ) ) {
			unlink( $this->tmp_path );
		}
	}

	/**
	 * Copies a fixture to a throwaway path, since ingest() always deletes
	 * the file it's given.
	 */
	private function copy_fixture( string $name ): string {
		$this->tmp_path = sys_get_temp_dir() . '/flc-ingest-test-' . uniqid() . '.csv';
		copy( __DIR__ . "/../data/$name", $this->tmp_path );
		return $this->tmp_path;
	}

	public function test_ingest_stores_only_active_flc_members(): void {
		$path = $this->copy_fixture( 'report-valid.csv' );
		$db   = new FakeAnniversariesDB();

		$service = new FLC_Anniversaries_Ingestion_Service( $db );
		$summary = $service->ingest( $path, [ 'FLC' ] );

		$this->assertTrue( $summary['applied'] );
		$this->assertSame( 21, $summary['active_count'] );
		$this->assertSame( [ 'GC' => 1, 'SFL' => 1 ], $summary['skipped_chapters'] );
		$this->assertSame( 2, $summary['skipped_inactive'] );
		$this->assertEmpty( $summary['errors'] );

		$this->assertTrue( $db->replace_all_called );
		$this->assertCount( 21, $db->stored );
	}

	public function test_ingest_only_ever_stores_the_three_allowed_fields(): void {
		$path = $this->copy_fixture( 'report-valid.csv' );
		$db   = new FakeAnniversariesDB();

		( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'FLC' ] );

		foreach ( $db->stored as $member ) {
			$this->assertSame( [ 'first_name', 'last_initial', 'anniversary_date' ], array_keys( $member ) );
		}
	}

	public function test_ingest_deletes_the_uploaded_file(): void {
		$path = $this->copy_fixture( 'report-valid.csv' );
		$this->assertFileExists( $path );

		( new FLC_Anniversaries_Ingestion_Service( new FakeAnniversariesDB() ) )->ingest( $path, [ 'FLC' ] );

		$this->assertFileDoesNotExist( $path );
	}

	public function test_ingest_deletes_the_uploaded_file_even_when_nothing_is_applied(): void {
		$path = $this->copy_fixture( 'report-invalid.csv' );
		$db   = new FakeAnniversariesDB();

		// No FLC chapter allowed -> every row is either an error or skipped, zero members.
		( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'ZZZ-not-a-real-chapter' ] );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( $db->replace_all_called );
	}

	public function test_a_bad_upload_never_wipes_the_existing_table(): void {
		// A file with zero matching active members (e.g. the admin uploaded
		// something with no FLC rows) must not clobber existing data.
		$path = $this->copy_fixture( 'report-invalid.csv' );
		$db   = new FakeAnniversariesDB();

		$summary = ( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'nonexistent-chapter' ] );

		$this->assertFalse( $summary['applied'] );
		$this->assertFalse( $db->replace_all_called );
	}

	public function test_ingest_of_missing_file_does_not_throw_and_reports_file_error(): void {
		$db      = new FLC_Anniversaries_Ingestion_Service( new FakeAnniversariesDB() );
		$summary = $db->ingest( '/tmp/does-not-exist-' . uniqid() . '.csv', [ 'FLC' ] );

		$this->assertFalse( $summary['applied'] );
		$this->assertArrayHasKey( 0, $summary['errors'] );
	}

	public function test_ingested_members_are_queryable_by_month_through_the_db_contract(): void {
		$path = $this->copy_fixture( 'report-valid.csv' );
		$db   = new FakeAnniversariesDB();

		( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'FLC' ] );

		$may_rows       = $db->get_by_month( 5 );
		$may_milestones = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $may_rows, 5, 2026 );

		$this->assertContains( 'Marcus D.', $may_milestones[5] );
		$this->assertContains( 'Frank N.', $may_milestones[50] );
	}

	// -------------------------------------------------------------------------
	// Clearing the table — the settings-page "Clear All Anniversary Data"
	// action, exercised here against the DB contract it depends on.
	// -------------------------------------------------------------------------

	public function test_clear_all_empties_the_table_and_reports_how_many_were_removed(): void {
		$path = $this->copy_fixture( 'report-valid.csv' );
		$db   = new FakeAnniversariesDB();
		( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'FLC' ] );

		$this->assertSame( 21, $db->count_all() );

		$deleted = $db->clear_all();

		$this->assertSame( 21, $deleted );
		$this->assertSame( 0, $db->count_all() );
		$this->assertSame( [], $db->get_by_month( 5 ) );
	}

	public function test_clear_all_on_an_already_empty_table_deletes_nothing(): void {
		$db = new FakeAnniversariesDB();

		$this->assertSame( 0, $db->clear_all() );
		$this->assertSame( 0, $db->count_all() );
	}

	public function test_clearing_does_not_prevent_a_subsequent_upload_from_repopulating(): void {
		$db = new FakeAnniversariesDB();
		$db->clear_all();

		$path = $this->copy_fixture( 'report-valid.csv' );
		$summary = ( new FLC_Anniversaries_Ingestion_Service( $db ) )->ingest( $path, [ 'FLC' ] );

		$this->assertTrue( $summary['applied'] );
		$this->assertSame( 21, $db->count_all() );
	}
}
