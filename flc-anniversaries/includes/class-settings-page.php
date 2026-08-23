<?php
/**
 * Settings page for FLC Anniversaries.
 * Accessible at Settings > FLC Anniversaries.
 *
 * Two independent things live here:
 *  - The plugin-level list of chapter codes (from the report's CHAPTER
 *    column) this site should ingest.
 *  - The monthly roster upload, which parses the uploaded report,
 *    replaces the anniversaries table with its active in-chapter members,
 *    and discards the uploaded file immediately afterward.
 *  - Clearing the anniversaries table entirely, for when you need to reset
 *    it without waiting for (or in place of) the next monthly upload.
 */
class FLC_Anniversaries_Settings_Page {

	const CHAPTERS_OPTION     = 'flc_anniversaries_chapters';
	const DEFAULT_CHAPTERS    = 'FLC';
	const LAST_INGEST_OPTION  = 'flc_anniversaries_last_ingest';
	const UPLOAD_FIELD        = 'flc_anniversaries_roster';
	const UPLOAD_NONCE_ACTION = 'flc_anniversaries_upload_roster';
	const UPLOAD_NONCE_NAME   = 'flc_anniversaries_upload_nonce';
	const CLEAR_NONCE_ACTION  = 'flc_anniversaries_clear_table';
	const CLEAR_NONCE_NAME    = 'flc_anniversaries_clear_nonce';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_upload' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_clear' ) );
		add_filter( 'plugin_action_links_flc-anniversaries/flc-anniversaries.php', array( $this, 'add_settings_link' ) );
	}

	/**
	 * @return list<string>
	 */
	public static function get_allowed_chapters(): array {
		$raw   = (string) get_option( self::CHAPTERS_OPTION, self::DEFAULT_CHAPTERS );
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
		return $parts ?: array( self::DEFAULT_CHAPTERS );
	}

	public function add_menu(): void {
		add_options_page(
			'FLC Anniversaries Settings',
			'FLC Anniversaries',
			'manage_options',
			'flc-anniversaries',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'flc_anniversaries_settings',
			self::CHAPTERS_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_chapters' ),
				'default'           => self::DEFAULT_CHAPTERS,
			)
		);

		add_settings_section(
			'flc_anniversaries_main',
			'Chapter Configuration',
			null,
			'flc-anniversaries'
		);

		add_settings_field(
			self::CHAPTERS_OPTION,
			'Included Chapters',
			array( $this, 'render_chapters_field' ),
			'flc-anniversaries',
			'flc_anniversaries_main'
		);
	}

	public function sanitize_chapters( string $value ): string {
		$parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );

		if ( empty( $parts ) ) {
			add_settings_error(
				self::CHAPTERS_OPTION,
				'invalid_chapters',
				'Enter at least one chapter code.'
			);
			return get_option( self::CHAPTERS_OPTION, self::DEFAULT_CHAPTERS );
		}

		$parts = array_map( static fn( $c ) => strtoupper( sanitize_text_field( $c ) ), $parts );

		return implode( ', ', $parts );
	}

	public function render_chapters_field(): void {
		$value = get_option( self::CHAPTERS_OPTION, self::DEFAULT_CHAPTERS );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::CHAPTERS_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_CHAPTERS ); ?>"
		/>
		<p class="description" style="margin-top: 8px;">
			Comma-separated chapter codes from the region report's <code>CHAPTER</code> column to include
			(e.g. <code>FLC</code>). Rows for any other chapter present in an uploaded report are skipped
			and counted in the upload summary below, rather than treated as an error.
		</p>
		<?php
	}

	/**
	 * Handles the roster upload form, if one was submitted. Runs on
	 * admin_init so it can redirect before any output is sent.
	 */
	public function maybe_handle_upload(): void {
		if ( ! isset( $_POST[ self::UPLOAD_NONCE_NAME ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flc-anniversaries' ) );
		}

		check_admin_referer( self::UPLOAD_NONCE_ACTION, self::UPLOAD_NONCE_NAME );

		$tmp_name = $_FILES[ self::UPLOAD_FIELD ]['tmp_name'] ?? '';

		if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
			add_settings_error( self::CHAPTERS_OPTION, 'upload_failed', 'No file was uploaded, or the upload failed.' );
			return;
		}

		$service = new FLC_Anniversaries_Ingestion_Service();
		$summary = $service->ingest( $tmp_name, self::get_allowed_chapters() );
		$summary['timestamp'] = time();

		update_option( self::LAST_INGEST_OPTION, $summary );

		wp_safe_redirect( add_query_arg( array( 'page' => 'flc-anniversaries' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Handles the "clear all data" form, if it was submitted. Deletes every
	 * row from the anniversaries table; it does not touch any option
	 * (chapter config, last-upload summary) or affect the next upload.
	 */
	public function maybe_handle_clear(): void {
		if ( ! isset( $_POST[ self::CLEAR_NONCE_NAME ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flc-anniversaries' ) );
		}

		check_admin_referer( self::CLEAR_NONCE_ACTION, self::CLEAR_NONCE_NAME );

		$db      = new FLC_Anniversaries_DB();
		$deleted = $db->clear_all();

		wp_safe_redirect( add_query_arg( array( 'page' => 'flc-anniversaries', 'flc_cleared' => $deleted ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<?php if ( isset( $_GET['flc_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( sprintf( 'Cleared %d anniversary record(s).', (int) $_GET['flc_cleared'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				Currently storing
				<strong><?php echo (int) ( new FLC_Anniversaries_DB() )->count_all(); ?></strong>
				anniversary record(s).
			</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'flc_anniversaries_settings' );
				do_settings_sections( 'flc-anniversaries' );
				submit_button( 'Save Chapters' );
				?>
			</form>

			<hr />

			<h2>Upload Monthly Roster Report</h2>
			<p>
				Upload the full region report CSV from PCA National. The site replaces its anniversary list
				with the active members from the chapters configured above. The uploaded file is parsed and
				discarded immediately &mdash; no data from it is retained beyond first name, last initial,
				and anniversary date.
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::UPLOAD_NONCE_ACTION, self::UPLOAD_NONCE_NAME ); ?>
				<input type="file" name="<?php echo esc_attr( self::UPLOAD_FIELD ); ?>" accept=".csv" required />
				<?php submit_button( 'Upload and Ingest' ); ?>
			</form>

			<?php $this->render_last_ingest_summary(); ?>

			<hr />

			<h2>Clear Anniversary Data</h2>
			<p>
				Permanently deletes every record from the anniversaries table. This does not touch the
				roster report itself &mdash; nothing from it is ever stored &mdash; so the next upload will
				repopulate the table as usual. Use this to reset the list without waiting for the next
				monthly upload.
			</p>
			<form method="post" onsubmit="return confirm( 'Delete all anniversary records? This cannot be undone.' );">
				<?php wp_nonce_field( self::CLEAR_NONCE_ACTION, self::CLEAR_NONCE_NAME ); ?>
				<?php submit_button( 'Clear All Anniversary Data', 'secondary', 'submit', true, array( 'style' => 'color: #b32d2e; border-color: #b32d2e;' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function render_last_ingest_summary(): void {
		$summary = get_option( self::LAST_INGEST_OPTION );
		if ( empty( $summary ) ) {
			return;
		}
		?>
		<h2>Last Upload</h2>
		<table class="widefat" style="max-width: 640px;">
			<tbody>
				<tr>
					<td>Uploaded</td>
					<td><?php echo esc_html( wp_date( 'F j, Y g:i a', $summary['timestamp'] ) ); ?></td>
				</tr>
				<tr>
					<td>Result</td>
					<td>
						<?php if ( $summary['applied'] ) : ?>
							<span style="color: green;">&#10003; Anniversary list updated &mdash; <?php echo (int) $summary['active_count']; ?> active member(s) ingested.</span>
						<?php else : ?>
							<span style="color: red;">&#10007; No active, in-chapter members were found &mdash; the existing anniversary list was left unchanged.</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( ! empty( $summary['skipped_inactive'] ) ) : ?>
				<tr>
					<td>Skipped (inactive)</td>
					<td><?php echo (int) $summary['skipped_inactive']; ?> row(s) had a non-Active status.</td>
				</tr>
				<?php endif; ?>
				<?php if ( ! empty( $summary['skipped_chapters'] ) ) : ?>
				<tr>
					<td>Skipped (other chapters)</td>
					<td>
						<?php
						$parts = array();
						foreach ( $summary['skipped_chapters'] as $chapter => $count ) {
							$parts[] = esc_html( $chapter ) . ' (' . (int) $count . ')';
						}
						echo 'Rows found for chapters outside your configured list: ' . implode( ', ', $parts ) . '. Update "Included Chapters" above if this is unexpected.';
						?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( ! empty( $summary['errors'] ) ) : ?>
				<tr>
					<td>Row errors</td>
					<td>
						<?php echo count( $summary['errors'] ); ?> row(s) could not be parsed and were skipped:
						<ul style="margin: 4px 0 0 20px; list-style: disc;">
							<?php foreach ( $summary['errors'] as $message ) : ?>
								<li><?php echo esc_html( $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=flc-anniversaries' ) ) . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
