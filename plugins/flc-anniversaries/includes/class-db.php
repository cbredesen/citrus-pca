<?php

/**
 * Storage contract for ingested anniversary members, so the ingestion
 * service can be unit tested against a fake without a live database.
 */
interface FLC_Anniversaries_DB_Interface {

	/**
	 * Replaces the entire table contents with the given members.
	 *
	 * @param list<array{first_name: string, last_initial: string, anniversary_date: string}> $members
	 * @return int Number of rows inserted.
	 */
	public function replace_all( array $members ): int;

	/**
	 * @return list<array{first_name: string, last_initial: string, anniversary_date: string}>
	 */
	public function get_by_month( int $month ): array;

	/**
	 * Deletes every row. Used by the "clear all data" admin action, separately
	 * from replace_all() (which clears as part of ingesting a new upload).
	 *
	 * @return int Number of rows deleted.
	 */
	public function clear_all(): int;

	/**
	 * @return int Total number of stored anniversary records.
	 */
	public function count_all(): int;
}

/**
 * $wpdb-backed storage for ingested anniversary members.
 *
 * The table holds only first_name, last_initial, and anniversary_date --
 * the sole fields the anniversaries feature needs. Every other column in
 * the source report (name, email, address, phone, etc.) never reaches
 * this table.
 */
class FLC_Anniversaries_DB implements FLC_Anniversaries_DB_Interface {

	const DB_VERSION        = '1.0';
	const DB_VERSION_OPTION = 'flc_anniversaries_db_version';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'flc_anniversary_members';
	}

	/**
	 * Creates or upgrades the table. Safe to call on every request; it
	 * only touches the schema when the stored version differs.
	 */
	public static function maybe_create_table(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			first_name VARCHAR(100) NOT NULL,
			last_initial CHAR(1) NOT NULL,
			anniversary_date DATE NOT NULL,
			PRIMARY KEY  (id),
			KEY anniversary_date (anniversary_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public function replace_all( array $members ): int {
		global $wpdb;
		$table = self::table_name();

		$wpdb->query( 'START TRANSACTION' );
		$wpdb->query( "DELETE FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$inserted = 0;
		foreach ( $members as $member ) {
			$result = $wpdb->insert(
				$table,
				[
					'first_name'       => $member['first_name'],
					'last_initial'     => $member['last_initial'],
					'anniversary_date' => $member['anniversary_date'],
				],
				[ '%s', '%s', '%s' ]
			);
			if ( $result !== false ) {
				++$inserted;
			}
		}

		$wpdb->query( 'COMMIT' );

		return $inserted;
	}

	public function get_by_month( int $month ): array {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT first_name, last_initial, anniversary_date FROM $table WHERE MONTH( anniversary_date ) = %d",
				$month
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	public function clear_all(): int {
		global $wpdb;
		$table = self::table_name();

		$deleted = $wpdb->query( "DELETE FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $deleted === false ? 0 : (int) $deleted;
	}

	public function count_all(): int {
		global $wpdb;
		$table = self::table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
