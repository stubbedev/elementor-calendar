<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public holidays via the Nager.Date API (https://date.nager.at) — free, no key.
 * Each country+year is fetched once and cached in a transient. Denmark also has
 * an offline computus fallback, so DK keeps working with no network (and under
 * the test harness, which has no HTTP).
 */
class TSB_Holidays {

	const API = 'https://date.nager.at/api/v3/PublicHolidays/%d/%s';
	const TTL = 2592000; // 30 days

	protected static $cache = array();

	/** ISO-3166 alpha-2 => display name. Curated subset of Nager's country list. */
	public static function countries() {
		return array(
			'DK' => 'Danmark', 'SE' => 'Sverige', 'NO' => 'Norge', 'FI' => 'Finland',
			'IS' => 'Island', 'DE' => 'Tyskland', 'NL' => 'Holland', 'BE' => 'Belgien',
			'GB' => 'Storbritannien', 'IE' => 'Irland', 'FR' => 'Frankrig', 'ES' => 'Spanien',
			'PT' => 'Portugal', 'IT' => 'Italien', 'CH' => 'Schweiz', 'AT' => 'Østrig',
			'PL' => 'Polen', 'CZ' => 'Tjekkiet', 'SK' => 'Slovakiet', 'HU' => 'Ungarn',
			'EE' => 'Estland', 'LV' => 'Letland', 'LT' => 'Litauen', 'GR' => 'Grækenland',
			'RO' => 'Rumænien', 'BG' => 'Bulgarien', 'HR' => 'Kroatien', 'SI' => 'Slovenien',
			'LU' => 'Luxembourg', 'US' => 'USA', 'CA' => 'Canada', 'AU' => 'Australien',
			'NZ' => 'New Zealand', 'JP' => 'Japan', 'BR' => 'Brasilien', 'MX' => 'Mexico',
			'ZA' => 'Sydafrika', 'IN' => 'Indien', 'TR' => 'Tyrkiet', 'UA' => 'Ukraine',
		);
	}

	/**
	 * Holiday date map for one country+year: 'Y-m-d' => name.
	 */
	public static function for_country_year( $country, $year ) {
		$country = strtoupper( substr( (string) $country, 0, 2 ) );
		$year    = (int) $year;
		$mem     = $country . ':' . $year;
		if ( isset( self::$cache[ $mem ] ) ) {
			return self::$cache[ $mem ];
		}

		$transient = 'tsb_hol_' . $country . '_' . $year;
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( $transient );
			if ( is_array( $cached ) ) {
				self::$cache[ $mem ] = $cached;
				return $cached;
			}
		}

		$map = self::fetch_api( $country, $year );
		if ( null === $map ) {
			// API unavailable: offline computus for DK, empty elsewhere.
			$map = ( 'DK' === $country ) ? self::dk_year( $year ) : array();
			if ( function_exists( 'set_transient' ) ) {
				// Short cache so we retry the API soon after a failure.
				$ttl = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
				set_transient( $transient, $map, $ttl );
			}
		} elseif ( function_exists( 'set_transient' ) ) {
			set_transient( $transient, $map, self::TTL );
		}

		self::$cache[ $mem ] = $map;
		return $map;
	}

	/** True if $ymd is a holiday in ANY of $countries (ISO alpha-2). */
	public static function is_holiday( $ymd, $countries = array( 'DK' ) ) {
		$countries = array_filter( (array) $countries );
		if ( empty( $countries ) ) {
			$countries = array( 'DK' );
		}
		$year = (int) substr( $ymd, 0, 4 );
		foreach ( $countries as $c ) {
			$map = self::for_country_year( $c, $year );
			if ( isset( $map[ $ymd ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array|null 'Y-m-d' => name, or null on any failure. */
	protected static function fetch_api( $country, $year ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return null;
		}
		$resp = wp_remote_get( sprintf( self::API, $year, $country ), array( 'timeout' => 6 ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}
		$rows = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $rows ) ) {
			return null;
		}
		$map = array();
		foreach ( $rows as $r ) {
			if ( empty( $r['date'] ) ) {
				continue;
			}
			$map[ $r['date'] ] = ! empty( $r['localName'] ) ? $r['localName'] : ( $r['name'] ?? '' );
		}
		return $map;
	}

	/* ---------- offline Danish fallback (computus) ---------- */

	/** Back-compat alias used by older callers/tests. */
	public static function for_year( $year ) {
		return self::dk_year( $year );
	}

	/** @return array 'Y-m-d' => name, Danish holidays for $year. */
	public static function dk_year( $year ) {
		$year   = (int) $year;
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
		return $h;
	}

	/** Computus (Meeus/Jones/Butcher) -> Easter Sunday as DateTime. */
	public static function easter( $year ) {
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
