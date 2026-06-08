<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Ajax {

	protected static function verify() {
		if ( ! check_ajax_referer( 'tsb_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'tsb' ), 'code' => 'nonce' ), 403 );
		}
		// Render slots/emails in the visitor's language (WPML/Polylang).
		if ( ! empty( $_POST['lang'] ) ) {
			TSB_I18N::switch_language( sanitize_text_field( wp_unslash( $_POST['lang'] ) ) );
		}
	}

	public static function get_slots() {
		self::verify();
		$s   = TSB_Availability::settings();
		$tz  = wp_timezone();
		$min = ( new DateTime( 'today', $tz ) )->format( 'Y-m-d' );
		$max = ( new DateTime( 'today', $tz ) )->modify( '+' . max( 1, (int) $s['days_ahead'] ) . ' days' )->format( 'Y-m-d' );
		wp_send_json_success( array(
			'days'  => TSB_Availability::build(),
			'range' => array( 'min' => $min, 'max' => $max ),
			'nonce' => wp_create_nonce( 'tsb_nonce' ), // lets a cached page refresh its token
			'stamp' => self::make_stamp(),             // time-trap token
		) );
	}

	/** Signed timestamp token issued with the slots; checked on booking. */
	protected static function make_stamp() {
		$t = time();
		return $t . '.' . substr( hash_hmac( 'sha256', (string) $t, wp_salt( 'nonce' ) ), 0, 16 );
	}

	/** Valid signature + submitted no faster than 3s and within 2h. */
	protected static function check_stamp( $stamp ) {
		if ( ! preg_match( '/^(\d+)\.([a-f0-9]{16})$/', (string) $stamp, $m ) ) {
			return false;
		}
		$t = (int) $m[1];
		$sig = substr( hash_hmac( 'sha256', (string) $t, wp_salt( 'nonce' ) ), 0, 16 );
		if ( ! hash_equals( $sig, $m[2] ) ) {
			return false;
		}
		$age = time() - $t;
		return $age >= 3 && $age <= 7200;
	}

	public static function book() {
		self::verify();
		$s = TSB_Availability::settings();

		// Honeypot: bots fill hidden field. Pretend success, store nothing.
		if ( ! empty( $_POST['tsb_hp'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Thank you! Your time is booked.', 'tsb' ) ) );
		}

		// Time-trap: blocks instant/scripted posts that skipped loading the slots.
		if ( ! self::check_stamp( $_POST['stamp'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a moment and try again.', 'tsb' ), 'code' => 'stamp' ) );
		}

		$date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time  = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid time slot.', 'tsb' ) ) );
		}
		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name and a valid email.', 'tsb' ) ) );
		}

		// Dynamic, user-defined fields. phone → its own column; a textarea named
		// "message" is the raw message body; everything else is labelled into it.
		$phone     = '';
		$message   = '';
		$extra     = array();
		$fieldvals = array();
		foreach ( TSB_Availability::form_fields() as $f ) {
			$raw = isset( $_POST[ $f['name'] ] ) ? wp_unslash( $_POST[ $f['name'] ] ) : '';
			$val = ( 'textarea' === $f['type'] ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			if ( $f['required'] && '' === trim( $val ) ) {
				wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'tsb' ) ) );
			}
			$fieldvals[ $f['name'] ] = $val; // every field is available for email interpolation
			if ( '' === $val ) {
				continue;
			}
			if ( 'phone' === $f['name'] ) {
				$phone = $val;
			} elseif ( 'message' === $f['name'] && 'textarea' === $f['type'] ) {
				$message = $val;
			} else {
				$extra[] = $f['label'] . ': ' . $val;
			}
		}
		$msg = implode( "\n", $extra );
		if ( '' !== $message ) {
			$msg = ( '' !== $msg ? $msg . "\n\n" : '' ) . $message;
		}

		if ( ! empty( $s['consent_enable'] ) && empty( $_POST['consent'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please accept the consent to continue.', 'tsb' ) ) );
		}
		if ( ! self::captcha_ok( $s ) ) {
			wp_send_json_error( array( 'message' => __( 'Please confirm that you are not a robot.', 'tsb' ) ) );
		}
		if ( ! self::slot_available( $date, $time ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, that time is no longer available.', 'tsb' ) ) );
		}

		global $wpdb;
		$ok = $wpdb->insert(
			TSB_DB::bookings_table(),
			array(
				'slot_date' => $date,
				'slot_time' => $time . ':00',
				'name'      => $name,
				'email'     => $email,
				'phone'     => $phone,
				'message'   => $msg,
				'status'    => 'confirmed',
				'active'    => 1,
				'meta'      => wp_json_encode( $fieldvals ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		// false => UNIQUE(slot_date, slot_time) collision: just taken.
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'That time was just taken. Please choose another.', 'tsb' ) ) );
		}

		$booking_id = (int) $wpdb->insert_id;
		TSB_Emails::on_book( array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $msg,
			'date'    => $date,
			'time'    => $time,
			'ref'     => $booking_id,
			'fields'  => $fieldvals,
		) );

		wp_send_json_success( array(
			'message' => __( 'Thank you! Your time is booked. You will receive a confirmation by email.', 'tsb' ),
			'booking' => array(
				'date' => $date,
				'time' => $time,
				'name' => $name,
				'ref'  => $booking_id,
			),
		) );
	}

	/** Verify reCAPTCHA v2/v3 + hCaptcha. honeypot + none always pass here. */
	protected static function captcha_ok( $s ) {
		$mode = $s['captcha_mode'];
		if ( 'none' === $mode || 'honeypot' === $mode ) {
			return true;
		}
		$token = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';
		if ( '' === $token || empty( $s['captcha_secret'] ) ) {
			return false;
		}
		$url = ( 'hcaptcha' === $mode )
			? 'https://hcaptcha.com/siteverify'
			: 'https://www.google.com/recaptcha/api/siteverify';

		$resp = wp_remote_post( $url, array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => $s['captcha_secret'],
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		) );
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) ) {
			return false;
		}
		// v3 returns a score; enforce the configured threshold.
		if ( 'recaptcha_v3' === $mode && isset( $body['score'] ) ) {
			return (float) $body['score'] >= (float) $s['captcha_min_score'];
		}
		return true;
	}

	protected static function slot_available( $date, $time ) {
		foreach ( TSB_Availability::build() as $d ) {
			if ( $d['date'] === $date && in_array( $time, $d['slots'], true ) ) {
				return true;
			}
		}
		return false;
	}
}
