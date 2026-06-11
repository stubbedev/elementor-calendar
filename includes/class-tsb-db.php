<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_DB {

	const DB_VERSION = '5';

	/**
	 * Idempotent schema guard. Runs on every load but does real work only when
	 * the stored version differs — so it self-heals when activation hooks are
	 * skipped (e.g. `wp plugin activate`, which suppresses them).
	 */
	public static function ensure_schema() {
		if ( get_option( 'tsb_db_ver' ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
	}

	public static function blocked_table() {
		global $wpdb;
		return $wpdb->prefix . 'tsb_blocked';
	}

	public static function bookings_table() {
		global $wpdb;
		return $wpdb->prefix . 'tsb_bookings';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$blocked  = self::blocked_table();
		$bookings = self::bookings_table();

		// Time off. block_time NULL = whole day blocked. Otherwise block_time is the
		// start and block_end the (exclusive) end of a blocked range — slots whose
		// range overlaps [block_time, block_end) are hidden. Legacy single-slot rows
		// (no block_end) are backfilled to a 30-minute range below.
		dbDelta( "CREATE TABLE $blocked (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			block_date DATE NOT NULL,
			block_time TIME NULL,
			block_end TIME NULL,
			reason VARCHAR(190) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY block_date (block_date)
		) $charset;" );

		// Legacy timed blocks predate ranges → give them the old hard-coded 30-min span.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "UPDATE $blocked SET block_end = ADDTIME(block_time, '00:30:00') WHERE block_time IS NOT NULL AND block_end IS NULL" );

		// active = 1 for live bookings, NULL when cancelled. UNIQUE(slot_date, slot_time, active)
		// blocks an exact double-booking of one start time, while letting a cancelled slot be
		// rebooked (MySQL treats NULL as distinct in a unique index). Overlap of *different*
		// start times (variable-length slots across types) is enforced in PHP at book time.
		//
		// type_id  — which session type the booking belongs to ('default' for legacy rows).
		// slot_end — slot_time + the type's length captured at book time, so overlap checks
		//            stay correct even if a type's length changes later.
		// meet_url / gcal_event_id — Google Calendar + Meet linkage (filled when enabled).
		// Dynamic form fields are the single source of truth: every submitted value
		// lives in `meta` (JSON, keyed by field name). There are deliberately no
		// per-field columns — phone is read from meta, and the human-readable body
		// is rebuilt on demand (TSB_Availability::summary_from_meta).
		dbDelta( "CREATE TABLE $bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type_id VARCHAR(64) NULL,
			slot_date DATE NOT NULL,
			slot_time TIME NOT NULL,
			slot_end TIME NULL,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			active TINYINT NULL DEFAULT 1,
			reminded TINYINT NOT NULL DEFAULT 0,
			meet_url TEXT NULL,
			gcal_event_id VARCHAR(190) NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slot (slot_date, slot_time, active),
			KEY type_date (type_id, slot_date),
			KEY date_active (slot_date, active)
		) $charset;" );

		self::backfill( $bookings );

		// Seed the session-types option from the legacy global settings on first upgrade.
		if ( class_exists( 'TSB_Types' ) ) {
			TSB_Types::seed_if_empty();
		}

		update_option( 'tsb_db_ver', self::DB_VERSION );
	}

	/**
	 * Migrate legacy rows. dbDelta only adds columns (and never drops them), so we
	 * (a) assign rows to the default type, (b) backfill slot_end, (c) fold the old
	 * phone/message columns into `meta`, then (d) drop those columns. Idempotent.
	 */
	protected static function backfill( $bookings ) {
		global $wpdb;

		// Existing rows predate session types → they belong to 'default'.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "UPDATE $bookings SET type_id = 'default' WHERE type_id IS NULL OR type_id = ''" );

		// Backfill slot_end for rows that have none, using the default type's length.
		$len = 30;
		if ( class_exists( 'TSB_Types' ) ) {
			$t   = TSB_Types::get( 'default' );
			$len = max( 5, (int) $t['slot_minutes'] );
		}
		$wpdb->query( $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE $bookings SET slot_end = ADDTIME(slot_time, SEC_TO_TIME(%d)) WHERE slot_end IS NULL",
			$len * 60
		) );

		// Fold the legacy phone/message columns into meta, then drop them. Only runs
		// while the columns still exist (i.e. on the first upgrade past v3).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM $bookings", 0 );
		$has_phone   = in_array( 'phone', $cols, true );
		$has_message = in_array( 'message', $cols, true );
		if ( $has_phone || $has_message ) {
			$rows = $wpdb->get_results( "SELECT id, phone, message, meta FROM $bookings" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $rows as $r ) {
				$meta = $r->meta ? (array) json_decode( $r->meta, true ) : array();
				if ( $has_phone && '' !== (string) $r->phone && ! isset( $meta['phone'] ) ) {
					$meta['phone'] = (string) $r->phone;
				}
				// The old message column was an assembled body; preserve it verbatim
				// under 'message' when meta has nothing there yet.
				if ( $has_message && '' !== (string) $r->message && ! isset( $meta['message'] ) ) {
					$meta['message'] = (string) $r->message;
				}
				$wpdb->update( $bookings, array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => (int) $r->id ), array( '%s' ), array( '%d' ) );
			}
			if ( $has_phone ) {
				$wpdb->query( "ALTER TABLE $bookings DROP COLUMN phone" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			if ( $has_message ) {
				$wpdb->query( "ALTER TABLE $bookings DROP COLUMN message" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/** Rows blocked for a date (whole-day = block_time NULL, else a [start,end) range). */
	public static function blocked_for_date( $date ) {
		global $wpdb;
		$t = self::blocked_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT block_time, block_end FROM $t WHERE block_date = %s", $date
		) );
	}

	/** Booked HH:MM:SS times for a date (cancelled excluded). */
	public static function booked_times( $date ) {
		global $wpdb;
		$t = self::bookings_table();
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT slot_time FROM $t WHERE slot_date = %s AND status != 'cancelled'", $date
		) );
	}

	/**
	 * Busy intervals on a date across ALL session types (cancelled excluded),
	 * optionally excluding one booking id (for the reschedule picker). Each row:
	 * { slot_time: 'HH:MM:SS', slot_end: 'HH:MM:SS' }. A row with a NULL slot_end
	 * (legacy/edge) is returned as-is; callers treat it as a zero-length point.
	 *
	 * This is the source of truth for overbooking prevention: variable-length
	 * slots from different types are compared as time ranges, so a long booking
	 * blocks every shorter slot that overlaps it.
	 */
	public static function booked_intervals( $date, $exclude_id = 0 ) {
		global $wpdb;
		$t  = self::bookings_table();
		$id = (int) $exclude_id;
		if ( $id > 0 ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT slot_time, slot_end FROM $t WHERE slot_date = %s AND status != 'cancelled' AND id != %d",
				$date, $id
			) );
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT slot_time, slot_end FROM $t WHERE slot_date = %s AND status != 'cancelled'", $date
		) );
	}
}
