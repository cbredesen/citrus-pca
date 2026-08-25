<?php
/**
 * Server-side render callback for the File Include block.
 */
class FLC_File_Include_Block_Renderer {

	/**
	 * Render the block on the front end.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render( array $attributes ): string {
		$directory = flc_file_include_get_directory();

		if ( ! is_dir( $directory ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="flc-file-include-error">' .
					esc_html__( 'No files available. Contact the site administrator.', 'flc-file-include' ) .
					'</p>';
			}
			return '';
		}

		$mode          = isset( $attributes['mode'] ) ? $attributes['mode'] : 'latest';
		$selected_file = isset( $attributes['selectedFile'] ) ? $attributes['selectedFile'] : '';

		if ( 'latest' === $mode ) {
			$files = FLC_File_Include_File_Scanner::get_files( $directory );
			if ( empty( $files ) ) {
				if ( current_user_can( 'edit_posts' ) ) {
					return '<p class="flc-file-include-error">' .
						esc_html__( 'File Include: No HTML files found in the configured directory.', 'flc-file-include' ) .
						'</p>';
				}
				return '';
			}
			$filename = $files[0]['name'];
		} else {
			if ( empty( $selected_file ) ) {
				if ( current_user_can( 'edit_posts' ) ) {
					return '<p class="flc-file-include-error">' .
						esc_html__( 'File Include: No file selected. Edit this block to choose a file.', 'flc-file-include' ) .
						'</p>';
				}
				return '';
			}
			$filename = $selected_file;
		}

		// Prevent path traversal: strip any directory separators from the filename.
		$filename = wp_basename( $filename );
		$filepath = rtrim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $filename;

		// Ensure the resolved path is inside the configured directory.
		$real_directory = realpath( $directory );
		$real_filepath  = realpath( $filepath );

		if (
			false === $real_filepath ||
			false === $real_directory ||
			strncmp( $real_filepath, $real_directory . DIRECTORY_SEPARATOR, strlen( $real_directory ) + 1 ) !== 0
		) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="flc-file-include-error">' .
					esc_html__( 'File Include: Invalid file path.', 'flc-file-include' ) .
					'</p>';
			}
			return '';
		}

		$html = file_get_contents( $real_filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $html ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="flc-file-include-error">' .
					esc_html__( 'File Include: Could not read file.', 'flc-file-include' ) .
					'</p>';
			}
			return '';
		}

		$output = '';

		if ( ! empty( $attributes['showRawLink'] ) ) {
			$relative_dir = ltrim( str_replace( '\\', '/', get_option( FLC_File_Include_Settings_Page::OPTION_KEY, FLC_File_Include_Settings_Page::DEFAULT_DIRECTORY ) ), '/' );
			$file_url     = trailingslashit( site_url() ) . $relative_dir . '/' . rawurlencode( $filename );
			$output      .= '<p class="flc-file-include-raw-link"><a href="' . esc_url( $file_url ) . '" target="_blank">'
				. esc_html__( 'View original HTML file', 'flc-file-include' )
				. '</a></p>';
		}

		$output .= '<div class="flc-file-include-content">' . self::strip_inline_styles( self::extract_body( $html ) ) . '</div>';

		return $output;
	}

	/**
	 * Remove <style> blocks and style="" attributes from HTML.
	 *
	 * @param string $html HTML fragment.
	 * @return string Cleaned HTML.
	 */
	private static function strip_inline_styles( string $html ): string {
		if ( empty( trim( $html ) ) ) {
			return $html;
		}

		// Wrap in a full document so DOMDocument has proper context and UTF-8 is preserved.
		$wrapped = '<html><head><meta charset="UTF-8"></head><body><div id="wpfi-root">' . $html . '</div></body></html>';

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $wrapped );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Remove all <style> elements (closed or not — DOMDocument handles both).
		foreach ( $xpath->query( '//style' ) as $node ) {
			$node->parentNode->removeChild( $node );
		}

		// Remove all style="..." attributes.
		foreach ( $xpath->query( '//*[@style]' ) as $node ) {
			$node->removeAttribute( 'style' );
		}

		$root = $dom->getElementById( 'wpfi-root' );
		if ( ! $root ) {
			return $html;
		}

		$output = '';
		foreach ( $root->childNodes as $child ) {
			$output .= $dom->saveHTML( $child );
		}

		return $output;
	}

	private static function extract_body( string $html ): string {
		// Extract content between <body ...> and </body>.
		if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html, $matches ) ) {
			return trim( $matches[1] );
		}

		// No <body> found — strip <html>, <head> blocks and return what remains.
		$html = preg_replace( '/<head[^>]*>.*?<\/head>/is', '', $html );
		$html = preg_replace( '/<\/?html[^>]*>/i', '', $html );

		return trim( $html );
	}
}
