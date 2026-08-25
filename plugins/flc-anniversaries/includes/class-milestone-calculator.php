<?php

/**
 * Groups ingested members into milestone-year buckets for a given
 * month/year. Pure PHP, no WordPress dependency, so it can be unit tested
 * directly against whatever FLC_Anniversaries_Report_Parser produces.
 */
class FLC_Anniversaries_Milestone_Calculator {

	/**
	 * @param list<array{first_name: string, last_initial: string, anniversary_date: string}> $members
	 * @param bool $milestones_only When true (default), only years that are a positive multiple
	 *                              of 5 are included. When false, every member with a positive
	 *                              tenure in the given month is included, grouped by their exact
	 *                              years of membership.
	 * @return array<int, list<string>> Years => sorted display names ("First L."), keyed ascending by years.
	 */
	public static function group_by_milestone( array $members, int $month, int $year, bool $milestones_only = true ): array {
		$milestones = [];

		foreach ( $members as $member ) {
			$anniversary_month = (int) substr( $member['anniversary_date'], 5, 2 );
			$anniversary_year  = (int) substr( $member['anniversary_date'], 0, 4 );

			if ( $anniversary_month !== $month ) {
				continue;
			}

			$years = $year - $anniversary_year;

			if ( $years <= 0 ) {
				continue;
			}

			if ( $milestones_only && $years % 5 !== 0 ) {
				continue;
			}

			$milestones[ $years ][] = $member['first_name'] . ' ' . $member['last_initial'] . '.';
		}

		foreach ( $milestones as &$names ) {
			sort( $names );
		}
		unset( $names );

		ksort( $milestones );

		return $milestones;
	}
}
