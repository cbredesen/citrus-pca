<?php
/**
 * Plugin Name: FLC Anniversaries
 * Description: Displays club member milestone anniversaries (divisible by 5) for the current month. Use the FLC Anniversaries block or shortcode [flc_anniversaries].
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-settings-page.php';
require_once __DIR__ . '/includes/class-roster-parser.php';
require_once __DIR__ . '/includes/class-block-renderer.php';

/**
 * Resolves the configured CSV path (relative to ABSPATH) to an absolute path.
 */
function flc_anniversaries_get_csv_path(): string {
	$relative = get_option( FLC_Anniversaries_Settings_Page::OPTION_KEY, FLC_Anniversaries_Settings_Page::DEFAULT_PATH );
	if ( empty( $relative ) ) {
		$relative = FLC_Anniversaries_Settings_Page::DEFAULT_PATH;
	}
	$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
	return rtrim( ABSPATH, '/\\' ) . '/' . $relative;
}

new FLC_Anniversaries_Settings_Page();

add_action( 'init', function () {
	register_block_type(
		__DIR__ . '/build',
		array(
			'render_callback' => array( 'FLC_Anniversaries_Block_Renderer', 'render' ),
		)
	);
} );

add_shortcode( 'flc_anniversaries', array( 'FLC_Anniversaries_Block_Renderer', 'render' ) );
