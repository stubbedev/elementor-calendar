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
			'label' => __( 'Content', 'tsb' ),
		) );

		$this->add_control( 'intro', array(
			'label'   => __( 'Intro text', 'tsb' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Pick a day:', 'tsb' ),
		) );

		$this->add_control( 'note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Opening hours, slot length, holidays, emails, spam protection and blocks are managed under <strong>Bookings → Settings</strong> in wp-admin.', 'tsb' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();

		/* ---------------- Style: calendar ---------------- */
		$this->start_controls_section( 'style_cal', array(
			'label' => __( 'Calendar', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent', array(
			'label'       => __( 'Accent color', 'tsb' ),
			'type'        => Controls_Manager::COLOR,
			'description' => __( 'Defaults to your theme/Elementor primary color.', 'tsb' ),
			'selectors'   => array( '{{WRAPPER}} .tsb' => '--tsb-accent: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'cal_gap', array(
			'label'      => __( 'Cell spacing', 'tsb' ),
			'type'       => Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
			'selectors'  => array( '{{WRAPPER}} .tsb' => '--tsb-cal-gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'title_color', array(
			'label'     => __( 'Month title color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-cal-title' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'weekday_color', array(
			'label'     => __( 'Weekday header color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-cal-weekdays' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'cell_typo',
			'label'    => __( 'Day cell typography', 'tsb' ),
			'selector' => '{{WRAPPER}} .tsb-day',
		) );

		$this->add_control( 'cell_color', array(
			'label'     => __( 'Day text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day.is-open' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_bg', array(
			'label'     => __( 'Day background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day.is-open' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_hover', array(
			'label'     => __( 'Day hover border', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day.is-open:hover' => 'border-color: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_sel_bg', array(
			'label'     => __( 'Selected day background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-day.is-open.is-selected' => 'background: {{VALUE}}; border-color: {{VALUE}};' ),
		) );

		$this->add_control( 'cell_radius', array(
			'label'      => __( 'Cell corner radius', 'tsb' ),
			'type'       => Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .tsb-day, {{WRAPPER}} .tsb-slot' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---------------- Style: slots ---------------- */
		$this->start_controls_section( 'style_slots', array(
			'label' => __( 'Time slots', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'slot_typo',
			'selector' => '{{WRAPPER}} .tsb-slot',
		) );

		$this->add_control( 'slot_color', array(
			'label'     => __( 'Text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-slot' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'slot_bg', array(
			'label'     => __( 'Background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-slot' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'slot_hover_bg', array(
			'label'     => __( 'Hover background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-slot:hover' => 'background: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'slot_padding', array(
			'label'      => __( 'Padding', 'tsb' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .tsb-slot' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---------------- Style: form & fields ---------------- */
		$this->start_controls_section( 'style_form', array(
			'label' => __( 'Form & fields', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'intro_head', array( 'label' => __( 'Intro text', 'tsb' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'intro_typo',
			'selector' => '{{WRAPPER}} .tsb-intro',
		) );
		$this->add_control( 'intro_color', array(
			'label'     => __( 'Color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-intro' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'chosen_head', array( 'label' => __( 'Chosen-time box', 'tsb' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'chosen_color', array(
			'label'     => __( 'Text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-chosen' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'chosen_bg', array(
			'label'     => __( 'Background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-chosen' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'label_head', array( 'label' => __( 'Labels', 'tsb' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'label_typo',
			'selector' => '{{WRAPPER}} .tsb-form label',
		) );
		$this->add_control( 'label_color', array(
			'label'     => __( 'Label color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form label' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'input_head', array( 'label' => __( 'Input fields', 'tsb' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'input_typo',
			'selector' => '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea',
		) );
		$this->add_control( 'input_color', array(
			'label'     => __( 'Text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'input_bg', array(
			'label'     => __( 'Background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'background: {{VALUE}};' ),
		) );
		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'input_border',
			'selector' => '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea',
		) );
		$this->add_control( 'input_radius', array(
			'label'     => __( 'Corner radius', 'tsb' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---------------- Style: buttons & messages ---------------- */
		$this->start_controls_section( 'style_buttons', array(
			'label' => __( 'Buttons & messages', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'submit_head', array( 'label' => __( 'Confirm button', 'tsb' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'submit_typo',
			'selector' => '{{WRAPPER}} .tsb-submit',
		) );
		$this->add_control( 'submit_color', array(
			'label'     => __( 'Text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'submit_bg', array(
			'label'     => __( 'Background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit' => 'background: {{VALUE}};' ),
		) );
		$this->add_control( 'submit_bg_hover', array(
			'label'     => __( 'Background (hover)', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-submit:hover' => 'background: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'submit_padding', array(
			'label'      => __( 'Padding', 'tsb' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .tsb-submit, {{WRAPPER}} .tsb-back' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'btn_radius', array(
			'label'     => __( 'Corner radius', 'tsb' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .tsb-submit, {{WRAPPER}} .tsb-back' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'back_head', array( 'label' => __( 'Back button', 'tsb' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'back_color', array(
			'label'     => __( 'Text color', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-back' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'back_bg', array(
			'label'     => __( 'Background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-back' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'msg_head', array( 'label' => __( 'Result messages', 'tsb' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'ok_color', array(
			'label'     => __( 'Success text', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-result.ok' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'ok_bg', array(
			'label'     => __( 'Success background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-result.ok' => 'background: {{VALUE}};' ),
		) );
		$this->add_control( 'err_color', array(
			'label'     => __( 'Error text', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-result.err' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'err_bg', array(
			'label'     => __( 'Error background', 'tsb' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .tsb-result.err' => 'background: {{VALUE}};' ),
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
				<div class="tsb-loading"><?php esc_html_e( 'Loading available times…', 'tsb' ); ?></div>

				<div class="tsb-cal" hidden>
					<div class="tsb-cal-head">
						<button type="button" class="tsb-cal-nav tsb-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'tsb' ); ?>">&lsaquo;</button>
						<span class="tsb-cal-title"></span>
						<button type="button" class="tsb-cal-nav tsb-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'tsb' ); ?>">&rsaquo;</button>
					</div>
					<div class="tsb-cal-weekdays"></div>
					<div class="tsb-cal-grid"></div>
					<p class="tsb-cal-hint"><?php esc_html_e( 'Highlighted days have available times.', 'tsb' ); ?></p>
				</div>

				<div class="tsb-slots" hidden></div>
			</div>

			<?php // STEP 2: contact form ?>
			<form class="tsb-form" hidden>
				<p class="tsb-chosen"></p>
				<input type="hidden" name="date" value="">
				<input type="hidden" name="time" value="">

				<label><?php esc_html_e( 'Name', 'tsb' ); ?> *
					<input type="text" name="name" required>
				</label>
				<label><?php esc_html_e( 'Email', 'tsb' ); ?> *
					<input type="email" name="email" required>
				</label>
				<label><?php esc_html_e( 'Phone', 'tsb' ); ?>
					<input type="tel" name="phone">
				</label>
				<label><?php esc_html_e( 'Message', 'tsb' ); ?>
					<textarea name="message" rows="4"></textarea>
				</label>

				<?php // Honeypot — hidden from humans, bots fill it. ?>
				<div class="tsb-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'tsb' ); ?>
						<input type="text" name="tsb_hp" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<?php if ( 'recaptcha' === $mode && $site ) : ?>
					<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site ); ?>"></div>
				<?php elseif ( 'hcaptcha' === $mode && $site ) : ?>
					<div class="h-captcha" data-sitekey="<?php echo esc_attr( $site ); ?>"></div>
				<?php endif; ?>

				<div class="tsb-actions">
					<button type="button" class="tsb-back"><?php esc_html_e( 'Back', 'tsb' ); ?></button>
					<button type="submit" class="tsb-submit"><?php esc_html_e( 'Confirm booking', 'tsb' ); ?></button>
				</div>
			</form>

			<div class="tsb-result" hidden></div>
		</div>
		<?php
	}
}
