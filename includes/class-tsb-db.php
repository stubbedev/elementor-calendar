<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_DB {

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

		// block_time NULL = whole day blocked.
		dbDelta( "CREATE TABLE $blocked (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			block_date DATE NOT NULL,
			block_time TIME NULL,
			reason VARCHAR(190) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY block_date (block_date)
		) $charset;" );

		// active = 1 for live bookings, NULL when cancelled. UNIQUE(slot_date, slot_time, active)
		// blocks double-booking of live slots, while letting a cancelled slot be rebooked
		// (MySQL treats NULL as distinct in a unique index).
		dbDelta( "CREATE TABLE $bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slot_date DATE NOT NULL,
			slot_time TIME NOT NULL,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			phone VARCHAR(60) NULL,
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			active TINYINT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slot (slot_date, slot_time, active)
		) $charset;" );
	}

	/** Rows blocked for a date (whole-day = block_time NULL). */
	public static function blocked_for_date( $date ) {
		global $wpdb;
		$t = self::blocked_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT block_time FROM $t WHERE block_date = %s", $date
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
}
