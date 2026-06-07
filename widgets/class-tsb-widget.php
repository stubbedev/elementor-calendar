<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TSB_Widget extends Widget_Base {

	public function get_name() {
		return 'tsb_booking';
	}

	public function get_title() {
		return __( 'Timeslot Booking', 'tsb' );
	}

	public function get_icon() {
		return 'eicon-calendar';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_script_depends() {
		return array( 'tsb' );
	}

	public function get_style_depends() {
		return array( 'tsb' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array(
			'label' => __( 'Indhold', 'tsb' ),
		) );

		$this->add_control( 'intro', array(
			'label'   => __( 'Intro tekst', 'tsb' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Vælg et ledigt tidspunkt:', 'tsb' ),
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent farve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tsb' => '--tsb-accent: {{VALUE}};',
			),
		) );

		$this->add_control( 'note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Åbningstider, slot-længde, helligdage, e-mails, spam-beskyttelse og blokeringer styres under <strong>Booking</strong> i wp-admin.', 'tsb' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$set    = TSB_Availability::settings();
		$mode   = $set['captcha_mode'];
		$site   = $set['captcha_site'];
		$id     = 'tsb-' . $this->get_id();
		?>
		<div class="tsb" id="<?php echo esc_attr( $id ); ?>">
			<?php if ( ! empty( $s['intro'] ) ) : ?>
				<p class="tsb-intro"><?php echo esc_html( $s['intro'] ); ?></p>
			<?php endif; ?>

			<div class="tsb-loading"><?php esc_html_e( 'Henter ledige tider…', 'tsb' ); ?></div>
			<div class="tsb-days" hidden></div>
			<div class="tsb-slots" hidden></div>

			<form class="tsb-form" hidden>
				<p class="tsb-chosen"></p>
				<input type="hidden" name="date" value="">
				<input type="hidden" name="time" value="">

				<label><?php esc_html_e( 'Navn', 'tsb' ); ?> *
					<input type="text" name="name" required>
				</label>
				<label><?php esc_html_e( 'E-mail', 'tsb' ); ?> *
					<input type="email" name="email" required>
				</label>
				<label><?php esc_html_e( 'Telefon', 'tsb' ); ?>
					<input type="tel" name="phone">
				</label>
				<label><?php esc_html_e( 'Besked', 'tsb' ); ?>
					<textarea name="message" rows="4"></textarea>
				</label>

				<?php // Honeypot — hidden from humans, bots fill it. ?>
				<div class="tsb-hp" aria-hidden="true">
					<label>Lad dette felt være tomt
						<input type="text" name="tsb_hp" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<?php if ( 'recaptcha' === $mode && $site ) : ?>
					<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site ); ?>"></div>
				<?php elseif ( 'hcaptcha' === $mode && $site ) : ?>
					<div class="h-captcha" data-sitekey="<?php echo esc_attr( $site ); ?>"></div>
				<?php endif; ?>

				<div class="tsb-actions">
					<button type="submit" class="tsb-submit"><?php esc_html_e( 'Bekræft booking', 'tsb' ); ?></button>
					<button type="button" class="tsb-back"><?php esc_html_e( 'Tilbage', 'tsb' ); ?></button>
				</div>
			</form>

			<div class="tsb-result" hidden></div>
		</div>
		<?php
	}
}
