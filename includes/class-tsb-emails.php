<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transactional emails: confirmation, admin notification, move, cancel, reminder.
 * Each template stores compiled HTML (designed in the admin MJML editor) plus its
 * MJML source. At send time we interpolate {{tokens}} into the subject + HTML and
 * send an HTML email. The reminder runs from an hourly cron.
 */
class TSB_Emails {

	const CRON_HOOK = 'tsb_send_reminders';

	/* ---------------- defaults ---------------- */

	/** Responsive HTML shell around inner content (used for the default templates). */
	public static function shell( $inner ) {
		return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f5;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;">'
			. '<tr><td align="center" style="padding:24px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
			. '<tr><td style="padding:32px;">' . $inner . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	protected static function mjml( $inner ) {
		return "<mjml>\n  <mj-body background-color=\"#f4f4f5\">\n    <mj-section background-color=\"#ffffff\" border-radius=\"8px\">\n      <mj-column>\n$inner\n      </mj-column>\n    </mj-section>\n  </mj-body>\n</mjml>";
	}

	/** Default templates: event => [enabled, subject, mjml, html]. */
	public static function default_templates() {
		$t = array();

		$t['confirm'] = array(
			'enabled' => 1,
			'subject' => __( 'Confirmation of your booking {{date}} at {{time}}', 'tsb' ),
			'mjml'    => self::mjml(
				"        <mj-text font-size=\"20px\" font-weight=\"bold\">Booking confirmed</mj-text>\n"
				. "        <mj-text>Hi {{name}},</mj-text>\n"
				. "        <mj-text>Your booking is confirmed for <strong>{{date}} at {{time}}</strong>.</mj-text>\n"
				. "        <mj-text color=\"#6b7280\">Reference: {{ref}}</mj-text>"
			),
			'html'    => self::shell(
				'<h1 style="font-size:20px;margin:0 0 16px;">Booking confirmed</h1>'
				. '<p>Hi {{name}},</p>'
				. '<p>Your booking is confirmed for <strong>{{date}} at {{time}}</strong>.</p>'
				. '<p style="color:#6b7280;">Reference: {{ref}}</p>'
			),
		);

		$t['admin'] = array(
			'enabled' => 1,
			'to'      => '',
			'subject' => __( 'New booking: {{date}} {{time}}', 'tsb' ),
			'mjml'    => self::mjml(
				"        <mj-text font-size=\"18px\" font-weight=\"bold\">New booking</mj-text>\n"
				. "        <mj-text>{{name}} &middot; {{email}} &middot; {{phone}}</mj-text>\n"
				. "        <mj-text><strong>{{date}} at {{time}}</strong></mj-text>\n"
				. "        <mj-text>{{message}}</mj-text>"
			),
			'html'    => self::shell(
				'<h1 style="font-size:18px;margin:0 0 16px;">New booking</h1>'
				. '<p>{{name}} &middot; {{email}} &middot; {{phone}}</p>'
				. '<p><strong>{{date}} at {{time}}</strong></p>'
				. '<p style="white-space:pre-line;">{{message}}</p>'
			),
		);

		$t['move'] = array(
			'enabled' => 1,
			'subject' => __( 'Your booking has been moved to {{date}} {{time}}', 'tsb' ),
			'mjml'    => self::mjml(
				"        <mj-text font-size=\"20px\" font-weight=\"bold\">Booking moved</mj-text>\n"
				. "        <mj-text>Hi {{name}},</mj-text>\n"
				. "        <mj-text>Your booking has been moved.</mj-text>\n"
				. "        <mj-text color=\"#6b7280\">From: {{old_date}} at {{old_time}}</mj-text>\n"
				. "        <mj-text><strong>New time: {{date}} at {{time}}</strong></mj-text>"
			),
			'html'    => self::shell(
				'<h1 style="font-size:20px;margin:0 0 16px;">Booking moved</h1>'
				. '<p>Hi {{name}},</p>'
				. '<p>Your booking has been moved.</p>'
				. '<p style="color:#6b7280;">From: {{old_date}} at {{old_time}}</p>'
				. '<p><strong>New time: {{date}} at {{time}}</strong></p>'
			),
		);

		$t['cancel'] = array(
			'enabled' => 1,
			'subject' => __( 'Your booking on {{date}} has been cancelled', 'tsb' ),
			'mjml'    => self::mjml(
				"        <mj-text font-size=\"20px\" font-weight=\"bold\">Booking cancelled</mj-text>\n"
				. "        <mj-text>Hi {{name}},</mj-text>\n"
				. "        <mj-text>Your booking for <strong>{{date}} at {{time}}</strong> has been cancelled.</mj-text>"
			),
			'html'    => self::shell(
				'<h1 style="font-size:20px;margin:0 0 16px;">Booking cancelled</h1>'
				. '<p>Hi {{name}},</p>'
				. '<p>Your booking for <strong>{{date}} at {{time}}</strong> has been cancelled.</p>'
			),
		);

		$t['reminder'] = array(
			'enabled' => 0,
			'subject' => __( 'Reminder: your booking {{date}} at {{time}}', 'tsb' ),
			'mjml'    => self::mjml(
				"        <mj-text font-size=\"20px\" font-weight=\"bold\">See you soon</mj-text>\n"
				. "        <mj-text>Hi {{name}},</mj-text>\n"
				. "        <mj-text>This is a reminder of your booking on <strong>{{date}} at {{time}}</strong>.</mj-text>"
			),
			'html'    => self::shell(
				'<h1 style="font-size:20px;margin:0 0 16px;">See you soon</h1>'
				. '<p>Hi {{name}},</p>'
				. '<p>This is a reminder of your booking on <strong>{{date}} at {{time}}</strong>.</p>'
			),
		);

		return $t;
	}

	/** All possible tokens (palette default). */
	public static function tokens() {
		return self::tokens_for( 'move' ); // superset (includes old_*)
	}

	/** Tokens valid for one event: base + custom fields ( + old_* on move ). */
	public static function tokens_for( $event ) {
		$base = array( 'name', 'email', 'phone', 'message', 'date', 'time', 'ref', 'site' );
		if ( 'move' === $event ) {
			$base[] = 'old_date';
			$base[] = 'old_time';
		}
		foreach ( TSB_Availability::settings()['fields'] as $f ) {
			if ( ! empty( $f['enabled'] ) ) {
				$base[] = $f['name'];
			}
		}
		return array_values( array_unique( $base ) );
	}

	/** Human descriptions for the standard tokens. */
	public static function token_labels() {
		return array(
			'name'     => __( 'Customer name', 'tsb' ),
			'email'    => __( 'Customer email', 'tsb' ),
			'phone'    => __( 'Phone', 'tsb' ),
			'message'  => __( 'Message + extra fields', 'tsb' ),
			'date'     => __( 'Booking date', 'tsb' ),
			'time'     => __( 'Booking time', 'tsb' ),
			'ref'      => __( 'Reference number', 'tsb' ),
			'site'     => __( 'Site name', 'tsb' ),
			'old_date' => __( 'Previous date (move)', 'tsb' ),
			'old_time' => __( 'Previous time (move)', 'tsb' ),
		);
	}

	/** Sample values for live preview + test sends. */
	public static function sample_vars( $event = 'confirm' ) {
		$v = array(
			'name'     => 'Jane Doe',
			'email'    => 'jane@example.com',
			'phone'    => '12 34 56 78',
			'message'  => 'Looking forward to it!',
			'date'     => '2026-06-20',
			'time'     => '09:00',
			'ref'      => '42',
			'site'     => get_bloginfo( 'name' ),
			'old_date' => '2026-06-18',
			'old_time' => '14:00',
		);
		foreach ( TSB_Availability::settings()['fields'] as $f ) {
			if ( ! empty( $f['enabled'] ) && ! isset( $v[ $f['name'] ] ) ) {
				$v[ $f['name'] ] = $f['label'] ? $f['label'] : $f['name'];
			}
		}
		return $v;
	}

	/* ---------------- sending ---------------- */

	public static function interpolate( $str, $vars ) {
		return preg_replace_callback(
			'/\{\{\s*(\w+)\s*\}\}/',
			function ( $m ) use ( $vars ) {
				return isset( $vars[ $m[1] ] ) ? $vars[ $m[1] ] : '';
			},
			(string) $str
		);
	}

	/** Build the variable map for a booking row/array. */
	protected static function vars( $b, $extra = array() ) {
		$vars = array(
			'name'    => $b['name'] ?? '',
			'email'   => $b['email'] ?? '',
			'phone'   => $b['phone'] ?? '',
			'message' => $b['message'] ?? '',
			'date'    => $b['date'] ?? '',
			'time'    => $b['time'] ?? '',
			'ref'     => $b['ref'] ?? '',
			'site'    => get_bloginfo( 'name' ),
		);
		// Every collected form field, by its name (custom fields included).
		if ( ! empty( $b['fields'] ) && is_array( $b['fields'] ) ) {
			$vars = array_merge( $vars, $b['fields'] );
		}
		return array_merge( $vars, $extra );
	}

	/** Send one template to one recipient (only if enabled). $ics_args → attach .ics. */
	protected static function send( $event, $to, $vars, $ics_args = null ) {
		$tpl = TSB_Availability::settings()['emails'][ $event ] ?? null;
		if ( ! $tpl || empty( $tpl['enabled'] ) || ! is_email( $to ) ) {
			return;
		}
		self::deliver( $event, $to, $tpl, $vars, $ics_args );
	}

	/** Render + send a template (enabled check already done by caller). */
	protected static function deliver( $event, $to, $tpl, $vars, $ics_args = null ) {
		$s       = TSB_Availability::settings();
		$subject = self::interpolate( TSB_I18N::translate( $event . '_subject', $tpl['subject'] ), $vars );
		$html    = self::interpolate( $tpl['html'], $vars );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
			$headers[] = $s['from_name']
				? sprintf( 'From: %s <%s>', $s['from_name'], $s['from_email'] )
				: 'From: ' . $s['from_email'];
		}

		// Plain-text fallback + optional .ics, via a one-shot PHPMailer hook.
		$cb = function ( $phpmailer ) use ( $html, $ics_args, $s ) {
			$phpmailer->AltBody = wp_strip_all_tags( $html );
			if ( $ics_args && ! empty( $s['ics_attach'] ) ) {
				$ics = TSB_ICS::generate( $ics_args, $s['slot_minutes'] );
				$phpmailer->addStringAttachment( $ics, 'booking.ics', 'base64', 'text/calendar; charset=utf-8; method=PUBLISH' );
			}
		};
		add_action( 'phpmailer_init', $cb );
		wp_mail( $to, $subject, $html, $headers );
		remove_action( 'phpmailer_init', $cb );
	}

	/** Send a test of one template to an address, using sample data, ignoring enabled. */
	public static function send_test( $event, $to ) {
		$tpl = TSB_Availability::settings()['emails'][ $event ] ?? null;
		if ( ! $tpl || ! is_email( $to ) ) {
			return false;
		}
		self::deliver( $event, $to, $tpl, self::sample_vars( $event ) );
		return true;
	}

	/* ---------------- events ---------------- */

	/** $b: name,email,phone,message,date,time,ref. */
	public static function on_book( $b ) {
		$s    = TSB_Availability::settings();
		$vars = self::vars( $b );
		$ics  = array(
			'id'          => $b['ref'],
			'date'        => $b['date'],
			'time'        => $b['time'],
			'name'        => $b['name'],
			'email'       => $b['email'],
			'summary'     => self::interpolate( str_replace( '{', '{{', str_replace( '}', '}}', $s['ics_summary'] ) ), $vars ),
			'location'    => $s['ics_location'],
			'description' => $b['message'],
		);
		self::send( 'confirm', $b['email'], $vars, $ics );

		$admin_to = ! empty( $s['emails']['admin']['to'] ) ? $s['emails']['admin']['to'] : get_option( 'admin_email' );
		self::send( 'admin', $admin_to, $vars );
	}

	public static function on_move( $b, $old_date, $old_time ) {
		$vars = self::vars( $b, array( 'old_date' => $old_date, 'old_time' => $old_time ) );
		self::send( 'move', $b['email'], $vars );
	}

	public static function on_cancel( $b ) {
		self::send( 'cancel', $b['email'], self::vars( $b ) );
	}

	/* ---------------- reminder cron ---------------- */

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	public static function clear_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Hourly: send reminders for active bookings inside the window, once each. */
	public static function run_reminders() {
		$s = TSB_Availability::settings();
		if ( empty( $s['emails']['reminder']['enabled'] ) ) {
			return;
		}
		$hours = max( 1, (int) $s['reminder_hours'] );

		global $wpdb;
		$t   = TSB_DB::bookings_table();
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$to  = ( clone $now )->modify( "+$hours hours" );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t
			 WHERE status != 'cancelled' AND ( reminded IS NULL OR reminded = 0 )
			   AND CONCAT(slot_date, ' ', slot_time) BETWEEN %s AND %s",
			$now->format( 'Y-m-d H:i:s' ),
			$to->format( 'Y-m-d H:i:s' )
		) );

		foreach ( $rows as $r ) {
			$b = array(
				'name'    => $r->name,
				'email'   => $r->email,
				'phone'   => $r->phone,
				'message' => $r->message,
				'date'    => $r->slot_date,
				'time'    => substr( $r->slot_time, 0, 5 ),
				'ref'     => (int) $r->id,
				'fields'  => $r->meta ? (array) json_decode( $r->meta, true ) : array(),
			);
			self::send( 'reminder', $r->email, self::vars( $b ) );
			$wpdb->update( $t, array( 'reminded' => 1 ), array( 'id' => (int) $r->id ), array( '%d' ), array( '%d' ) );
		}
	}
}
