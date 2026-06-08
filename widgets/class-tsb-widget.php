<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

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

		/* ---------------- Content ---------------- */
		$this->start_controls_section( 'content', array(
			'label' => __( 'Indhold', 'tsb' ),
		) );

		$this->add_control( 'intro', array(
			'label'   => __( 'Intro tekst', 'tsb' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Vælg en dag:', 'tsb' ),
		) );

		$this->add_control( 'note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Åbningstider, slot-længde, helligdage, e-mails, spam-beskyttelse og blokeringer styres under <strong>Bookinger → Indstillinger</strong> i wp-admin.', 'tsb' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();

		/* ---------------- Style: calendar ---------------- */
		$this->start_controls_section( 'style_cal', array(
			'label' => __( 'Kalender', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent farve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#2563eb',
			'selectors' => array( '{{WRAPPER}} .tsb' => '--tsb-accent: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'cal_typo',
			'selector' => '{{WRAPPER}} .tsb-day, {{WRAPPER}} .tsb-slot',
		) );

		$this->add_control( 'cell_color', array(
			'label'     => __( 'Tekstfarve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day, {{WRAPPER}} .tsb-slot' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_bg', array(
			'label'     => __( 'Cellebaggrund', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day, {{WRAPPER}} .tsb-slot' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_radius', array(
			'label'      => __( 'Hjørneradius', 'tsb' ),
			'type'       => Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .tsb-day, {{WRAPPER}} .tsb-slot' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---------------- Style: form fields ---------------- */
		$this->start_controls_section( 'style_form', array(
			'label' => __( 'Formular', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'label_head', array(
			'label' => __( 'Etiketter', 'tsb' ),
			'type'  => Controls_Manager::HEADING,
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'label_typo',
			'selector' => '{{WRAPPER}} .tsb-form label',
		) );

		$this->add_control( 'label_color', array(
			'label'     => __( 'Etiketfarve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form label' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'input_head', array(
			'label'     => __( 'Inputfelter', 'tsb' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'input_typo',
			'selector' => '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea',
		) );

		$this->add_control( 'input_color', array(
			'label'     => __( 'Tekstfarve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'input_bg', array(
			'label'     => __( 'Baggrund', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'background: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'input_border',
			'selector' => '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea',
		) );

		$this->add_control( 'input_radius', array(
			'label'     => __( 'Hjørneradius', 'tsb' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---------------- Style: buttons ---------------- */
		$this->start_controls_section( 'style_buttons', array(
			'label' => __( 'Knapper', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'submit_head', array(
			'label' => __( 'Bekræft-knap', 'tsb' ),
			'type'  => Controls_Manager::HEADING,
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'submit_typo',
			'selector' => '{{WRAPPER}} .tsb-submit',
		) );

		$this->add_control( 'submit_color', array(
			'label'     => __( 'Tekstfarve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'submit_bg', array(
			'label'     => __( 'Baggrund', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'submit_bg_hover', array(
			'label'     => __( 'Baggrund (hover)', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit:hover' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'btn_radius', array(
			'label'     => __( 'Hjørneradius', 'tsb' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .tsb-submit, {{WRAPPER}} .tsb-back' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'back_head', array(
			'label'     => __( 'Tilbage-knap', 'tsb' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'back_color', array(
			'label'     => __( 'Tekstfarve', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-back' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'back_bg', array(
			'label'     => __( 'Baggrund', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-back' => 'background: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$set  = TSB_Availability::settings();
		$mode = $set['captcha_mode'];
		$site = $set['captcha_site'];
		$id   = 'tsb-' . $this->get_id();
		?>
		<div class="tsb" id="<?php echo esc_attr( $id ); ?>">
			<?php if ( ! empty( $s['intro'] ) ) : ?>
				<p class="tsb-intro"><?php echo esc_html( $s['intro'] ); ?></p>
			<?php endif; ?>

			<?php // STEP 1: calendar + slots ?>
			<div class="tsb-step tsb-step-cal">
				<div class="tsb-loading"><?php esc_html_e( 'Henter ledige tider…', 'tsb' ); ?></div>

				<div class="tsb-cal" hidden>
					<div class="tsb-cal-head">
						<button type="button" class="tsb-cal-nav tsb-cal-prev" aria-label="<?php esc_attr_e( 'Forrige måned', 'tsb' ); ?>">&lsaquo;</button>
						<span class="tsb-cal-title"></span>
						<button type="button" class="tsb-cal-nav tsb-cal-next" aria-label="<?php esc_attr_e( 'Næste måned', 'tsb' ); ?>">&rsaquo;</button>
					</div>
					<div class="tsb-cal-weekdays">
						<span>Ma</span><span>Ti</span><span>On</span><span>To</span><span>Fr</span><span>Lø</span><span>Sø</span>
					</div>
					<div class="tsb-cal-grid"></div>
					<p class="tsb-cal-hint"><?php esc_html_e( 'Markerede dage har ledige tider.', 'tsb' ); ?></p>
				</div>

				<div class="tsb-slots" hidden></div>
			</div>

			<?php // STEP 2: contact form ?>
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
					<button type="button" class="tsb-back"><?php esc_html_e( 'Tilbage', 'tsb' ); ?></button>
					<button type="submit" class="tsb-submit"><?php esc_html_e( 'Bekræft booking', 'tsb' ); ?></button>
				</div>
			</form>

			<div class="tsb-result" hidden></div>
		</div>
		<?php
	}
}
