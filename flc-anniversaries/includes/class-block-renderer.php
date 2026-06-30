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
		$result   = FLC_Anniversaries_Roster_Parser::parse( $csv_path, $month, $year );

		// Key 0 = file-level error (not found / unreadable).
		if ( isset( $result['errors'][0] ) ) {
			return '<p class="flc-anniversaries-none">Anniversary data not found.</p>';
		}

		// Row-level errors: log each one but keep rendering with whatever is valid.
		foreach ( $result['errors'] as $message ) {
			trigger_error( esc_html( $message ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		}

		return $result['milestones'];
	}
}
