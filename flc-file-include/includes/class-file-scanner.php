<?php
/**
 * Scans the configured directory for HTML files.
 */
class FLC_File_Include_File_Scanner {

	/**
	 * Returns an array of HTML files in the configured directory,
	 * sorted by modification time descending (newest first).
	 *
	 * @param string $directory Absolute path to directory.
	 * @return array[] Each element has 'name' and 'modified' keys.
	 */
	public static function get_files( string $directory ): array {
		if ( empty( $directory ) || ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return array();
		}

		$files = array();

		foreach ( new DirectoryIterator( $directory ) as $file_info ) {
			if ( $file_info->isDot() || ! $file_info->isFile() ) {
				continue;
			}
			$ext = strtolower( $file_info->getExtension() );
			if ( ! in_array( $ext, array( 'html', 'htm' ), true ) ) {
				continue;
			}
			$files[] = array(
				'name'     => $file_info->getFilename(),
				'modified' => $file_info->getMTime(),
			);
		}

		usort( $files, fn( $a, $b ) => $b['modified'] - $a['modified'] );

		return $files;
	}

	/**
	 * REST API callback to list files in the configured directory.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_get_files() {
		$directory = flc_file_include_get_directory();
		$files     = self::get_files( $directory );
		return rest_ensure_response( $files );
	}
}
