<?php
use PHPUnit\Framework\TestCase;

class AvailabilityTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['tsb_test_option']  = array();
		$GLOBALS['tsb_test_blocked'] = array();
		$GLOBALS['tsb_test_booked']  = array();
	}

	protected function setOption( array $opt ) {
		$GLOBALS['tsb_test_option'] = $opt;
	}

	public function test_no_closed_weekday_is_returned() {
		// Default week: Mon–Fri open, weekend closed.
		foreach ( TSB_Availability::build() as $day ) {
			$dow = (int) ( new DateTime( $day['date'] ) )->format( 'N' );
			$this->assertLessThanOrEqual( 5, $dow, "Weekend day {$day['date']} should be closed" );
		}
	}

	public function test_holidays_are_excluded() {
		$this->setOption( array( 'days_ahead' => 365, 'block_holidays' => 1 ) );
		foreach ( TSB_Availability::build() as $day ) {
			$this->assertFalse( TSB_Holidays::is_holiday( $day['date'] ), "{$day['date']} is a holiday, must be excluded" );
		}
	}

	public function test_slots_are_within_hours_and_stepped() {
		$this->setOption( array( 'slot_minutes' => 30 ) ); // default week 9–17
		$days = TSB_Availability::build();
		$this->assertNotEmpty( $days, 'Expected at least one bookable day' );
		foreach ( $days as $day ) {
			foreach ( $day['slots'] as $hm ) {
				$this->assertMatchesRegularExpression( '/^\d{2}:\d{2}$/', $hm );
				list( $h, $m ) = array_map( 'intval', explode( ':', $hm ) );
				$mins = $h * 60 + $m;
				$this->assertGreaterThanOrEqual( 9 * 60, $mins, "slot $hm before opening" );
				$this->assertLessThan( 17 * 60, $mins, "slot $hm at/after close" );
				$this->assertSame( 0, ( $mins - 9 * 60 ) % 30, "slot $hm not on a 30-min step" );
			}
		}
	}

	public function test_booked_time_is_removed() {
		$days = TSB_Availability::build();
		$this->assertNotEmpty( $days );
		$target = $days[0];
		$slot   = $target['slots'][0];

		$GLOBALS['tsb_test_booked'][ $target['date'] ] = array( $slot . ':00' );
		$rebuilt = $this->dayByDate( TSB_Availability::build(), $target['date'] );
		$this->assertNotContains( $slot, $rebuilt['slots'], 'Booked slot must disappear' );
	}

	public function test_individual_blocked_time_is_removed() {
		$days   = TSB_Availability::build();
		$target = $days[0];
		$slot   = $target['slots'][0];

		$GLOBALS['tsb_test_blocked'][ $target['date'] ] = array( tsb_block_row( $slot . ':00' ) );
		$rebuilt = $this->dayByDate( TSB_Availability::build(), $target['date'] );
		$this->assertNotContains( $slot, $rebuilt['slots'], 'Blocked slot must disappear' );
	}

	public function test_whole_day_block_removes_the_date() {
		$days   = TSB_Availability::build();
		$target = $days[0]['date'];

		$GLOBALS['tsb_test_blocked'][ $target ] = array( tsb_block_row( null ) ); // null = whole day
		$rebuilt = $this->dayByDate( TSB_Availability::build(), $target );
		$this->assertNull( $rebuilt, 'Whole-day-blocked date must vanish entirely' );
	}

	public function test_lead_hours_pushes_out_slots() {
		$tz    = new DateTimeZone( 'Europe/Copenhagen' );
		$lead  = 48;
		$this->setOption( array( 'lead_hours' => $lead, 'days_ahead' => 365 ) );
		$cut   = ( new DateTime( 'now', $tz ) )->modify( "+$lead hours" );

		foreach ( TSB_Availability::build() as $day ) {
			foreach ( $day['slots'] as $hm ) {
				$dt = new DateTime( $day['date'] . ' ' . $hm, $tz );
				$this->assertGreaterThan( $cut, $dt, "slot {$day['date']} $hm is inside the lead window" );
			}
		}
	}

	protected function dayByDate( array $days, $date ) {
		foreach ( $days as $d ) {
			if ( $d['date'] === $date ) {
				return $d;
			}
		}
		return null;
	}
}
