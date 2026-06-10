<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Calendar + Meet integration.
 *
 * OAuth2 (web-application client) against a single Google account. The admin
 * pastes a client id/secret (stored in tsb_settings) and connects once; we keep
 * the refresh token in the tsb_google_token option and mint short-lived access
 * tokens on demand. No SDK — plain wp_remote_* against the public endpoints.
 *
 * When a session type has meet_enabled and the account is connected, each
 * booking creates a Calendar event with an auto-generated Meet link; moves
 * patch the event time and cancellations delete it.
 */
class TSB_Google {

	const TOKEN_OPTION = 'tsb_google_token';
	const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	const API_BASE     = 'https://www.googleapis.com/calendar/v3';
	const SCOPE        = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email';

	/* ---------------- config ---------------- */

	protected static function creds() {
		$s = TSB_Availability::settings();
		return array(
			'id'     => (string) ( $s['google_client_id'] ?? '' ),
			'secret' => (string) ( $s['google_client_secret'] ?? '' ),
			'cal'    => (string) ( $s['google_calendar_id'] ?? 'primary' ),
		);
	}

	public static function configured() {
		$c = self::creds();
		return '' !== $c['id'] && '' !== $c['secret'];
	}

	public static function is_connected() {
		$tok = get_option( self::TOKEN_OPTION );
		return self::configured() && is_array( $tok ) && ! empty( $tok['refresh_token'] );
	}

	public static function account_email() {
		$tok = get_option( self::TOKEN_OPTION );
		return is_array( $tok ) && ! empty( $tok['email'] ) ? $tok['email'] : '';
	}

	/** The fixed redirect URI the admin must whitelist in the Google Cloud console. */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=tsb_google_callback' );
	}

	/* ---------------- OAuth flow ---------------- */

	/** Consent-screen URL; state carries a nonce we re-check on callback. */
	public static function auth_url() {
		if ( ! self::configured() ) {
			return '';
		}
		$c = self::creds();
		return self::AUTH_URL . '?' . http_build_query( array(
			'client_id'     => $c['id'],
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => self::SCOPE,
			'access_type'   => 'offline',
			'include_granted_scopes' => 'true',
			'prompt'        => 'consent', // force a refresh_token every time
			'state'         => wp_create_nonce( 'tsb_google_oauth' ),
		) );
	}

	/** admin-post handler: exchange the code for tokens, store the refresh token. */
	public static function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'tsb' ) );
		}
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'tsb_google_oauth' ) ) {
			self::redirect_back( 'state' );
		}
		if ( isset( $_GET['error'] ) ) {
			self::redirect_back( 'denied' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			self::redirect_back( 'nocode' );
		}

		$c    = self::creds();
		$resp = wp_remote_post( self::TOKEN_URL, array(
			'timeout' => 15,
			'body'    => array(
				'code'          => $code,
				'client_id'     => $c['id'],
				'client_secret' => $c['secret'],
				'redirect_uri'  => self::redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
		) );
		$body = self::json( $resp );
		if ( empty( $body['access_token'] ) ) {
			self::redirect_back( 'exchange' );
		}

		$token = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ) - 60,
			'email'         => self::fetch_email( $body['access_token'] ),
		);
		// Google only returns refresh_token on the first consent — keep the old one
		// if a re-auth omits it.
		if ( '' === $token['refresh_token'] ) {
			$prev = get_option( self::TOKEN_OPTION );
			if ( is_array( $prev ) && ! empty( $prev['refresh_token'] ) ) {
				$token['refresh_token'] = $prev['refresh_token'];
			}
		}
		update_option( self::TOKEN_OPTION, $token );
		self::redirect_back( '' );
	}

	public static function disconnect() {
		$tok = get_option( self::TOKEN_OPTION );
		if ( is_array( $tok ) && ! empty( $tok['refresh_token'] ) ) {
			// Best-effort revoke; ignore failures.
			wp_remote_post( 'https://oauth2.googleapis.com/revoke', array(
				'timeout' => 8,
				'body'    => array( 'token' => $tok['refresh_token'] ),
			) );
		}
		delete_option( self::TOKEN_OPTION );
	}

	/** A valid access token, refreshing via the stored refresh token if expired. */
	protected static function access_token() {
		$tok = get_option( self::TOKEN_OPTION );
		if ( ! is_array( $tok ) || empty( $tok['refresh_token'] ) ) {
			return '';
		}
		if ( ! empty( $tok['access_token'] ) && ! empty( $tok['expires_at'] ) && time() < (int) $tok['expires_at'] ) {
			return $tok['access_token'];
		}
		$c    = self::creds();
		$resp = wp_remote_post( self::TOKEN_URL, array(
			'timeout' => 15,
			'body'    => array(
				'client_id'     => $c['id'],
				'client_secret' => $c['secret'],
				'refresh_token' => $tok['refresh_token'],
				'grant_type'    => 'refresh_token',
			),
		) );
		$body = self::json( $resp );
		if ( empty( $body['access_token'] ) ) {
			return '';
		}
		$tok['access_token'] = $body['access_token'];
		$tok['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 ) - 60;
		update_option( self::TOKEN_OPTION, $tok );
		return $tok['access_token'];
	}

	protected static function fetch_email( $access ) {
		$resp = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
			'timeout' => 10,
			'headers' => array( 'Authorization' => 'Bearer ' . $access ),
		) );
		$body = self::json( $resp );
		return $body['email'] ?? '';
	}

	/* ---------------- events ---------------- */

	/**
	 * Create a Calendar event with a Meet link for a booking.
	 *
	 * @param array $b  keys: ref, date (Y-m-d), time (H:i), name, email, summary,
	 *                  location, description.
	 * @param int   $minutes slot length.
	 * @return array{event_id:string,meet_url:string}|null Null on any failure.
	 */
	public static function create_event( $b, $minutes ) {
		$access = self::access_token();
		if ( '' === $access ) {
			return null;
		}
		$c       = self::creds();
		$tz      = wp_timezone();
		$tz_name = $tz->getName();
		$start   = new DateTime( $b['date'] . ' ' . $b['time'], $tz );
		$end     = ( clone $start )->modify( '+' . max( 5, (int) $minutes ) . ' minutes' );

		$payload = array(
			'summary'        => $b['summary'] ?? '',
			'description'    => $b['description'] ?? '',
			'location'       => $b['location'] ?? '',
			'start'          => array( 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_name ),
			'end'            => array( 'dateTime' => $end->format( DateTime::RFC3339 ), 'timeZone' => $tz_name ),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => 'tsb-' . (int) ( $b['ref'] ?? 0 ) . '-' . substr( md5( (string) ( $b['ref'] ?? '' ) . $b['date'] . $b['time'] ), 0, 8 ),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			),
		);
		if ( ! empty( $b['email'] ) ) {
			$payload['attendees'] = array( array( 'email' => $b['email'], 'displayName' => $b['name'] ?? '' ) );
		}

		$url  = self::API_BASE . '/calendars/' . rawurlencode( $c['cal'] ) . '/events?conferenceDataVersion=1';
		$resp = wp_remote_post( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		) );
		$body = self::json( $resp );
		if ( empty( $body['id'] ) ) {
			return null;
		}
		return array(
			'event_id' => (string) $body['id'],
			'meet_url' => self::extract_meet( $body ),
		);
	}

	/** Patch an existing event's time (used on reschedule). */
	public static function update_event( $event_id, $date, $time, $minutes ) {
		$access = self::access_token();
		if ( '' === $access || '' === (string) $event_id ) {
			return false;
		}
		$c       = self::creds();
		$tz      = wp_timezone();
		$tz_name = $tz->getName();
		$start   = new DateTime( $date . ' ' . $time, $tz );
		$end     = ( clone $start )->modify( '+' . max( 5, (int) $minutes ) . ' minutes' );

		$url  = self::API_BASE . '/calendars/' . rawurlencode( $c['cal'] ) . '/events/' . rawurlencode( $event_id );
		$resp = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'start' => array( 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_name ),
				'end'   => array( 'dateTime' => $end->format( DateTime::RFC3339 ), 'timeZone' => $tz_name ),
			) ),
		) );
		$body = self::json( $resp );
		return ! empty( $body['id'] );
	}

	public static function delete_event( $event_id ) {
		$access = self::access_token();
		if ( '' === $access || '' === (string) $event_id ) {
			return false;
		}
		$c   = self::creds();
		$url = self::API_BASE . '/calendars/' . rawurlencode( $c['cal'] ) . '/events/' . rawurlencode( $event_id );
		$resp = wp_remote_request( $url, array(
			'method'  => 'DELETE',
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $access ),
		) );
		$code = wp_remote_retrieve_response_code( $resp );
		return $code >= 200 && $code < 300;
	}

	/* ---------------- helpers ---------------- */

	protected static function extract_meet( $event ) {
		if ( ! empty( $event['hangoutLink'] ) ) {
			return (string) $event['hangoutLink'];
		}
		if ( ! empty( $event['conferenceData']['entryPoints'] ) ) {
			foreach ( $event['conferenceData']['entryPoints'] as $ep ) {
				if ( ( $ep['entryPointType'] ?? '' ) === 'video' && ! empty( $ep['uri'] ) ) {
					return (string) $ep['uri'];
				}
			}
		}
		return '';
	}

	protected static function json( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body ) ? $body : array();
	}

	protected static function redirect_back( $error ) {
		$url = add_query_arg(
			$error ? array( 'tsb_google' => $error ) : array( 'tsb_google' => 'connected' ),
			admin_url( 'admin.php?page=tsb_settings' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
