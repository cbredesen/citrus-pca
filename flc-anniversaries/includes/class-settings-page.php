<?php
/**
 * Settings page for FLC Anniversaries.
 * Accessible at Settings > FLC Anniversaries.
 */
class FLC_Anniversaries_Settings_Page {

	const OPTION_KEY    = 'flc_anniversaries_csv_path';
	const DEFAULT_PATH  = 'wp-content/plugins/flc-anniversaries/data/roster.csv';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_flc-anniversaries/flc-anniversaries.php', array( $this, 'add_settings_link' ) );
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
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_path' ),
				'default'           => self::DEFAULT_PATH,
			)
		);

		add_settings_section(
			'flc_anniversaries_main',
			'CSV File Configuration',
			null,
			'flc-anniversaries'
		);

		add_settings_field(
			self::OPTION_KEY,
			'Roster CSV File',
			array( $this, 'render_path_field' ),
			'flc-anniversaries',
			'flc_anniversaries_main'
		);
	}

	public function sanitize_path( string $value ): string {
		$value = sanitize_text_field( $value );
		$value = trim( $value, '/\\' );
		$value = str_replace( '\\', '/', $value );

		if ( strpos( $value, '..' ) !== false ) {
			add_settings_error(
				self::OPTION_KEY,
				'invalid_path',
				'CSV file path must not contain "..".'
			);
			return get_option( self::OPTION_KEY, self::DEFAULT_PATH );
		}

		return $value;
	}

	public function render_path_field(): void {
		$relative  = get_option( self::OPTION_KEY, self::DEFAULT_PATH );
		$wp_root   = rtrim( ABSPATH, '/\\' );
		$absolute  = $wp_root . '/' . ltrim( str_replace( '\\', '/', $relative ), '/' );
		$exists    = file_exists( $absolute );
		$readable  = $exists && is_readable( $absolute );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			value="<?php echo esc_attr( $relative ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_PATH ); ?>"
		/>

		<p class="description" style="margin-top: 8px;">
			Enter a path relative to the WordPress installation root shown below. Do not include a leading slash.
		</p>

		<table class="description" style="margin-top: 8px; border-collapse: collapse;">
			<tr>
				<td style="padding: 2px 12px 2px 0; white-space: nowrap; font-weight: 600;">WordPress root:</td>
				<td><code><?php echo esc_html( $wp_root ); ?>/</code></td>
			</tr>
			<tr>
				<td style="padding: 2px 12px 2px 0; white-space: nowrap; font-weight: 600;">Resolves to:</td>
				<td>
					<code><?php echo esc_html( $absolute ); ?></code>
					<?php if ( ! empty( $relative ) ) : ?>
						<?php if ( $readable ) : ?>
							<span style="color: green; margin-left: 8px;">&#10003; File exists and is readable.</span>
						<?php elseif ( $exists ) : ?>
							<span style="color: orange; margin-left: 8px;">&#9888; File exists but is not readable by the web server.</span>
						<?php else : ?>
							<span style="color: red; margin-left: 8px;">&#10007; File does not exist.</span>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
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
				settings_fields( 'flc_anniversaries_settings' );
				do_settings_sections( 'flc-anniversaries' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=flc-anniversaries' ) ) . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
