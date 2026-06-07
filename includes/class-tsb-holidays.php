<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Danish public holidays (helligdage), computed — no external API.
 * Note: Store Bededag was abolished as a public holiday from 2024, so it is
 * only included for years before 2024.
 */
class TSB_Holidays {

	protected static $cache = array();

	/** @return array map 'Y-m-d' => holiday name for $year. */
	public static function for_year( $year ) {
		if ( isset( self::$cache[ $year ] ) ) {
			return self::$cache[ $year ];
		}

		$easter = self::easter( $year );
		$off    = function ( $days ) use ( $easter ) {
			$c = clone $easter;
			$c->modify( ( $days >= 0 ? '+' : '' ) . $days . ' days' );
			return $c->format( 'Y-m-d' );
		};

		$h = array();
		$h[ "$year-01-01" ] = 'Nytårsdag';
		$h[ $off( -3 ) ]    = 'Skærtorsdag';
		$h[ $off( -2 ) ]    = 'Langfredag';
		$h[ $off( 0 ) ]     = 'Påskedag';
		$h[ $off( 1 ) ]     = '2. påskedag';
		if ( $year < 2024 ) {
			$h[ $off( 26 ) ] = 'Store bededag';
		}
		$h[ $off( 39 ) ]    = 'Kristi himmelfartsdag';
		$h[ $off( 49 ) ]    = 'Pinsedag';
		$h[ $off( 50 ) ]    = '2. pinsedag';
		$h[ "$year-12-25" ] = 'Juledag';
		$h[ "$year-12-26" ] = '2. juledag';

		self::$cache[ $year ] = $h;
		return $h;
	}

	public static function is_holiday( $ymd ) {
		$year = (int) substr( $ymd, 0, 4 );
		$h    = self::for_year( $year );
		return isset( $h[ $ymd ] );
	}

	/** Computus (Meeus/Jones/Butcher) -> Easter Sunday as DateTime. */
	protected static function easter( $year ) {
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return new DateTime( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
	}
}
