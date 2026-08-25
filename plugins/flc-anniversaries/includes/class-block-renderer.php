<?php
/**
 * Server-side render callback for the FLC Anniversaries block.
 */
class FLC_Anniversaries_Block_Renderer {

	/**
	 * @param array{showAllAnniversaries?: bool} $attributes
	 */
	public static function render( array $attributes = array() ): string {
		$show_all = ! empty( $attributes['showAllAnniversaries'] );

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

		$milestones = self::load_milestones( $month, $year, $show_all );

		if ( empty( $milestones ) ) {
			$empty_message = $show_all ? 'No anniversaries this month.' : 'No milestone anniversaries this month.';
			$html .= '<p class="flc-anniversaries-none">' . esc_html( $empty_message ) . '</p>';
		} else {
			foreach ( $milestones as $years => $names ) {
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
	 * Returns an associative array of [ years => display names[] ] for members
	 * whose anniversary falls in the given month. When $show_all is false
	 * (the default), only years of membership (relative to $year) that are a
	 * positive multiple of 5 are included; when true, every member with a
	 * positive tenure is included, grouped by their exact years of membership.
	 *
	 * @return array<int, list<string>>
	 */
	private static function load_milestones( int $month, int $year, bool $show_all = false ): array {
		$db   = new FLC_Anniversaries_DB();
		$rows = $db->get_by_month( $month );

		return FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $rows, $month, $year, ! $show_all );
	}
}
