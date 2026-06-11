<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API behind the admin SPA. All routes require manage_options and the
 * standard wp_rest nonce (handled by apiFetch on the client).
 */
class TSB_REST {

	const NS = 'tsb/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function perm() {
		return current_user_can( 'manage_options' );
	}

	public static function routes() {
		$perm = array( __CLASS__, 'perm' );

		register_rest_route( self::NS, '/settings', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_settings' ),  'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_settings' ), 'permission_callback' => $perm ),
		) );

		register_rest_route( self::NS, '/bookings', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_bookings' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/bookings/(?P<id>\d+)', array(
			array( 'methods' => 'POST',   'callback' => array( __CLASS__, 'update_booking' ), 'permission_callback' => $perm ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_booking' ), 'permission_callback' => $perm ),
		) );

		register_rest_route( self::NS, '/types', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_types' ),  'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_types' ), 'permission_callback' => $perm ),
		) );

		register_rest_route( self::NS, '/google', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'google_status' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/google/disconnect', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'google_disconnect' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/availability', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'availability' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/month', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'month' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/test-email', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'test_email' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/blocks', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'list_blocks' ), 'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_block' ),   'permission_callback' => $perm ),
		) );
		register_rest_route( self::NS, '/blocks/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_block' ),
			'permission_callback' => $perm,
		) );
	}

	/* ---------------- settings ---------------- */

	public static function get_settings() {
		return rest_ensure_response( array(
			'settings' => TSB_Availability::settings(),
			'meta'     => array(
				'weekdays'    => TSB_Availability::weekday_names(),
				'countries'   => TSB_Holidays::countries(),
				'fieldTypes'  => array(
					'text'     => __( 'Text', 'tsb' ),
					'email'    => __( 'Email', 'tsb' ),
					'tel'      => __( 'Phone', 'tsb' ),
					'textarea' => __( 'Text area', 'tsb' ),
					'number'   => __( 'Number', 'tsb' ),
				),
				'adminEmail'  => get_option( 'admin_email' ),
				'emailEvents' => array(
					'confirm'  => __( 'Customer confirmation', 'tsb' ),
					'admin'    => __( 'Admin notification', 'tsb' ),
					'move'     => __( 'Booking moved', 'tsb' ),
					'cancel'   => __( 'Booking cancelled', 'tsb' ),
					'reminder' => __( 'Reminder', 'tsb' ),
				),
				'emailTokens' => TSB_Emails::tokens(),
				'tokensByEvent' => array(
					'confirm'  => TSB_Emails::tokens_for( 'confirm' ),
					'admin'    => TSB_Emails::tokens_for( 'admin' ),
					'move'     => TSB_Emails::tokens_for( 'move' ),
					'cancel'   => TSB_Emails::tokens_for( 'cancel' ),
					'reminder' => TSB_Emails::tokens_for( 'reminder' ),
				),
				'tokenLabels'   => TSB_Emails::token_labels(),
				'sampleVars'    => TSB_Emails::sample_vars(),
				'emailDefaults' => TSB_Emails::default_templates(),
			),
		) );
	}

	public static function save_settings( $req ) {
		$in = (array) $req->get_json_params();
		$s  = self::sanitize_settings( $in, TSB_Availability::settings() );
		update_option( 'tsb_settings', $s );
		return rest_ensure_response( array( 'settings' => TSB_Availability::settings() ) );
	}

	/* ---------------- session types ---------------- */

	public static function get_types() {
		return rest_ensure_response( array(
			'types' => array_values( TSB_Types::all() ),
			'meta'  => self::types_meta(),
		) );
	}

	public static function save_types( $req ) {
		$in   = (array) $req->get_json_params();
		$list = ( isset( $in['types'] ) && is_array( $in['types'] ) ) ? $in['types'] : array();
		$stored = TSB_Types::save( $list );
		return rest_ensure_response( array( 'types' => array_values( $stored ) ) );
	}

	/** Editor metadata shared by every per-type form (client email events only). */
	protected static function types_meta() {
		$client_events = array(
			'confirm'  => __( 'Customer confirmation', 'tsb' ),
			'move'     => __( 'Booking moved', 'tsb' ),
			'cancel'   => __( 'Booking cancelled', 'tsb' ),
			'reminder' => __( 'Reminder', 'tsb' ),
		);
		$tokens_by_event = array();
		foreach ( array_keys( $client_events ) as $ev ) {
			$tokens_by_event[ $ev ] = TSB_Emails::tokens_for( $ev );
		}
		return array(
			'weekdays'      => TSB_Availability::weekday_names(),
			'countries'     => TSB_Holidays::countries(),
			'emailEvents'   => $client_events,
			'tokensByEvent' => $tokens_by_event,
			'tokenLabels'   => TSB_Emails::token_labels(),
			'sampleVars'    => TSB_Emails::sample_vars(),
			'emailDefaults' => TSB_Emails::default_templates(),
			'adminEmail'    => get_option( 'admin_email' ),
			'googleReady'   => class_exists( 'TSB_Google' ) ? TSB_Google::is_connected() : false,
		);
	}

	/* ---------------- google ---------------- */

	public static function google_status() {
		return rest_ensure_response( array(
			'configured'  => TSB_Google::configured(),
			'connected'   => TSB_Google::is_connected(),
			'email'       => TSB_Google::account_email(),
			'authUrl'     => TSB_Google::auth_url(),
			'redirectUri' => TSB_Google::redirect_uri(),
		) );
	}

	public static function google_disconnect() {
		TSB_Google::disconnect();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** Validate any subset of settings keys over the current values. */
	protected static function sanitize_settings( $in, $s ) {
		$int  = function ( $k, $def, $min, $max = null ) use ( $in ) {
			$v = isset( $in[ $k ] ) ? (int) $in[ $k ] : $def;
			$v = max( $min, $v );
			return null === $max ? $v : min( $max, $v );
		};
		$bool = function ( $k ) use ( $in ) { return empty( $in[ $k ] ) ? 0 : 1; };

		// Global scheduling rules (booking window, lead time, holidays). Slot geometry
		// and weekly hours are per session type and saved via the /types endpoint.
		if ( array_key_exists( 'days_ahead', $in ) )   { $s['days_ahead']   = $int( 'days_ahead', 30, 1 ); }
		if ( array_key_exists( 'lead_hours', $in ) )   { $s['lead_hours']   = $int( 'lead_hours', 0, 0 ); }
		if ( array_key_exists( 'block_holidays', $in ) ) { $s['block_holidays'] = $bool( 'block_holidays' ); }

		if ( array_key_exists( 'holiday_countries', $in ) ) {
			$valid = array_keys( TSB_Holidays::countries() );
			$cc    = array_values( array_intersect( array_map( 'strtoupper', array_map( 'sanitize_text_field', (array) $in['holiday_countries'] ) ), $valid ) );
			$s['holiday_countries'] = $cc ? $cc : array( 'DK' );
		}

		// emails: per-event templates (admin is trusted → MJML/HTML stored raw).
		if ( array_key_exists( 'emails', $in ) && is_array( $in['emails'] ) ) {
			foreach ( array( 'confirm', 'admin', 'move', 'cancel', 'reminder' ) as $ev ) {
				if ( ! isset( $in['emails'][ $ev ] ) || ! is_array( $in['emails'][ $ev ] ) ) {
					continue;
				}
				$e = $in['emails'][ $ev ];
				$s['emails'][ $ev ]['enabled'] = empty( $e['enabled'] ) ? 0 : 1;
				if ( isset( $e['subject'] ) ) { $s['emails'][ $ev ]['subject'] = sanitize_text_field( $e['subject'] ); }
				if ( isset( $e['mjml'] ) )    { $s['emails'][ $ev ]['mjml']    = (string) $e['mjml']; }
				if ( isset( $e['html'] ) )    { $s['emails'][ $ev ]['html']    = (string) $e['html']; }
				if ( 'admin' === $ev && isset( $e['to'] ) ) { $s['emails'][ $ev ]['to'] = sanitize_email( $e['to'] ); }
			}
		}

		// google calendar / meet
		foreach ( array( 'google_client_id', 'google_client_secret' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) { $s[ $k ] = sanitize_text_field( $in[ $k ] ); }
		}
		if ( array_key_exists( 'google_calendar_id', $in ) ) {
			$cal = sanitize_text_field( $in['google_calendar_id'] );
			$s['google_calendar_id'] = '' !== $cal ? $cal : 'primary';
		}

		// form fields (ordered, user-defined)
		if ( array_key_exists( 'fields', $in ) ) {
			$s['fields'] = TSB_Availability::normalize_fields( $in['fields'] );
		}

		return $s;
	}

	/* ---------------- bookings ---------------- */

	public static function list_bookings( $req ) {
		global $wpdb;
		$t       = TSB_DB::bookings_table();
		$search  = trim( (string) $req->get_param( 'search' ) );
		$scope   = $req->get_param( 'scope' );
		$per     = min( 100, max( 1, (int) ( $req->get_param( 'per_page' ) ?: 20 ) ) );
		$page    = max( 1, (int) ( $req->get_param( 'page' ) ?: 1 ) );
		$allowed = array( 'slot_date', 'slot_time', 'type_id', 'name', 'email', 'phone', 'status', 'created_at' );
		$orderby = in_array( $req->get_param( 'orderby' ), $allowed, true ) ? $req->get_param( 'orderby' ) : 'slot_date';
		$order   = strtolower( (string) $req->get_param( 'order' ) ) === 'asc' ? 'ASC' : 'DESC';
		// Phone has no column — it lives in the meta JSON, so sort by the extracted key.
		$order_expr = 'phone' === $orderby ? "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.phone'))" : $orderby;

		$where = '1=1';
		$args  = array();
		if ( '' !== $search ) {
			// meta is the JSON field store, so a LIKE over it catches phone and any
			// custom field value now that there are no per-field columns.
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (name LIKE %s OR email LIKE %s OR meta LIKE %s)';
			$args   = array( $like, $like, $like );
		}
		$today = current_time( 'Y-m-d' );
		if ( 'upcoming' === $scope ) {
			$where .= $wpdb->prepare( ' AND slot_date >= %s', $today );
		} elseif ( 'past' === $scope ) {
			$where .= $wpdb->prepare( ' AND slot_date < %s', $today );
		}

		$count = "SELECT COUNT(*) FROM $t WHERE $where";
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count, $args ) ) : $wpdb->get_var( $count ) );

		$sql   = "SELECT * FROM $t WHERE $where ORDER BY $order_expr $order, slot_time $order LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) );
		$types = class_exists( 'TSB_Types' ) ? TSB_Types::all() : array();
		foreach ( $items as $it ) {
			$it->slot_time  = substr( $it->slot_time, 0, 5 );
			$it->id         = (int) $it->id;
			$it->type_id    = $it->type_id ?: 'default';
			$it->type_label = isset( $types[ $it->type_id ] ) ? $types[ $it->type_id ]['label'] : $it->type_id;
			// Derive the display fields the UI expects from meta (no stored columns).
			$meta          = $it->meta ? (array) json_decode( $it->meta, true ) : array();
			$it->phone     = TSB_Availability::phone_from_meta( $meta );
			$it->message   = TSB_Availability::summary_from_meta( $meta );
		}

		return rest_ensure_response( array( 'items' => $items, 'total' => $total ) );
	}

	public static function update_booking( $req ) {
		global $wpdb;
		$id  = (int) $req['id'];
		$t   = TSB_DB::bookings_table();
		$op  = sanitize_key( (string) $req->get_param( 'op' ) );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
		if ( ! $row ) {
			return new WP_Error( 'tsb_notfound', __( 'Booking not found.', 'tsb' ), array( 'status' => 404 ) );
		}
		$as_email = function ( $r, $date = null, $time = null ) {
			$meta = $r->meta ? (array) json_decode( $r->meta, true ) : array();
			return array(
				'name'     => $r->name,
				'email'    => $r->email,
				'phone'    => TSB_Availability::phone_from_meta( $meta ),
				'message'  => TSB_Availability::summary_from_meta( $meta ),
				'date'     => $date ?? $r->slot_date,
				'time'     => $time ?? substr( $r->slot_time, 0, 5 ),
				'ref'      => (int) $r->id,
				'type'     => $r->type_id ?: 'default',
				'meet_url' => $r->meet_url ?? '',
				'fields'   => $meta,
			);
		};

		if ( 'cancel' === $op ) {
			$wpdb->update( $t, array( 'status' => 'cancelled', 'active' => null ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
			if ( ! empty( $row->gcal_event_id ) && class_exists( 'TSB_Google' ) ) {
				TSB_Google::delete_event( $row->gcal_event_id );
			}
			TSB_Emails::on_cancel( $as_email( $row ) );
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( 'restore' === $op ) {
			$wpdb->update( $t, array( 'status' => 'confirmed', 'active' => 1 ), array( 'id' => $id ), array( '%s', '%d' ), array( '%d' ) );
			return rest_ensure_response( array( 'ok' => ( false !== $wpdb->rows_affected ) ) );
		}
		if ( 'move' === $op ) {
			$date = sanitize_text_field( (string) $req->get_param( 'date' ) );
			$time = sanitize_text_field( (string) $req->get_param( 'time' ) );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
				return new WP_Error( 'tsb_badtime', __( 'Invalid date/time.', 'tsb' ), array( 'status' => 400 ) );
			}
			// Overlap-aware: block the move if the new range collides with any active
			// booking of any type (variable-length slots compared as ranges).
			$type = $row->type_id ?: 'default';
			$len  = max( 5, (int) TSB_Types::get( $type )['slot_minutes'] );
			if ( ! TSB_Availability::range_free( $date, $time, $len, $id ) ) {
				return new WP_Error( 'tsb_taken', __( 'That time is already taken. Choose another.', 'tsb' ), array( 'status' => 409 ) );
			}
			$parts    = array_map( 'intval', explode( ':', $time ) );
			$end_min  = $parts[0] * 60 + ( $parts[1] ?? 0 ) + $len;
			$slot_end = sprintf( '%02d:%02d:00', intdiv( $end_min, 60 ), $end_min % 60 );
			$res = $wpdb->update( $t, array( 'slot_date' => $date, 'slot_time' => $time . ':00', 'slot_end' => $slot_end, 'reminded' => 0 ), array( 'id' => $id ), array( '%s', '%s', '%s', '%d' ), array( '%d' ) );
			if ( false === $res ) {
				return new WP_Error( 'tsb_taken', __( 'That time is already taken. Choose another.', 'tsb' ), array( 'status' => 409 ) );
			}
			if ( ! empty( $row->gcal_event_id ) && class_exists( 'TSB_Google' ) ) {
				TSB_Google::update_event( $row->gcal_event_id, $date, $time, $len );
			}
			TSB_Emails::on_move( $as_email( $row, $date, $time ), $row->slot_date, substr( $row->slot_time, 0, 5 ) );
			return rest_ensure_response( array( 'ok' => true ) );
		}
		return new WP_Error( 'tsb_badop', 'Unknown operation.', array( 'status' => 400 ) );
	}

	public static function delete_booking( $req ) {
		global $wpdb;
		$wpdb->delete( TSB_DB::bookings_table(), array( 'id' => (int) $req['id'] ), array( '%d' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function availability( $req ) {
		$date    = sanitize_text_field( (string) $req->get_param( 'date' ) );
		$exclude = (int) $req->get_param( 'exclude' );
		$type    = sanitize_key( (string) $req->get_param( 'type' ) ) ?: 'default';
		return rest_ensure_response( array( 'slots' => TSB_Availability::day_grid( $date, $exclude, $type ) ) );
	}

	public static function test_email( $req ) {
		$event = sanitize_key( (string) $req->get_param( 'event' ) );
		$to    = sanitize_email( (string) $req->get_param( 'to' ) );
		$type  = sanitize_key( (string) $req->get_param( 'type' ) ) ?: 'default';
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'tsb_bademail', __( 'Enter a valid email.', 'tsb' ), array( 'status' => 400 ) );
		}
		if ( ! TSB_Emails::send_test( $event, $to, $type ) ) {
			return new WP_Error( 'tsb_failed', __( 'Could not send the test email.', 'tsb' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** Per-day status for one month, for the calendar overview. */
	public static function month( $req ) {
		$y    = max( 1970, (int) $req->get_param( 'year' ) );
		$mo   = min( 12, max( 1, (int) $req->get_param( 'month' ) ) );
		$type = sanitize_key( (string) $req->get_param( 'type' ) ) ?: 'default';
		$dim  = (int) gmdate( 't', gmmktime( 0, 0, 0, $mo, 1, $y ) );
		$out  = array();

		for ( $d = 1; $d <= $dim; $d++ ) {
			$date  = sprintf( '%04d-%02d-%02d', $y, $mo, $d );
			$whole = false;
			foreach ( TSB_DB::blocked_for_date( $date ) as $b ) {
				if ( null === $b->block_time ) {
					$whole = true;
					break;
				}
			}
			if ( $whole ) {
				$out[ $date ] = array( 'status' => 'wholeday' );
				continue;
			}
			$grid = TSB_Availability::day_grid( $date, 0, $type );
			if ( empty( $grid ) ) {
				$out[ $date ] = array( 'status' => 'closed' );
				continue;
			}
			$free = 0;
			$used = 0;
			foreach ( $grid as $g ) {
				if ( $g['available'] ) {
					$free++;
				} else {
					$used++;
				}
			}
			$status = ( 0 === $free ) ? 'full' : ( $used > 0 ? 'partial' : 'free' );
			$out[ $date ] = array( 'status' => $status, 'free' => $free, 'open' => count( $grid ) );
		}
		return rest_ensure_response( array( 'days' => $out ) );
	}

	/* ---------------- blocks ---------------- */

	public static function list_blocks() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . TSB_DB::blocked_table() . ' ORDER BY block_date DESC, block_time' );
		foreach ( $rows as $r ) {
			$r->id         = (int) $r->id;
			$r->block_time = $r->block_time ? substr( $r->block_time, 0, 5 ) : '';
			$r->block_end  = $r->block_end ? substr( $r->block_end, 0, 5 ) : '';
		}
		return rest_ensure_response( array( 'items' => $rows ) );
	}

	public static function add_block( $req ) {
		global $wpdb;
		$date = sanitize_text_field( (string) $req->get_param( 'block_date' ) );
		$time = sanitize_text_field( (string) $req->get_param( 'block_time' ) );
		$end  = sanitize_text_field( (string) $req->get_param( 'block_end' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'tsb_baddate', __( 'Invalid date.', 'tsb' ), array( 'status' => 400 ) );
		}
		// No start time → whole day. Otherwise require start < end (a [start,end) range).
		if ( '' === $time ) {
			$start_sql = null;
			$end_sql   = null;
		} else {
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) || ! preg_match( '/^\d{2}:\d{2}$/', $end ) ) {
				return new WP_Error( 'tsb_badtime', __( 'Invalid time range.', 'tsb' ), array( 'status' => 400 ) );
			}
			$to_min = function ( $hm ) {
				$p = explode( ':', $hm );
				return (int) $p[0] * 60 + (int) ( $p[1] ?? 0 );
			};
			if ( $to_min( $end ) <= $to_min( $time ) ) {
				return new WP_Error( 'tsb_badtime', __( 'End time must be after the start time.', 'tsb' ), array( 'status' => 400 ) );
			}
			$start_sql = $time . ':00';
			$end_sql   = $end . ':00';
		}
		$wpdb->insert(
			TSB_DB::blocked_table(),
			array(
				'block_date' => $date,
				'block_time' => $start_sql,
				'block_end'  => $end_sql,
				'reason'     => sanitize_text_field( (string) $req->get_param( 'reason' ) ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
		return rest_ensure_response( array( 'ok' => true, 'id' => (int) $wpdb->insert_id ) );
	}

	public static function delete_block( $req ) {
		global $wpdb;
		$wpdb->delete( TSB_DB::blocked_table(), array( 'id' => (int) $req['id'] ), array( '%d' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
