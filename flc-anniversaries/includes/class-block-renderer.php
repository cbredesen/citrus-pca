<?php
/**
 * Server-side render callback for the FLC Anniversaries block.
 */
class FLC_Anniversaries_Block_Renderer {

	public static function render(): string {
		$month = isset( $_GET['flc_month'] ) ? (int) $_GET['flc_month'] : (int) date( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$year  = isset( $_GET['flc_year'] )  ? (int) $_GET['flc_year']  : (int) date( 'Y' );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$month = max( 1, min( 12, $month ) );
		$year  = max( 1900, min( 2200, $year ) );

		$current        = new DateTime( "$year-$month-01" );
		$prev           = ( clone $current )->modify( '-1 month' );
		$next           = ( clone $current )->modify( '+1 month' );
		$month_label    = $current->format( 'F Y' );

		$prev_url = add_query_arg( [ 'flc_month' => $prev->format( 'n' ), 'flc_year' => $prev->format( 'Y' ) ] );
		$next_url = add_query_arg( [ 'flc_month' => $next->format( 'n' ), 'flc_year' => $next->format( 'Y' ) ] );

		$html  = '<div class="flc-anniversaries">';
		$html .= '<div class="flc-anniversaries-nav">';
		$html .= '<a class="flc-anniversaries-nav-btn" href="' . esc_url( $prev_url ) . '">&lsaquo; ' . esc_html( $prev->format( 'F Y' ) ) . '</a>';
		$html .= '<span class="flc-anniversaries-month">' . esc_html( $month_label ) . '</span>';
		$html .= '<a class="flc-anniversaries-nav-btn" href="' . esc_url( $next_url ) . '">' . esc_html( $next->format( 'F Y' ) ) . ' &rsaquo;</a>';
		$html .= '</div>';

		$milestones = self::load_milestones( $month, $year );

		if ( is_string( $milestones ) ) {
			$html .= $milestones;
		} elseif ( empty( $milestones ) ) {
			$html .= '<p class="flc-anniversaries-none">No milestone anniversaries this month.</p>';
		} else {
			foreach ( $milestones as $years => $names ) {
				sort( $names );
				$label = esc_html( $years ) . ' Year' . ( $years !== 1 ? 's' : '' );
				$html .= '<section class="flc-anniversary-group">';
				$html .= '<h2 class="flc-anniversary-heading">' . $label . '</h2>';
				$html .= '<ul class="flc-anniversary-names">';
				foreach ( $names as $name ) {
					$html .= '<li>' . esc_html( $name ) . '</li>';
				}
				$html .= '</ul>';
				$html .= '</section>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns an associative array of [ years => names[] ], a string error message, or an empty array.
	 *
	 * @return array|string
	 */
	private static function load_milestones( int $month, int $year ) {
		$csv_path = flc_anniversaries_get_csv_path();

		if ( ! file_exists( $csv_path ) ) {
			return '<p class="flc-anniversaries-none">Anniversary data not found.</p>';
		}

		$handle = fopen( $csv_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return '<p class="flc-anniversaries-none">Unable to read anniversary data.</p>';
		}

		fgetcsv( $handle, 0, ',', '"', '' ); // skip header

		$milestones = [];

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( $row ) < 2 ) {
				continue;
			}

			$name     = trim( $row[0] );
			$date_str = trim( $row[1] );

			if ( empty( $name ) || empty( $date_str ) ) {
				continue;
			}

			$date = DateTime::createFromFormat( 'n/j/Y', $date_str );
			if ( ! $date ) {
				continue;
			}

			$join_month = (int) $date->format( 'n' );
			$join_year  = (int) $date->format( 'Y' );

			if ( $join_month !== $month ) {
				continue;
			}

			$years = $year - $join_year;

			if ( $years <= 0 || $years % 5 !== 0 ) {
				continue;
			}

			$milestones[ $years ][] = $name;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		ksort( $milestones );

		return $milestones;
	}
}
