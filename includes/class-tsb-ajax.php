<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Ajax {

	protected static function verify() {
		if ( ! check_ajax_referer( 'tsb_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Sikkerhedstjek fejlede. Genindlæs siden.' ), 403 );
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
		) );
	}

	public static function book() {
		self::verify();
		$s = TSB_Availability::settings();

		// Honeypot: bots fill hidden field. Pretend success, store nothing.
		if ( ! empty( $_POST['tsb_hp'] ) ) {
			wp_send_json_success( array( 'message' => 'Tak! Din tid er booket.' ) );
		}

		$date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time  = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$msg   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			wp_send_json_error( array( 'message' => 'Ugyldigt tidspunkt.' ) );
		}
		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Udfyld navn og en gyldig e-mail.' ) );
		}
		if ( ! self::captcha_ok( $s ) ) {
			wp_send_json_error( array( 'message' => 'Bekræft venligst at du ikke er en robot.' ) );
		}
		if ( ! self::slot_available( $date, $time ) ) {
			wp_send_json_error( array( 'message' => 'Tidspunktet er desværre ikke længere ledigt.' ) );
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
			wp_send_json_error( array( 'message' => 'Tidspunktet blev netop optaget. Vælg et andet.' ) );
		}

		$booking_id = (int) $wpdb->insert_id;
		self::send_mails( $s, compact( 'name', 'email', 'phone', 'msg', 'date', 'time', 'booking_id' ) );

		wp_send_json_success( array( 'message' => 'Tak! Din tid er booket. Du modtager en bekræftelse på e-mail.' ) );
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
					'summary'     => strtr( $s['ics_summary'], $repl ),
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
			wp_mail( $to, $tr( $s['admin_subject'] ), $tr( $s['admin_body'] ), $headers );
		}
		if ( ! empty( $s['customer_confirm'] ) && is_email( $d['email'] ) ) {
			$headers = $from ? array( $from ) : array();
			if ( $ics_cb ) {
				add_action( 'phpmailer_init', $ics_cb );
			}
			wp_mail( $d['email'], $tr( $s['customer_subject'] ), $tr( $s['customer_body'] ), $headers );
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
