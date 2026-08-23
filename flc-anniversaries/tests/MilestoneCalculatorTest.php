<?php

use PHPUnit\Framework\TestCase;

class MilestoneCalculatorTest extends TestCase {

	private function member( string $first, string $initial, string $date ): array {
		return [ 'first_name' => $first, 'last_initial' => $initial, 'anniversary_date' => $date ];
	}

	public function test_empty_input_returns_empty_array(): void {
		$this->assertSame( [], FLC_Anniversaries_Milestone_Calculator::group_by_milestone( [], 5, 2026 ) );
	}

	public function test_groups_by_years_since_anniversary(): void {
		$members = [
			$this->member( 'Amy', 'A', '2021-05-01' ), // 5yr
			$this->member( 'Ben', 'B', '2016-05-01' ), // 10yr
		];
		$result = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [ 'Amy A.' ], $result[5] );
		$this->assertSame( [ 'Ben B.' ], $result[10] );
	}

	public function test_excludes_members_outside_target_month(): void {
		$members = [ $this->member( 'Amy', 'A', '2021-06-01' ) ];
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [], $result );
	}

	public function test_excludes_non_multiple_of_five(): void {
		$members = [ $this->member( 'Amy', 'A', '2019-05-01' ) ]; // 7 years
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [], $result );
	}

	public function test_excludes_zero_years(): void {
		$members = [ $this->member( 'Amy', 'A', '2026-05-01' ) ]; // joined this year
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [], $result );
	}

	public function test_excludes_future_anniversary(): void {
		$members = [ $this->member( 'Amy', 'A', '2031-05-01' ) ]; // anniversary year after target year
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [], $result );
	}

	public function test_names_within_a_group_are_sorted(): void {
		$members = [
			$this->member( 'Zoe', 'Z', '2021-05-01' ),
			$this->member( 'Amy', 'A', '2021-05-01' ),
			$this->member( 'Mia', 'M', '2021-05-01' ),
		];
		$result = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [ 'Amy A.', 'Mia M.', 'Zoe Z.' ], $result[5] );
	}

	public function test_groups_are_keyed_ascending_by_years(): void {
		$members = [
			$this->member( 'Amy', 'A', '1976-05-01' ), // 50yr
			$this->member( 'Ben', 'B', '2021-05-01' ), // 5yr
			$this->member( 'Cal', 'C', '2001-05-01' ), // 25yr
		];
		$result = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( [ 5, 25, 50 ], array_keys( $result ) );
	}

	public function test_display_name_format_is_first_name_space_initial_dot(): void {
		$members = [ $this->member( 'Amy', 'A', '2021-05-01' ) ];
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026 );
		$this->assertSame( 'Amy A.', $result[5][0] );
	}

	// -------------------------------------------------------------------------
	// $milestones_only = false — the block/shortcode "show all anniversaries"
	// option.
	// -------------------------------------------------------------------------

	public function test_show_all_includes_non_multiple_of_five_years(): void {
		$members = [ $this->member( 'Amy', 'A', '2019-05-01' ) ]; // 7 years
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026, false );
		$this->assertSame( [ 'Amy A.' ], $result[7] );
	}

	public function test_show_all_still_excludes_zero_and_future_years(): void {
		$members = [
			$this->member( 'Amy', 'A', '2026-05-01' ), // joined this year
			$this->member( 'Ben', 'B', '2031-05-01' ), // anniversary year after target year
		];
		$result = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026, false );
		$this->assertSame( [], $result );
	}

	public function test_show_all_still_excludes_members_outside_target_month(): void {
		$members = [ $this->member( 'Amy', 'A', '2021-06-01' ) ];
		$result  = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026, false );
		$this->assertSame( [], $result );
	}

	public function test_show_all_mixes_milestone_and_non_milestone_years(): void {
		$members = [
			$this->member( 'Amy', 'A', '2021-05-01' ), // 5yr (milestone)
			$this->member( 'Ben', 'B', '2019-05-01' ), // 7yr (not a milestone)
		];
		$result = FLC_Anniversaries_Milestone_Calculator::group_by_milestone( $members, 5, 2026, false );
		$this->assertSame( [ 5, 7 ], array_keys( $result ) );
	}
}
