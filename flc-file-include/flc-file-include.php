<?php
/**
 * Plugin Name:       FLC File Include
 * Plugin URI:        https://github.com/cbredesen/flc-file-include
 * Description:       Gutenberg block to include an HTML file from a configured server-side directory.
 * Version:           1.1.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            cbredesen
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flc-file-include
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-file-scanner.php';
require_once __DIR__ . '/includes/class-block-renderer.php';
require_once __DIR__ . '/includes/class-settings-page.php';

/**
 * Resolves the configured relative directory path to an absolute server path.
 * The stored value is relative to the WordPress installation root (ABSPATH).
 *
 * @return string Absolute path, no trailing slash.
 */
function flc_file_include_get_directory(): string {
	$relative = get_option( 'flc_file_include_directory', FLC_File_Include_Settings_Page::DEFAULT_DIRECTORY );
	if ( empty( $relative ) ) {
		$relative = FLC_File_Include_Settings_Page::DEFAULT_DIRECTORY;
	}
	$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
	return rtrim( ABSPATH, '/\\' ) . '/' . $relative;
}

/**
 * Register the block using block.json from the build directory.
 * @wordpress/scripts compiles src/ -> build/ and copies block.json.
 */
add_action( 'init', function () {
	wp_register_style(
		'flc-file-include-frontend',
		plugins_url( 'assets/frontend.css', __FILE__ ),
		array(),
		'1.1.0'
	);

	register_block_type(
		__DIR__ . '/build',
		array(
			'render_callback' => array( 'FLC_File_Include_Block_Renderer', 'render' ),
			'style'           => 'flc-file-include-frontend',
		)
	);
} );

/**
 * Register the REST endpoint that lists HTML files in the configured directory.
 * Requires edit_posts capability so only logged-in editors/admins can call it.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'flc-file-include/v1',
		'/files',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( 'FLC_File_Include_File_Scanner', 'rest_get_files' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
} );

new FLC_File_Include_Settings_Page();
