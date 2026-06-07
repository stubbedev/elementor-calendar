<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_ICS {

	/**
	 * Build a VCALENDAR string for one booking.
	 *
	 * @param array $d   keys: id, date (Y-m-d), time (H:i), name, email, summary, location, description
	 * @param int   $minutes slot length, for DTEND
	 */
	public static function generate( $d, $minutes ) {
		$tz    = wp_timezone();
		$start = new DateTime( $d['date'] . ' ' . $d['time'], $tz );
		$end   = clone $start;
		$end->modify( '+' . max( 5, (int) $minutes ) . ' minutes' );

		$utc = new DateTimeZone( 'UTC' );
		$fmt = function ( DateTime $dt ) use ( $utc ) {
			$c = clone $dt;
			$c->setTimezone( $utc );
			return $c->format( 'Ymd\THis\Z' );
		};

		$now  = new DateTime( 'now', $utc );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$uid  = 'tsb-' . (int) $d['id'] . '@' . ( $host ? $host : 'localhost' );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Timeslot Booking//DA',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . $now->format( 'Ymd\THis\Z' ),
			'DTSTART:' . $fmt( $start ),
			'DTEND:' . $fmt( $end ),
			'SUMMARY:' . self::esc( $d['summary'] ),
		);
		if ( ! empty( $d['description'] ) ) {
			$lines[] = 'DESCRIPTION:' . self::esc( $d['description'] );
		}
		if ( ! empty( $d['location'] ) ) {
			$lines[] = 'LOCATION:' . self::esc( $d['location'] );
		}
		if ( ! empty( $d['email'] ) ) {
			$lines[] = 'ATTENDEE;CN=' . self::esc( $d['name'] ) . ':mailto:' . $d['email'];
		}
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		// RFC 5545 uses CRLF line endings.
		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	/** Escape per RFC 5545: backslash, comma, semicolon, newline. */
	protected static function esc( $s ) {
		$s = str_replace( array( '\\', "\n", ',', ';' ), array( '\\\\', '\\n', '\\,', '\\;' ), (string) $s );
		return $s;
	}

	/** Fold lines longer than 75 octets. */
	protected static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out = '';
		while ( strlen( $line ) > 75 ) {
			$out  .= substr( $line, 0, 75 ) . "\r\n ";
			$line  = substr( $line, 75 );
		}
		return $out . $line;
	}
}
