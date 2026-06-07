<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_tsb_block_add', array( $this, 'block_add' ) );
		add_action( 'admin_post_tsb_block_del', array( $this, 'block_del' ) );
		add_action( 'admin_post_tsb_booking_cancel', array( $this, 'booking_cancel' ) );
		add_action( 'admin_post_tsb_booking_delete', array( $this, 'booking_delete' ) );
		add_action( 'admin_post_tsb_booking_move', array( $this, 'booking_move' ) );
		add_action( 'admin_post_tsb_export_csv', array( $this, 'export_csv' ) );
	}

	public function menu() {
		add_menu_page( 'Booking', 'Booking', 'manage_options', 'tsb', array( $this, 'page' ), 'dashicons-calendar-alt', 26 );
	}

	public function register_settings() {
		register_setting( 'tsb_group', 'tsb_settings', array( $this, 'sanitize' ) );
	}

	/** Merge only the submitted section over current settings so per-tab forms don't wipe each other. */
	public function sanitize( $in ) {
		$s       = TSB_Availability::settings();
		$section = isset( $in['_section'] ) ? $in['_section'] : '';

		if ( 'availability' === $section ) {
			$s['slot_minutes']   = max( 5, (int) ( $in['slot_minutes'] ?? 30 ) );
			$s['days_ahead']     = max( 1, (int) ( $in['days_ahead'] ?? 30 ) );
			$s['lead_hours']     = max( 0, (int) ( $in['lead_hours'] ?? 0 ) );
			$s['block_holidays'] = empty( $in['block_holidays'] ) ? 0 : 1;
			$week = array();
			for ( $d = 1; $d <= 7; $d++ ) {
				$wd          = isset( $in['week'][ $d ] ) ? $in['week'][ $d ] : array();
				$week[ $d ]  = array(
					'open'  => empty( $wd['open'] ) ? 0 : 1,
					'start' => max( 0, min( 23, (int) ( $wd['start'] ?? 9 ) ) ),
					'end'   => max( 1, min( 24, (int) ( $wd['end'] ?? 17 ) ) ),
				);
			}
			$s['week'] = $week;

		} elseif ( 'emails' === $section ) {
			$s['admin_notify']     = empty( $in['admin_notify'] ) ? 0 : 1;
			$s['admin_to']         = sanitize_email( $in['admin_to'] ?? '' );
			$s['admin_subject']    = sanitize_text_field( $in['admin_subject'] ?? '' );
			$s['admin_body']       = sanitize_textarea_field( $in['admin_body'] ?? '' );
			$s['customer_confirm'] = empty( $in['customer_confirm'] ) ? 0 : 1;
			$s['customer_subject'] = sanitize_text_field( $in['customer_subject'] ?? '' );
			$s['customer_body']    = sanitize_textarea_field( $in['customer_body'] ?? '' );
			$s['from_name']        = sanitize_text_field( $in['from_name'] ?? '' );
			$s['from_email']       = sanitize_email( $in['from_email'] ?? '' );
			$s['ics_attach']       = empty( $in['ics_attach'] ) ? 0 : 1;
			$s['ics_summary']      = sanitize_text_field( $in['ics_summary'] ?? '' );
			$s['ics_location']     = sanitize_text_field( $in['ics_location'] ?? '' );

		} elseif ( 'spam' === $section ) {
			$mode                = $in['captcha_mode'] ?? 'honeypot';
			$s['captcha_mode']   = in_array( $mode, array( 'none', 'honeypot', 'recaptcha', 'recaptcha_v3', 'hcaptcha' ), true ) ? $mode : 'honeypot';
			$s['captcha_site']   = sanitize_text_field( $in['captcha_site'] ?? '' );
			$s['captcha_secret'] = sanitize_text_field( $in['captcha_secret'] ?? '' );
			$s['captcha_min_score'] = max( 0, min( 1, (float) ( $in['captcha_min_score'] ?? 0.5 ) ) );
		}

		return $s;
	}

	/* ---------- block management ---------- */

	public function block_add() {
		check_admin_referer( 'tsb_block' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$date = sanitize_text_field( wp_unslash( $_POST['block_date'] ?? '' ) );
		$time = sanitize_text_field( wp_unslash( $_POST['block_time'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$wpdb->insert(
				TSB_DB::blocked_table(),
				array(
					'block_date' => $date,
					'block_time' => $time ? $time . ':00' : null,
					'reason'     => sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ),
				),
				array( '%s', '%s', '%s' )
			);
		}
		$this->back( 'blocks' );
	}

	public function block_del() {
		check_admin_referer( 'tsb_block_del' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$wpdb->delete( TSB_DB::blocked_table(), array( 'id' => (int) ( $_GET['id'] ?? 0 ) ), array( '%d' ) );
		$this->back( 'blocks' );
	}

	/* ---------- booking management ---------- */

	public function booking_cancel() {
		check_admin_referer( 'tsb_booking_cancel' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		// status=cancelled + active=NULL frees the slot and lets it be rebooked.
		$wpdb->update(
			TSB_DB::bookings_table(),
			array( 'status' => 'cancelled', 'active' => null ),
			array( 'id' => (int) ( $_GET['id'] ?? 0 ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$this->back( 'bookings' );
	}

	public function booking_move() {
		check_admin_referer( 'tsb_booking_move' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$id   = (int) ( $_POST['id'] ?? 0 );
		$date = sanitize_text_field( wp_unslash( $_POST['new_date'] ?? '' ) );
		$time = sanitize_text_field( wp_unslash( $_POST['new_time'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$this->back( 'bookings', 'badtime' );
		}
		global $wpdb;
		// false => UNIQUE(slot_date,slot_time,active) collision: target already booked.
		$res = $wpdb->update(
			TSB_DB::bookings_table(),
			array( 'slot_date' => $date, 'slot_time' => $time . ':00' ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$this->back( 'bookings', ( false === $res ) ? 'taken' : 'moved' );
	}

	public function booking_delete() {
		check_admin_referer( 'tsb_booking_delete' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$wpdb->delete( TSB_DB::bookings_table(), array( 'id' => (int) ( $_GET['id'] ?? 0 ) ), array( '%d' ) );
		$this->back( 'bookings' );
	}

	public function export_csv() {
		check_admin_referer( 'tsb_export_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT slot_date, slot_time, name, email, phone, message, status, created_at FROM ' . TSB_DB::bookings_table() . ' ORDER BY slot_date DESC, slot_time DESC', ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bookinger-' . current_time( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads æøå
		fputcsv( $out, array( 'Dato', 'Tid', 'Navn', 'E-mail', 'Telefon', 'Besked', 'Status', 'Oprettet' ) );
		foreach ( $rows as $r ) {
			$r['slot_time'] = substr( $r['slot_time'], 0, 5 );
			fputcsv( $out, $r );
		}
		fclose( $out );
		exit;
	}

	protected function back( $tab, $msg = '' ) {
		$args = array( 'page' => 'tsb', 'tab' => $tab );
		if ( $msg ) {
			$args['tsb_msg'] = $msg;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- page ---------- */

	public function page() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'availability';
		$tabs = array(
			'availability' => 'Tilgængelighed',
			'emails'       => 'E-mails',
			'spam'         => 'Spam-beskyttelse',
			'blocks'       => 'Blokeringer',
			'bookings'     => 'Bookinger',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'availability';
		}
		$notices = array(
			'moved'   => array( 'updated', 'Booking flyttet.' ),
			'taken'   => array( 'error', 'Tidspunktet er allerede optaget. Vælg et andet.' ),
			'badtime' => array( 'error', 'Ugyldig dato/tid.' ),
		);
		$msg = isset( $_GET['tsb_msg'] ) ? sanitize_key( $_GET['tsb_msg'] ) : '';
		?>
		<div class="wrap">
			<h1>Booking</h1>
			<?php if ( isset( $notices[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notices[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $msg ][1] ); ?></p></div>
			<?php endif; ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=tsb&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>
			<?php
			$method = 'tab_' . $tab;
			$this->$method();
			?>
		</div>
		<?php
	}

	protected function tab_availability() {
		$s    = TSB_Availability::settings();
		$days = TSB_Availability::weekday_names();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'tsb_group' ); ?>
			<input type="hidden" name="tsb_settings[_section]" value="availability">

			<h2>Åbningstider pr. ugedag</h2>
			<table class="widefat striped" style="max-width:620px">
				<thead><tr><th>Dag</th><th>Åben</th><th>Fra (24t)</th><th>Til (24t)</th></tr></thead>
				<tbody>
				<?php foreach ( $days as $d => $name ) : $wd = $s['week'][ $d ]; ?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td><input type="checkbox" name="tsb_settings[week][<?php echo $d; ?>][open]" value="1" <?php checked( $wd['open'], 1 ); ?>></td>
						<td><input type="number" min="0" max="23" name="tsb_settings[week][<?php echo $d; ?>][start]" value="<?php echo esc_attr( $wd['start'] ); ?>"></td>
						<td><input type="number" min="1" max="24" name="tsb_settings[week][<?php echo $d; ?>][end]" value="<?php echo esc_attr( $wd['end'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<table class="form-table">
				<tr><th>Slot længde (min)</th><td><input type="number" min="5" name="tsb_settings[slot_minutes]" value="<?php echo esc_attr( $s['slot_minutes'] ); ?>"></td></tr>
				<tr><th>Dage frem</th><td><input type="number" min="1" name="tsb_settings[days_ahead]" value="<?php echo esc_attr( $s['days_ahead'] ); ?>"></td></tr>
				<tr><th>Mindste varsel (timer)</th><td><input type="number" min="0" name="tsb_settings[lead_hours]" value="<?php echo esc_attr( $s['lead_hours'] ); ?>"> <span class="description">0 = kan bookes lige nu</span></td></tr>
				<tr><th>Bloker danske helligdage</th><td><input type="checkbox" name="tsb_settings[block_holidays]" value="1" <?php checked( $s['block_holidays'], 1 ); ?>></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected function tab_emails() {
		$s = TSB_Availability::settings();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'tsb_group' ); ?>
			<input type="hidden" name="tsb_settings[_section]" value="emails">
			<p class="description">Pladsholdere: <code>{name} {email} {phone} {message} {date} {time}</code></p>

			<h2>Besked til admin</h2>
			<table class="form-table">
				<tr><th>Send admin-notifikation</th><td><input type="checkbox" name="tsb_settings[admin_notify]" value="1" <?php checked( $s['admin_notify'], 1 ); ?>></td></tr>
				<tr><th>Modtager</th><td><input type="email" class="regular-text" name="tsb_settings[admin_to]" value="<?php echo esc_attr( $s['admin_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?> (standard)"></td></tr>
				<tr><th>Emne</th><td><input type="text" class="large-text" name="tsb_settings[admin_subject]" value="<?php echo esc_attr( $s['admin_subject'] ); ?>"></td></tr>
				<tr><th>Tekst</th><td><textarea class="large-text" rows="6" name="tsb_settings[admin_body]"><?php echo esc_textarea( $s['admin_body'] ); ?></textarea></td></tr>
			</table>

			<h2>Bekræftelse til kunde</h2>
			<table class="form-table">
				<tr><th>Send bekræftelse</th><td><input type="checkbox" name="tsb_settings[customer_confirm]" value="1" <?php checked( $s['customer_confirm'], 1 ); ?>></td></tr>
				<tr><th>Emne</th><td><input type="text" class="large-text" name="tsb_settings[customer_subject]" value="<?php echo esc_attr( $s['customer_subject'] ); ?>"></td></tr>
				<tr><th>Tekst</th><td><textarea class="large-text" rows="6" name="tsb_settings[customer_body]"><?php echo esc_textarea( $s['customer_body'] ); ?></textarea></td></tr>
			</table>

			<h2>Afsender</h2>
			<table class="form-table">
				<tr><th>Afsendernavn</th><td><input type="text" class="regular-text" name="tsb_settings[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" placeholder="WordPress (standard)"></td></tr>
				<tr><th>Afsender e-mail</th><td><input type="email" class="regular-text" name="tsb_settings[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="standard fra WordPress"><p class="description">Brug en adresse på dit eget domæne for bedre leveringssikkerhed (typisk sammen med en SMTP-plugin).</p></td></tr>
			</table>

			<h2>Kalender-invitation (.ics)</h2>
			<table class="form-table">
				<tr><th>Vedhæft .ics til kundemail</th><td><input type="checkbox" name="tsb_settings[ics_attach]" value="1" <?php checked( $s['ics_attach'], 1 ); ?>></td></tr>
				<tr><th>Titel</th><td><input type="text" class="large-text" name="tsb_settings[ics_summary]" value="<?php echo esc_attr( $s['ics_summary'] ); ?>"><p class="description">Pladsholdere som i mails, fx <code>Booking: {name}</code>.</p></td></tr>
				<tr><th>Sted</th><td><input type="text" class="regular-text" name="tsb_settings[ics_location]" value="<?php echo esc_attr( $s['ics_location'] ); ?>" placeholder="valgfri"></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected function tab_spam() {
		$s = TSB_Availability::settings();
		$modes = array(
			'none'         => 'Ingen',
			'honeypot'     => 'Honeypot (skjult felt, ingen nøgler)',
			'recaptcha'    => 'Google reCAPTCHA v2 (afkrydsning)',
			'recaptcha_v3' => 'Google reCAPTCHA v3 (usynlig, score)',
			'hcaptcha'     => 'hCaptcha',
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'tsb_group' ); ?>
			<input type="hidden" name="tsb_settings[_section]" value="spam">
			<table class="form-table">
				<tr><th>Metode</th><td>
					<select name="tsb_settings[captcha_mode]">
						<?php foreach ( $modes as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['captcha_mode'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Honeypot er altid aktivt og kræver ingen opsætning. reCAPTCHA/hCaptcha kræver nøgler nedenfor.</p>
				</td></tr>
				<tr><th>Site key</th><td><input type="text" class="regular-text" name="tsb_settings[captcha_site]" value="<?php echo esc_attr( $s['captcha_site'] ); ?>"></td></tr>
				<tr><th>Secret key</th><td><input type="text" class="regular-text" name="tsb_settings[captcha_secret]" value="<?php echo esc_attr( $s['captcha_secret'] ); ?>"></td></tr>
				<tr><th>v3 mindste score</th><td><input type="number" step="0.1" min="0" max="1" name="tsb_settings[captcha_min_score]" value="<?php echo esc_attr( $s['captcha_min_score'] ); ?>"> <span class="description">Kun reCAPTCHA v3. 0.5 anbefalet; højere = strengere.</span></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected function tab_blocks() {
		global $wpdb;
		$blocks = $wpdb->get_results( 'SELECT * FROM ' . TSB_DB::blocked_table() . ' ORDER BY block_date DESC, block_time' );
		?>
		<h2>Fjern enkelte tider / dage fra tilgængelighed</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'tsb_block' ); ?>
			<input type="hidden" name="action" value="tsb_block_add">
			<p>
				Dato <input type="date" name="block_date" required>
				Tid <input type="time" name="block_time"> <em>(tom = hele dagen)</em>
				Årsag <input type="text" name="reason" placeholder="valgfri">
				<?php submit_button( 'Bloker', 'secondary', '', false ); ?>
			</p>
		</form>
		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th>Dato</th><th>Tid</th><th>Årsag</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $blocks ) : ?>
				<tr><td colspan="4"><em>Ingen blokeringer.</em></td></tr>
			<?php endif; ?>
			<?php foreach ( $blocks as $b ) : ?>
				<tr>
					<td><?php echo esc_html( $b->block_date ); ?></td>
					<td><?php echo $b->block_time ? esc_html( substr( $b->block_time, 0, 5 ) ) : '<em>hele dagen</em>'; ?></td>
					<td><?php echo esc_html( $b->reason ); ?></td>
					<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsb_block_del&id=' . (int) $b->id ), 'tsb_block_del' ) ); ?>" onclick="return confirm('Slet blokering?')">Slet</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	protected function tab_bookings() {
		global $wpdb;
		$t        = TSB_DB::bookings_table();
		$today    = current_time( 'Y-m-d' );
		$upcoming = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE slot_date >= %s ORDER BY slot_date ASC, slot_time ASC", $today ) );
		$past     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE slot_date < %s ORDER BY slot_date DESC, slot_time DESC LIMIT 300", $today ) );

		?>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsb_export_csv' ), 'tsb_export_csv' ) ); ?>">Eksportér alle som CSV</a>
		</p>
		<?php
		echo '<h2>Kommende bookinger</h2>';
		$this->bookings_table( $upcoming, true );

		echo '<h2 style="margin-top:2em">Tidligere bookinger</h2>';
		$this->bookings_table( $past, false );
	}

	protected function bookings_table( $rows, $is_upcoming ) {
		?>
		<table class="widefat striped">
			<thead><tr><th>Dato</th><th>Tid</th><th>Navn</th><th>E-mail</th><th>Telefon</th><th>Besked</th><th>Status</th><th>Handling</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="8"><em>Ingen.</em></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $bk ) : ?>
				<tr<?php echo ( 'cancelled' === $bk->status ) ? ' style="opacity:.55"' : ''; ?>>
					<td><?php echo esc_html( $bk->slot_date ); ?></td>
					<td><?php echo esc_html( substr( $bk->slot_time, 0, 5 ) ); ?></td>
					<td><?php echo esc_html( $bk->name ); ?></td>
					<td><a href="mailto:<?php echo esc_attr( $bk->email ); ?>"><?php echo esc_html( $bk->email ); ?></a></td>
					<td><?php echo esc_html( $bk->phone ); ?></td>
					<td><?php echo esc_html( $bk->message ); ?></td>
					<td><?php echo esc_html( $bk->status ); ?></td>
					<td>
						<?php if ( $is_upcoming && 'cancelled' !== $bk->status ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:.25em;align-items:center;margin:0 0 .35em">
								<?php wp_nonce_field( 'tsb_booking_move' ); ?>
								<input type="hidden" name="action" value="tsb_booking_move">
								<input type="hidden" name="id" value="<?php echo (int) $bk->id; ?>">
								<input type="date" name="new_date" value="<?php echo esc_attr( $bk->slot_date ); ?>" required>
								<input type="time" name="new_time" value="<?php echo esc_attr( substr( $bk->slot_time, 0, 5 ) ); ?>" required>
								<button class="button button-small" type="submit">Flyt</button>
							</form>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsb_booking_cancel&id=' . (int) $bk->id ), 'tsb_booking_cancel' ) ); ?>" onclick="return confirm('Aflys booking? Tiden bliver ledig igen.')">Aflys</a> |
						<?php endif; ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsb_booking_delete&id=' . (int) $bk->id ), 'tsb_booking_delete' ) ); ?>" onclick="return confirm('Slet permanent?')">Slet</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
