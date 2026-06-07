<?php
use PHPUnit\Framework\TestCase;

class HolidayTest extends TestCase {

	/** Easter Sunday dates — independent reference values. */
	public function easterProvider() {
		return array(
			array( 2024, '2024-03-31' ),
			array( 2025, '2025-04-20' ),
			array( 2026, '2026-04-05' ),
			array( 2027, '2027-03-28' ),
			array( 2030, '2030-04-21' ),
		);
	}

	/** @dataProvider easterProvider */
	public function test_easter_sunday_is_a_holiday( $year, $easter ) {
		$this->assertTrue( TSB_Holidays::is_holiday( $easter ), "Easter $easter should be a holiday" );
	}

	public function test_fixed_holidays() {
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-01-01' ), 'Nytårsdag' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-12-25' ), 'Juledag' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-12-26' ), '2. juledag' );
	}

	public function test_derived_easter_offsets_2026() {
		// Easter 2026-04-05.
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-04-02' ), 'Skærtorsdag (-3)' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-04-03' ), 'Langfredag (-2)' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-04-06' ), '2. påskedag (+1)' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-05-14' ), 'Kristi himmelfart (+39)' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-05-24' ), 'Pinsedag (+49)' );
		$this->assertTrue( TSB_Holidays::is_holiday( '2026-05-25' ), '2. pinsedag (+50)' );
	}

	public function test_store_bededag_abolished_from_2024() {
		// 2023: 4th Friday after Easter (Easter 2023-04-09 -> +26 = 2023-05-05).
		$this->assertTrue( TSB_Holidays::is_holiday( '2023-05-05' ), 'Store bededag exists pre-2024' );
		// 2024: Easter 2024-03-31 -> +26 = 2024-04-26, must NOT be a holiday.
		$this->assertFalse( TSB_Holidays::is_holiday( '2024-04-26' ), 'Store bededag gone from 2024' );
	}

	public function test_ordinary_day_is_not_a_holiday() {
		$this->assertFalse( TSB_Holidays::is_holiday( '2026-06-09' ) ); // a plain Tuesday
	}
}
