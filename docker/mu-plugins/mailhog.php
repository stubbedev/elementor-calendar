<?php
/**
 * Dev-only must-use plugin: route all wp_mail() through MailHog.
 * Not part of the Timeslot Booking plugin — lives here so the docker stack can
 * catch outgoing mail (incl. the .ics attachment) at http://localhost:8025.
 */
add_action( 'phpmailer_init', function ( $phpmailer ) {
	$phpmailer->isSMTP();
	$phpmailer->Host       = 'mailhog';
	$phpmailer->Port       = 1025;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
	$phpmailer->SMTPAutoTLS = false;
} );

// PHPMailer rejects the default `wordpress@localhost` (no TLD). Give it a valid From.
add_filter( 'wp_mail_from', function ( $from ) {
	return ( $from === 'wordpress@localhost' || ! is_email( $from ) ) ? 'wordpress@example.com' : $from;
} );
