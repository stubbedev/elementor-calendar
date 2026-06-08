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

		$fields = TSB_Availability::fields();

		$date   = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time   = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$msg    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$custom = isset( $_POST['custom'] ) ? sanitize_text_field( wp_unslash( $_POST['custom'] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid time slot.', 'tsb' ) ) );
		}
		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name and a valid email.', 'tsb' ) ) );
		}
		// Required optional fields (only those enabled + marked required).
		if ( ( $fields['phone']['show'] && $fields['phone']['req'] && '' === $phone )
			|| ( $fields['message']['show'] && $fields['message']['req'] && '' === $msg )
			|| ( $fields['custom']['show'] && $fields['custom']['req'] && '' === $custom ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'tsb' ) ) );
		}
		if ( ! empty( $s['consent_enable'] ) && empty( $_POST['consent'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please accept the consent to continue.', 'tsb' ) ) );
		}
		if ( ! self::captcha_ok( $s ) ) {
			wp_send_json_error( array( 'message' => __( 'Please confirm that you are not a robot.', 'tsb' ) ) );
		}

		// Fold the custom field into the stored/emailed message.
		if ( $fields['custom']['show'] && '' !== $custom ) {
			$label = $fields['custom']['label'] ? $fields['custom']['label'] : __( 'Custom', 'tsb' );
			$msg   = $label . ': ' . $custom . ( '' !== $msg ? "\n\n" . $msg : '' );
		}
		// Drop a field that's off entirely.
		if ( ! $fields['phone']['show'] ) {
			$phone = '';
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
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		// false => UNIQUE(slot_date, slot_time) collision: just taken.
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'That time was just taken. Please choose another.', 'tsb' ) ) );
		}

		$booking_id = (int) $wpdb->insert_id;
		self::send_mails( $s, compact( 'name', 'email', 'phone', 'msg', 'date', 'time', 'booking_id' ) );

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

	protected static function send_mails( $s, $d ) {
		$repl = array(
			'{name}'    => $d['name'],
			'{email}'   => $d['email'],
			'{phone}'   => $d['phone'],
			'{message}' => $d['msg'],
			'{date}'    => $d['date'],
			'{time}'    => $d['time'],
		);
		$tr = function ( $t ) use ( $repl ) {
			return strtr( (string) $t, $repl );
		};

		// WPML String Translation: localize the configured templates first.
		$admin_subject = TSB_I18N::translate( 'admin_subject', $s['admin_subject'] );
		$admin_body    = TSB_I18N::translate( 'admin_body', $s['admin_body'] );
		$cust_subject  = TSB_I18N::translate( 'customer_subject', $s['customer_subject'] );
		$cust_body     = TSB_I18N::translate( 'customer_body', $s['customer_body'] );
		$ics_summary   = TSB_I18N::translate( 'ics_summary', $s['ics_summary'] );

		// Optional custom sender.
		$from = '';
		if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
			$from = $s['from_name'] ? sprintf( 'From: %s <%s>', $s['from_name'], $s['from_email'] ) : 'From: ' . $s['from_email'];
		}

		// Build the .ics once, attach as a string via a one-shot PHPMailer hook.
		$ics_cb = null;
		if ( ! empty( $s['ics_attach'] ) ) {
			$ics = TSB_ICS::generate(
				array(
					'id'          => $d['booking_id'],
					'date'        => $d['date'],
					'time'        => $d['time'],
					'name'        => $d['name'],
					'email'       => $d['email'],
					'summary'     => strtr( $ics_summary, $repl ),
					'location'    => $s['ics_location'],
					'description' => $d['msg'],
				),
				$s['slot_minutes']
			);
			$ics_cb = function ( $phpmailer ) use ( $ics ) {
				$phpmailer->addStringAttachment( $ics, 'booking.ics', 'base64', 'text/calendar; charset=utf-8; method=PUBLISH' );
			};
		}

		if ( ! empty( $s['admin_notify'] ) ) {
			$to      = ! empty( $s['admin_to'] ) ? $s['admin_to'] : get_option( 'admin_email' );
			$headers = array();
			if ( $from ) {
				$headers[] = $from;
			}
			// Lets admin reply straight to the customer.
			if ( is_email( $d['email'] ) ) {
				$headers[] = 'Reply-To: ' . $d['name'] . ' <' . $d['email'] . '>';
			}
			wp_mail( $to, $tr( $admin_subject ), $tr( $admin_body ), $headers );
		}
		if ( ! empty( $s['customer_confirm'] ) && is_email( $d['email'] ) ) {
			$headers = $from ? array( $from ) : array();
			if ( $ics_cb ) {
				add_action( 'phpmailer_init', $ics_cb );
			}
			wp_mail( $d['email'], $tr( $cust_subject ), $tr( $cust_body ), $headers );
			if ( $ics_cb ) {
				remove_action( 'phpmailer_init', $ics_cb );
			}
		}
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
