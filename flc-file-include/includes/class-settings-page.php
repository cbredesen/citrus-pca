<?php
/**
 * Settings page for WP File Include.
 * Accessible at Settings > File Include.
 */
class FLC_File_Include_Settings_Page {

	const OPTION_KEY        = 'flc_file_include_directory';
	const DEFAULT_DIRECTORY = 'wp-content/uploads/html-files';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_flc-file-include/flc-file-include.php', array( $this, 'add_settings_link' ) );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'File Include Settings', 'flc-file-include' ),
			__( 'File Include', 'flc-file-include' ),
			'manage_options',
			'flc-file-include',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'flc_file_include_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_directory' ),
				'default'           => self::DEFAULT_DIRECTORY,
			)
		);

		add_settings_section(
			'flc_file_include_main',
			__( 'Directory Configuration', 'flc-file-include' ),
			null,
			'flc-file-include'
		);

		add_settings_field(
			self::OPTION_KEY,
			__( 'HTML Files Directory', 'flc-file-include' ),
			array( $this, 'render_directory_field' ),
			'flc-file-include',
			'flc_file_include_main'
		);
	}

	public function sanitize_directory( string $value ): string {
		$value = sanitize_text_field( $value );
		$value = trim( $value, '/\\' );
		$value = str_replace( '\\', '/', $value );

		if ( strpos( $value, '..' ) !== false ) {
			add_settings_error(
				self::OPTION_KEY,
				'invalid_path',
				__( 'Directory path must not contain "..".', 'flc-file-include' )
			);
			return get_option( self::OPTION_KEY, self::DEFAULT_DIRECTORY );
		}

		return $value;
	}

	public function render_directory_field(): void {
		$relative    = get_option( self::OPTION_KEY, self::DEFAULT_DIRECTORY );
		$wp_root     = rtrim( ABSPATH, '/\\' );
		$absolute    = $wp_root . '/' . ltrim( str_replace( '\\', '/', $relative ), '/' );
		$dir_exists  = is_dir( $absolute );
		$dir_readable = $dir_exists && is_readable( $absolute );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			value="<?php echo esc_attr( $relative ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_DIRECTORY ); ?>"
		/>

		<p class="description" style="margin-top: 8px;">
			<?php esc_html_e( 'Enter a path relative to the WordPress installation root shown below. Do not include a leading slash.', 'flc-file-include' ); ?>
		</p>

		<table class="description" style="margin-top: 8px; border-collapse: collapse;">
			<tr>
				<td style="padding: 2px 12px 2px 0; white-space: nowrap; font-weight: 600;">
					<?php esc_html_e( 'WordPress root:', 'flc-file-include' ); ?>
				</td>
				<td><code><?php echo esc_html( $wp_root ); ?>/</code></td>
			</tr>
			<tr>
				<td style="padding: 2px 12px 2px 0; white-space: nowrap; font-weight: 600;">
					<?php esc_html_e( 'Resolves to:', 'flc-file-include' ); ?>
				</td>
				<td>
					<code><?php echo esc_html( $absolute ); ?></code>
					<?php if ( ! empty( $relative ) ) : ?>
						<?php if ( $dir_readable ) : ?>
							<span style="color: green; margin-left: 8px;">&#10003; <?php esc_html_e( 'Directory exists and is readable.', 'flc-file-include' ); ?></span>
						<?php elseif ( $dir_exists ) : ?>
							<span style="color: orange; margin-left: 8px;">&#9888; <?php esc_html_e( 'Directory exists but is not readable by the web server.', 'flc-file-include' ); ?></span>
						<?php else : ?>
							<span style="color: red; margin-left: 8px;">&#10007; <?php esc_html_e( 'Directory does not exist yet.', 'flc-file-include' ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="description" style="margin-top: 10px;">
			<strong><?php esc_html_e( 'Recommendation:', 'flc-file-include' ); ?></strong>
			<?php
			printf(
				/* translators: %s: recommended path */
				esc_html__( 'Use a dedicated subfolder such as %s rather than placing files directly in uploads.', 'flc-file-include' ),
				'<code>' . esc_html( self::DEFAULT_DIRECTORY ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'flc_file_include_settings' );
				do_settings_sections( 'flc-file-include' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=flc-file-include' ) ) . '">'
			. esc_html__( 'Settings', 'flc-file-include' )
			. '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
