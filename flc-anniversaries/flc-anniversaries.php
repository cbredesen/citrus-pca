<?php
/**
 * Plugin Name:       FLC Anniversaries
 * Plugin URI:        https://github.com/cbredesen/flc-anniversaries
 * Description:       Displays club member anniversaries for the current month, ingested from a monthly PCA National roster upload. Use the Anniversaries block or shortcode [flc_anniversaries].
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            cbredesen
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flc-anniversaries
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-report-parser.php';
require_once __DIR__ . '/includes/class-milestone-calculator.php';
require_once __DIR__ . '/includes/class-ingestion-service.php';
require_once __DIR__ . '/includes/class-settings-page.php';
require_once __DIR__ . '/includes/class-block-renderer.php';

register_activation_hook( __FILE__, array( 'FLC_Anniversaries_DB', 'maybe_create_table' ) );

new FLC_Anniversaries_Settings_Page();

add_action( 'plugins_loaded', array( 'FLC_Anniversaries_DB', 'maybe_create_table' ) );

add_action( 'init', function () {
	wp_register_style(
		'flc-anniversaries-frontend',
		plugins_url( 'build/style-index.css', __FILE__ ),
		array(),
		'2.0.0'
	);

	register_block_type(
		__DIR__ . '/build',
		array(
			'render_callback' => array( 'FLC_Anniversaries_Block_Renderer', 'render' ),
		)
	);
} );

/**
 * Shortcode usage doesn't trigger the block's automatic style enqueue,
 * so the stylesheet is enqueued explicitly here.
 */
add_shortcode( 'flc_anniversaries', function ( $atts ) {
	$atts = shortcode_atts( array( 'show_all' => 'false' ), $atts, 'flc_anniversaries' );

	wp_enqueue_style( 'flc-anniversaries-frontend' );
	return FLC_Anniversaries_Block_Renderer::render( array(
		'showAllAnniversaries' => filter_var( $atts['show_all'], FILTER_VALIDATE_BOOLEAN ),
	) );
} );
