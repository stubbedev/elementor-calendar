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
		// Top-level item that behaves like Posts/Pages: the bookings list.
		add_menu_page( __( 'Bookings', 'tsb' ), __( 'Bookings', 'tsb' ), 'manage_options', 'tsb_bookings', array( $this, 'page_bookings' ), 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'tsb_bookings', __( 'All bookings', 'tsb' ), __( 'All bookings', 'tsb' ), 'manage_options', 'tsb_bookings', array( $this, 'page_bookings' ) );
		add_submenu_page( 'tsb_bookings', __( 'Settings', 'tsb' ), __( 'Settings', 'tsb' ), 'manage_options', 'tsb_settings', array( $this, 'page_settings' ) );
	}

	public function register_settings() {
		register_setting( 'tsb_group', 'tsb_settings', array( $this, 'sanitize' ) );
	}

	/** Merge only the submitted section over current settings so per-tab forms don't wipe each other. */
	public function sanitize( $in ) {
		$s       = TSB_Availability::settings();
		$section = isset( $in['_section'] ) ? $in['_section'] : '';

		if ( 'availability' === $section ) {
			$s['slot_minutes'] = max( 5, (int) ( $in['slot_minutes'] ?? 30 ) );
			$s['slot_offset']  = max( 0, (int) ( $in['slot_offset'] ?? 0 ) );
			$s['slot_gap']     = max( 0, (int) ( $in['slot_gap'] ?? 0 ) );
			$s['base_start']   = max( 0, min( 23, (int) ( $in['base_start'] ?? 9 ) ) );
			$s['base_end']     = max( 1, min( 24, (int) ( $in['base_end'] ?? 17 ) ) );
			$s['days_ahead']   = max( 1, (int) ( $in['days_ahead'] ?? 30 ) );
			$s['lead_hours']   = max( 0, (int) ( $in['lead_hours'] ?? 0 ) );
			$s['block_holidays'] = empty( $in['block_holidays'] ) ? 0 : 1;

			$valid = array_keys( TSB_Holidays::countries() );
			$cc    = isset( $in['holiday_countries'] ) ? (array) $in['holiday_countries'] : array();
			$cc    = array_values( array_intersect( array_map( 'strtoupper', array_map( 'sanitize_text_field', $cc ) ), $valid ) );
			$s['holiday_countries'] = $cc ? $cc : array( 'DK' );

			$week = array();
			for ( $d = 1; $d <= 7; $d++ ) {
				$wd         = isset( $in['week'][ $d ] ) ? $in['week'][ $d ] : array();
				$week[ $d ] = array(
					'open'     => empty( $wd['open'] ) ? 0 : 1,
					'use_base' => empty( $wd['use_base'] ) ? 0 : 1,
					'start'    => max( 0, min( 23, (int) ( $wd['start'] ?? 9 ) ) ),
					'end'      => max( 1, min( 24, (int) ( $wd['end'] ?? 17 ) ) ),
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
			$mode                   = $in['captcha_mode'] ?? 'honeypot';
			$s['captcha_mode']      = in_array( $mode, array( 'none', 'honeypot', 'recaptcha', 'recaptcha_v3', 'hcaptcha' ), true ) ? $mode : 'honeypot';
			$s['captcha_site']      = sanitize_text_field( $in['captcha_site'] ?? '' );
			$s['captcha_secret']    = sanitize_text_field( $in['captcha_secret'] ?? '' );
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
		$this->redirect( 'tsb_settings', array( 'tab' => 'blocks' ) );
	}

	public function block_del() {
		check_admin_referer( 'tsb_block_del' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$wpdb->delete( TSB_DB::blocked_table(), array( 'id' => (int) ( $_GET['id'] ?? 0 ) ), array( '%d' ) );
		$this->redirect( 'tsb_settings', array( 'tab' => 'blocks' ) );
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
		$this->redirect( 'tsb_bookings', array( 'tsb_msg' => 'cancelled' ) );
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
			$this->redirect( 'tsb_bookings', array( 'tsb_msg' => 'badtime' ) );
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
		$this->redirect( 'tsb_bookings', array( 'tsb_msg' => ( false === $res ) ? 'taken' : 'moved' ) );
	}

	public function booking_delete() {
		check_admin_referer( 'tsb_booking_delete' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$wpdb->delete( TSB_DB::bookings_table(), array( 'id' => (int) ( $_GET['id'] ?? 0 ) ), array( '%d' ) );
		$this->redirect( 'tsb_bookings', array( 'tsb_msg' => 'deleted' ) );
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
		header( 'Content-Disposition: attachment; filename=bookings-' . current_time( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads non-ASCII
		fputcsv( $out, array(
			__( 'Date', 'tsb' ), __( 'Time', 'tsb' ), __( 'Name', 'tsb' ), __( 'Email', 'tsb' ),
			__( 'Phone', 'tsb' ), __( 'Message', 'tsb' ), __( 'Status', 'tsb' ), __( 'Created', 'tsb' ),
		) );
		foreach ( $rows as $r ) {
			$r['slot_time'] = substr( $r['slot_time'], 0, 5 );
			fputcsv( $out, $r );
		}
		fclose( $out );
		exit;
	}

	protected function redirect( $page, $args = array() ) {
		$args['page'] = $page;
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- bookings list page ---------- */

	public function page_bookings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'edit' === $action ) {
			$this->booking_edit_screen();
			return;
		}

		require_once TSB_PATH . 'admin/class-tsb-bookings-table.php';
		$table = new TSB_Bookings_Table();

		$cur = $table->current_action();
		if ( 'cancel' === $cur || 'delete' === $cur ) {
			check_admin_referer( 'bulk-bookings' );
			$ids = isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : array();
			$this->bulk_bookings( $cur, $ids );
			$this->redirect( 'tsb_bookings', array( 'tsb_msg' => ( 'cancel' === $cur ) ? 'cancelled' : 'deleted' ) );
		}

		$table->prepare_items();
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=tsb_export_csv' ), 'tsb_export_csv' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'tsb' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'tsb' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->bookings_notice(); ?>
			<form method="get">
				<input type="hidden" name="page" value="tsb_bookings">
				<?php $table->search_box( __( 'Search', 'tsb' ), 'tsb-search' ); ?>
			</form>
			<form method="post">
				<input type="hidden" name="page" value="tsb_bookings">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	protected function bulk_bookings( $action, $ids ) {
		global $wpdb;
		$t = TSB_DB::bookings_table();
		foreach ( $ids as $id ) {
			if ( 'cancel' === $action ) {
				$wpdb->update( $t, array( 'status' => 'cancelled', 'active' => null ), array( 'id' => (int) $id ), array( '%s', '%s' ), array( '%d' ) );
			} else {
				$wpdb->delete( $t, array( 'id' => (int) $id ), array( '%d' ) );
			}
		}
	}

	protected function bookings_notice() {
		$map = array(
			'moved'     => array( 'updated', __( 'Booking moved.', 'tsb' ) ),
			'taken'     => array( 'error', __( 'That time is already taken. Choose another.', 'tsb' ) ),
			'badtime'   => array( 'error', __( 'Invalid date/time.', 'tsb' ) ),
			'cancelled' => array( 'updated', __( 'Booking(s) cancelled. The time is available again.', 'tsb' ) ),
			'deleted'   => array( 'updated', __( 'Booking(s) deleted.', 'tsb' ) ),
		);
		$msg = isset( $_GET['tsb_msg'] ) ? sanitize_key( $_GET['tsb_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $map[ $msg ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $map[ $msg ][0] ),
				esc_html( $map[ $msg ][1] )
			);
		}
	}

	protected function booking_edit_screen() {
		global $wpdb;
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$bk = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TSB_DB::bookings_table() . ' WHERE id = %d', $id ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Move booking', 'tsb' ); ?></h1>
			<?php if ( ! $bk ) : ?>
				<p><?php esc_html_e( 'Booking not found.', 'tsb' ); ?></p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tsb_bookings' ) ); ?>"><?php esc_html_e( 'Back', 'tsb' ); ?></a>
			<?php else : ?>
				<p><strong><?php echo esc_html( $bk->name ); ?></strong> —
					<?php
					/* translators: %s: current date and time of the booking */
					printf( esc_html__( 'current time: %s', 'tsb' ), esc_html( $bk->slot_date . ' ' . substr( $bk->slot_time, 0, 5 ) ) );
					?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'tsb_booking_move' ); ?>
					<input type="hidden" name="action" value="tsb_booking_move">
					<input type="hidden" name="id" value="<?php echo (int) $bk->id; ?>">
					<table class="form-table">
						<tr><th><?php esc_html_e( 'New date', 'tsb' ); ?></th><td><input type="date" name="new_date" value="<?php echo esc_attr( $bk->slot_date ); ?>" required></td></tr>
						<tr><th><?php esc_html_e( 'New time', 'tsb' ); ?></th><td><input type="time" name="new_time" value="<?php echo esc_attr( substr( $bk->slot_time, 0, 5 ) ); ?>" required></td></tr>
					</table>
					<p>
						<?php submit_button( __( 'Move booking', 'tsb' ), 'primary', 'submit', false ); ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tsb_bookings' ) ); ?>"><?php esc_html_e( 'Cancel', 'tsb' ); ?></a>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------- settings page (tabs) ---------- */

	public function page_settings() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'availability'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'availability' => __( 'Availability', 'tsb' ),
			'emails'       => __( 'Emails', 'tsb' ),
			'spam'         => __( 'Spam protection', 'tsb' ),
			'blocks'       => __( 'Blocks', 'tsb' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'availability';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Booking settings', 'tsb' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=tsb_settings&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
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
		$s     = TSB_Availability::settings();
		$days  = TSB_Availability::weekday_names();
		$cc    = $s['holiday_countries'];
		$names = TSB_Holidays::countries();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'tsb_group' ); ?>
			<input type="hidden" name="tsb_settings[_section]" value="availability">

			<h2><?php esc_html_e( 'Base business hours', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Base hours (from–to, 24h)', 'tsb' ); ?></th><td>
					<input type="number" min="0" max="23" name="tsb_settings[base_start]" value="<?php echo esc_attr( $s['base_start'] ); ?>">
					–
					<input type="number" min="1" max="24" name="tsb_settings[base_end]" value="<?php echo esc_attr( $s['base_end'] ); ?>">
					<p class="description"><?php esc_html_e( 'Days marked “Follow base hours” use these hours.', 'tsb' ); ?></p>
				</td></tr>
			</table>

			<h2><?php esc_html_e( 'Opening hours per weekday', 'tsb' ); ?></h2>
			<table class="widefat striped" style="max-width:720px">
				<thead><tr>
					<th><?php esc_html_e( 'Day', 'tsb' ); ?></th>
					<th><?php esc_html_e( 'Open', 'tsb' ); ?></th>
					<th><?php esc_html_e( 'Follow base hours', 'tsb' ); ?></th>
					<th><?php esc_html_e( 'Own hours from', 'tsb' ); ?></th>
					<th><?php esc_html_e( 'To', 'tsb' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $days as $d => $name ) : $wd = $s['week'][ $d ]; ?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td><input type="checkbox" name="tsb_settings[week][<?php echo $d; ?>][open]" value="1" <?php checked( $wd['open'], 1 ); ?>></td>
						<td><input type="checkbox" name="tsb_settings[week][<?php echo $d; ?>][use_base]" value="1" <?php checked( ! empty( $wd['use_base'] ), true ); ?>></td>
						<td><input type="number" min="0" max="23" name="tsb_settings[week][<?php echo $d; ?>][start]" value="<?php echo esc_attr( $wd['start'] ); ?>"></td>
						<td><input type="number" min="1" max="24" name="tsb_settings[week][<?php echo $d; ?>][end]" value="<?php echo esc_attr( $wd['end'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Slots', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Slot length (min)', 'tsb' ); ?></th><td><input type="number" min="5" name="tsb_settings[slot_minutes]" value="<?php echo esc_attr( $s['slot_minutes'] ); ?>"> <span class="description"><?php esc_html_e( 'How long each time slot is.', 'tsb' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Start offset (min)', 'tsb' ); ?></th><td><input type="number" min="0" name="tsb_settings[slot_offset]" value="<?php echo esc_attr( $s['slot_offset'] ); ?>"> <span class="description"><?php esc_html_e( 'Minutes after opening before the first slot. 0 = start at opening.', 'tsb' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Gap between slots (min)', 'tsb' ); ?></th><td><input type="number" min="0" name="tsb_settings[slot_gap]" value="<?php echo esc_attr( $s['slot_gap'] ); ?>"> <span class="description"><?php esc_html_e( 'Buffer between two slots. 0 = none.', 'tsb' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Days ahead', 'tsb' ); ?></th><td><input type="number" min="1" name="tsb_settings[days_ahead]" value="<?php echo esc_attr( $s['days_ahead'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Minimum lead time (hours)', 'tsb' ); ?></th><td><input type="number" min="0" name="tsb_settings[lead_hours]" value="<?php echo esc_attr( $s['lead_hours'] ); ?>"> <span class="description"><?php esc_html_e( '0 = bookable right now', 'tsb' ); ?></span></td></tr>
			</table>

			<h2><?php esc_html_e( 'Public holidays', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Block holidays', 'tsb' ); ?></th><td><input type="checkbox" name="tsb_settings[block_holidays]" value="1" <?php checked( $s['block_holidays'], 1 ); ?>></td></tr>
				<tr><th><?php esc_html_e( 'Countries', 'tsb' ); ?></th><td>
					<select name="tsb_settings[holiday_countries][]" multiple size="8" style="min-width:240px">
						<?php foreach ( $names as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php echo in_array( $code, $cc, true ) ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Holidays are fetched from date.nager.at (cached). Hold Ctrl/Cmd to select several.', 'tsb' ); ?></p>
				</td></tr>
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
			<p class="description"><?php esc_html_e( 'Placeholders:', 'tsb' ); ?> <code>{name} {email} {phone} {message} {date} {time}</code></p>

			<h2><?php esc_html_e( 'Message to admin', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Send admin notification', 'tsb' ); ?></th><td><input type="checkbox" name="tsb_settings[admin_notify]" value="1" <?php checked( $s['admin_notify'], 1 ); ?>></td></tr>
				<tr><th><?php esc_html_e( 'Recipient', 'tsb' ); ?></th><td><input type="email" class="regular-text" name="tsb_settings[admin_to]" value="<?php echo esc_attr( $s['admin_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?> (<?php esc_attr_e( 'default', 'tsb' ); ?>)"></td></tr>
				<tr><th><?php esc_html_e( 'Subject', 'tsb' ); ?></th><td><input type="text" class="large-text" name="tsb_settings[admin_subject]" value="<?php echo esc_attr( $s['admin_subject'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Body', 'tsb' ); ?></th><td><textarea class="large-text" rows="6" name="tsb_settings[admin_body]"><?php echo esc_textarea( $s['admin_body'] ); ?></textarea></td></tr>
			</table>

			<h2><?php esc_html_e( 'Confirmation to customer', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Send confirmation', 'tsb' ); ?></th><td><input type="checkbox" name="tsb_settings[customer_confirm]" value="1" <?php checked( $s['customer_confirm'], 1 ); ?>></td></tr>
				<tr><th><?php esc_html_e( 'Subject', 'tsb' ); ?></th><td><input type="text" class="large-text" name="tsb_settings[customer_subject]" value="<?php echo esc_attr( $s['customer_subject'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Body', 'tsb' ); ?></th><td><textarea class="large-text" rows="6" name="tsb_settings[customer_body]"><?php echo esc_textarea( $s['customer_body'] ); ?></textarea></td></tr>
			</table>

			<h2><?php esc_html_e( 'Sender', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Sender name', 'tsb' ); ?></th><td><input type="text" class="regular-text" name="tsb_settings[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" placeholder="<?php esc_attr_e( 'WordPress (default)', 'tsb' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Sender email', 'tsb' ); ?></th><td><input type="email" class="regular-text" name="tsb_settings[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="<?php esc_attr_e( 'WordPress default', 'tsb' ); ?>"><p class="description"><?php esc_html_e( 'Use an address on your own domain for better deliverability (typically together with an SMTP plugin).', 'tsb' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Calendar invite (.ics)', 'tsb' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Attach .ics to customer email', 'tsb' ); ?></th><td><input type="checkbox" name="tsb_settings[ics_attach]" value="1" <?php checked( $s['ics_attach'], 1 ); ?>></td></tr>
				<tr><th><?php esc_html_e( 'Title', 'tsb' ); ?></th><td><input type="text" class="large-text" name="tsb_settings[ics_summary]" value="<?php echo esc_attr( $s['ics_summary'] ); ?>"><p class="description"><?php esc_html_e( 'Placeholders as in emails, e.g. Booking: {name}.', 'tsb' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Location', 'tsb' ); ?></th><td><input type="text" class="regular-text" name="tsb_settings[ics_location]" value="<?php echo esc_attr( $s['ics_location'] ); ?>" placeholder="<?php esc_attr_e( 'optional', 'tsb' ); ?>"></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected function tab_spam() {
		$s     = TSB_Availability::settings();
		$modes = array(
			'none'         => __( 'None', 'tsb' ),
			'honeypot'     => __( 'Honeypot (hidden field, no keys)', 'tsb' ),
			'recaptcha'    => __( 'Google reCAPTCHA v2 (checkbox)', 'tsb' ),
			'recaptcha_v3' => __( 'Google reCAPTCHA v3 (invisible, score)', 'tsb' ),
			'hcaptcha'     => __( 'hCaptcha', 'tsb' ),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'tsb_group' ); ?>
			<input type="hidden" name="tsb_settings[_section]" value="spam">
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Method', 'tsb' ); ?></th><td>
					<select name="tsb_settings[captcha_mode]">
						<?php foreach ( $modes as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['captcha_mode'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Honeypot is always active and needs no setup. reCAPTCHA/hCaptcha require the keys below.', 'tsb' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Site key', 'tsb' ); ?></th><td><input type="text" class="regular-text" name="tsb_settings[captcha_site]" value="<?php echo esc_attr( $s['captcha_site'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Secret key', 'tsb' ); ?></th><td><input type="text" class="regular-text" name="tsb_settings[captcha_secret]" value="<?php echo esc_attr( $s['captcha_secret'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'v3 minimum score', 'tsb' ); ?></th><td><input type="number" step="0.1" min="0" max="1" name="tsb_settings[captcha_min_score]" value="<?php echo esc_attr( $s['captcha_min_score'] ); ?>"> <span class="description"><?php esc_html_e( 'reCAPTCHA v3 only. 0.5 recommended; higher = stricter.', 'tsb' ); ?></span></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected function tab_blocks() {
		global $wpdb;
		$blocks = $wpdb->get_results( 'SELECT * FROM ' . TSB_DB::blocked_table() . ' ORDER BY block_date DESC, block_time' );
		?>
		<h2><?php esc_html_e( 'Remove individual times / days from availability', 'tsb' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'tsb_block' ); ?>
			<input type="hidden" name="action" value="tsb_block_add">
			<p>
				<?php esc_html_e( 'Date', 'tsb' ); ?> <input type="date" name="block_date" required>
				<?php esc_html_e( 'Time', 'tsb' ); ?> <input type="time" name="block_time"> <em>(<?php esc_html_e( 'empty = whole day', 'tsb' ); ?>)</em>
				<?php esc_html_e( 'Reason', 'tsb' ); ?> <input type="text" name="reason" placeholder="<?php esc_attr_e( 'optional', 'tsb' ); ?>">
				<?php submit_button( __( 'Block', 'tsb' ), 'secondary', '', false ); ?>
			</p>
		</form>
		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th><?php esc_html_e( 'Date', 'tsb' ); ?></th><th><?php esc_html_e( 'Time', 'tsb' ); ?></th><th><?php esc_html_e( 'Reason', 'tsb' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $blocks ) : ?>
				<tr><td colspan="4"><em><?php esc_html_e( 'No blocks.', 'tsb' ); ?></em></td></tr>
			<?php endif; ?>
			<?php foreach ( $blocks as $b ) : ?>
				<tr>
					<td><?php echo esc_html( $b->block_date ); ?></td>
					<td><?php echo $b->block_time ? esc_html( substr( $b->block_time, 0, 5 ) ) : '<em>' . esc_html__( 'whole day', 'tsb' ) . '</em>'; ?></td>
					<td><?php echo esc_html( $b->reason ); ?></td>
					<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsb_block_del&id=' . (int) $b->id ), 'tsb_block_del' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete block?', 'tsb' ) ); ?>')"><?php esc_html_e( 'Delete', 'tsb' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
