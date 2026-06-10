<?php
/**
 * Runs on plugin delete (not deactivate). Drops tables + options.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'tsb_bookings',
	$wpdb->prefix . 'tsb_blocked',
);
foreach ( $tables as $t ) {
	// Table names cannot be parameterized; built from $wpdb->prefix only.
	$wpdb->query( "DROP TABLE IF EXISTS `$t`" );
}

delete_option( 'tsb_settings' );
delete_option( 'tsb_types' );        // session types
delete_option( 'tsb_google_token' ); // Google OAuth refresh/access token
delete_option( 'tsb_db_ver' );       // legacy, pre-squash installs
