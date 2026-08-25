<?php
/**
 * Fires on plugin deletion (not deactivation). Removes the anniversaries
 * table and plugin options -- there is no roster PII to clean up here,
 * since none is ever persisted past ingestion.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-db.php';

global $wpdb;
$table = FLC_Anniversaries_DB::table_name();
$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'flc_anniversaries_db_version' );
delete_option( 'flc_anniversaries_chapters' );
delete_option( 'flc_anniversaries_last_ingest' );
delete_option( 'flc_anniversaries_csv_path' ); // legacy v1 option, if still present
