<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

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

	/* ---------------- control helpers (keep the section bodies readable) ---------------- */

	private function ctl_heading( $id, $label ) {
		$this->add_control( $id, array( 'label' => $label, 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
	}

	private function ctl_typo( $name, $selector ) {
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => $name,
			'selector' => $selector,
		) );
	}

	private function ctl_color( $id, $label, $selector, $prop = 'color' ) {
		// Responsive so every color can differ per device (tablet/mobile inherit
		// from the larger breakpoint until overridden — Elementor's default).
		$this->add_responsive_control( $id, array(
			'label'     => $label,
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( $selector => $prop . ': {{VALUE}};' ),
		) );
	}

	private function ctl_dim( $id, $label, $selector, $prop ) {
		$this->add_responsive_control( $id, array(
			'label'      => $label,
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $selector => $prop . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
	}

	private function ctl_slider( $id, $label, $selector, $tpl, $max = 60, $units = array( 'px' ) ) {
		$this->add_responsive_control( $id, array(
			'label'      => $label,
			'type'       => Controls_Manager::SLIDER,
			'size_units' => $units,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => $max ), 'em' => array( 'min' => 0, 'max' => 5, 'step' => .1 ) ),
			'selectors'  => array( $selector => $tpl ),
		) );
	}

	private function ctl_border( $name, $selector ) {
		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => $name,
			'selector' => $selector,
		) );
	}

	private function ctl_shadow( $name, $selector ) {
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => $name,
			'selector' => $selector,
		) );
	}

	/** Typography + text color + margin + padding for a plain text element. */
	private function ctl_text_block( $prefix, $selector, $opts = array() ) {
		$this->ctl_typo( $prefix . '_typo', $selector );
		$this->ctl_color( $prefix . '_color', __( 'Text color', 'tsb' ), $selector );
		if ( ! empty( $opts['bg'] ) ) {
			$this->ctl_color( $prefix . '_bg', __( 'Background', 'tsb' ), $selector, 'background' );
		}
		$this->ctl_dim( $prefix . '_margin', __( 'Margin', 'tsb' ), $selector, 'margin' );
		$this->ctl_dim( $prefix . '_padding', __( 'Padding', 'tsb' ), $selector, 'padding' );
	}

	protected function register_controls() {

		/* ---------------- Content ---------------- */
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'tsb' ) ) );

		$this->add_control( 'note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Opening hours, slot length, holidays, emails, spam protection and blocks are managed under <strong>Bookings → Settings</strong> in wp-admin.', 'tsb' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();

		/* ---------------- General ---------------- */
		$this->start_controls_section( 'style_general', array(
			'label' => __( 'General', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_color( 'accent', __( 'Accent color', 'tsb' ), '{{WRAPPER}} .tsb', '--tsb-accent' );
		$this->add_control( 'accent_note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Leave empty to inherit the theme / Elementor primary color.', 'tsb' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->ctl_color( 'wrap_bg', __( 'Widget background', 'tsb' ), '{{WRAPPER}} .tsb', 'background' );
		$this->ctl_slider( 'wrap_width', __( 'Max width', 'tsb' ), '{{WRAPPER}} .tsb', 'max-width: {{SIZE}}{{UNIT}};', 1200, array( 'px', '%' ) );
		$this->ctl_dim( 'wrap_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb', 'padding' );

		$this->end_controls_section();

		/* ---------------- Session picker ---------------- */
		$this->start_controls_section( 'style_types', array(
			'label' => __( 'Session picker', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_slider( 'types_gap', __( 'Card spacing', 'tsb' ), '{{WRAPPER}} .tsb-types', 'gap: {{SIZE}}{{UNIT}};', 40, array( 'px', 'em' ) );

		$this->ctl_heading( 'type_card_head', __( 'Session cards', 'tsb' ) );
		$this->ctl_typo( 'type_typo', '{{WRAPPER}} .tsb-type' );
		$this->ctl_color( 'type_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-type' );
		$this->ctl_color( 'type_bg', __( 'Background', 'tsb' ), '{{WRAPPER}} .tsb-type', 'background' );
		$this->ctl_color( 'type_hover_bg', __( 'Hover background', 'tsb' ), '{{WRAPPER}} .tsb-type:hover', 'background' );
		$this->ctl_color( 'type_hover_border', __( 'Hover border', 'tsb' ), '{{WRAPPER}} .tsb-type:hover', 'border-color' );
		$this->ctl_border( 'type_border', '{{WRAPPER}} .tsb-type' );
		$this->ctl_slider( 'type_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-type', 'border-radius: {{SIZE}}{{UNIT}};', 40 );
		$this->ctl_dim( 'type_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-type', 'padding' );
		$this->ctl_shadow( 'type_shadow', '{{WRAPPER}} .tsb-type' );

		$this->ctl_heading( 'chip_head', __( 'Chosen-session bar', 'tsb' ) );
		$this->ctl_typo( 'chip_typo', '{{WRAPPER}} .tsb-chip' );
		$this->ctl_color( 'chip_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-chip' );
		$this->ctl_color( 'chip_bg', __( 'Background', 'tsb' ), '{{WRAPPER}} .tsb-chip', 'background' );
		$this->ctl_color( 'chip_change_color', __( '“Change” color', 'tsb' ), '{{WRAPPER}} .tsb-chip-change' );
		$this->ctl_slider( 'chip_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-chip', 'border-radius: {{SIZE}}{{UNIT}};', 60 );

		$this->end_controls_section();

		/* ---------------- Calendar ---------------- */
		$this->start_controls_section( 'style_cal', array(
			'label' => __( 'Calendar', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_slider( 'cal_gap', __( 'Cell spacing', 'tsb' ), '{{WRAPPER}} .tsb', '--tsb-cal-gap: {{SIZE}}{{UNIT}};', 30, array( 'px', 'em' ) );

		$this->ctl_heading( 'cal_head_head', __( 'Header (step title / back)', 'tsb' ) );
		$this->ctl_typo( 'title_typo', '{{WRAPPER}} .tsb-cal-title, {{WRAPPER}} .tsb-slots-day, {{WRAPPER}} .tsb-chosen, {{WRAPPER}} .tsb-summary-when' );
		$this->ctl_color( 'title_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-cal-title, {{WRAPPER}} .tsb-slots-day, {{WRAPPER}} .tsb-chosen, {{WRAPPER}} .tsb-summary-when' );
		$this->ctl_color( 'nav_color', __( 'Nav/back arrow color', 'tsb' ), '{{WRAPPER}} .tsb-cal-nav' );
		$this->ctl_color( 'nav_bg', __( 'Nav/back background', 'tsb' ), '{{WRAPPER}} .tsb-cal-nav', 'background' );
		$this->ctl_color( 'nav_hover', __( 'Nav/back hover', 'tsb' ), '{{WRAPPER}} .tsb-cal-nav:hover:not(:disabled)' );
		$this->ctl_color( 'nav_hover_bg', __( 'Nav/back hover background', 'tsb' ), '{{WRAPPER}} .tsb-cal-nav:hover:not(:disabled)', 'background' );
		$this->ctl_slider( 'nav_radius', __( 'Nav/back corner radius', 'tsb' ), '{{WRAPPER}} .tsb-cal-nav', 'border-radius: {{SIZE}}{{UNIT}};', 40, array( 'px', '%' ) );
		$this->ctl_dim( 'cal_head_margin', __( 'Header margin', 'tsb' ), '{{WRAPPER}} .tsb-cal-head', 'margin' );

		$this->ctl_heading( 'weekday_head', __( 'Weekday header', 'tsb' ) );
		$this->ctl_typo( 'weekday_typo', '{{WRAPPER}} .tsb-cal-weekdays' );
		$this->ctl_color( 'weekday_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-cal-weekdays' );
		$this->ctl_dim( 'weekday_margin', __( 'Margin', 'tsb' ), '{{WRAPPER}} .tsb-cal-weekdays', 'margin' );

		$this->ctl_heading( 'day_head', __( 'Day cells', 'tsb' ) );
		$this->ctl_typo( 'cell_typo', '{{WRAPPER}} .tsb-day' );
		$this->ctl_color( 'cell_color', __( 'Day text color', 'tsb' ), '{{WRAPPER}} .tsb-day.is-open' );
		$this->ctl_color( 'cell_bg', __( 'Day background', 'tsb' ), '{{WRAPPER}} .tsb-day.is-open', 'background' );
		$this->ctl_color( 'cell_hover', __( 'Hover background', 'tsb' ), '{{WRAPPER}} .tsb-day.is-open:hover', 'background' );
		$this->ctl_color( 'cell_sel_bg', __( 'Selected background', 'tsb' ), '{{WRAPPER}} .tsb-day.is-open.is-selected', 'background' );
		$this->ctl_color( 'cell_sel_color', __( 'Selected text', 'tsb' ), '{{WRAPPER}} .tsb-day.is-open.is-selected' );
		$this->ctl_border( 'cell_border', '{{WRAPPER}} .tsb-day.is-open' );
		$this->ctl_slider( 'cell_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-day', 'border-radius: {{SIZE}}{{UNIT}};', 40, array( 'px', '%' ) );
		$this->ctl_dim( 'cell_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-day', 'padding' );
		$this->ctl_shadow( 'cell_shadow', '{{WRAPPER}} .tsb-day.is-open' );

		$this->end_controls_section();

		/* ---------------- Time slots ---------------- */
		$this->start_controls_section( 'style_slots', array(
			'label' => __( 'Time slots', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_slider( 'slots_gap', __( 'Spacing between slots', 'tsb' ), '{{WRAPPER}} .tsb-slots', 'gap: {{SIZE}}{{UNIT}};', 40, array( 'px', 'em' ) );
		$this->ctl_dim( 'slots_margin', __( 'Slots area margin', 'tsb' ), '{{WRAPPER}} .tsb-slots', 'margin' );

		$this->ctl_heading( 'slot_head', __( 'Slot buttons', 'tsb' ) );
		$this->ctl_typo( 'slot_typo', '{{WRAPPER}} .tsb-slot' );
		$this->ctl_color( 'slot_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-slot' );
		$this->ctl_color( 'slot_bg', __( 'Background', 'tsb' ), '{{WRAPPER}} .tsb-slot', 'background' );
		$this->ctl_color( 'slot_hover_color', __( 'Hover text', 'tsb' ), '{{WRAPPER}} .tsb-slot:hover' );
		$this->ctl_color( 'slot_hover_bg', __( 'Hover background', 'tsb' ), '{{WRAPPER}} .tsb-slot:hover', 'background' );
		$this->ctl_color( 'slot_hover_border', __( 'Hover border', 'tsb' ), '{{WRAPPER}} .tsb-slot:hover', 'border-color' );
		$this->ctl_border( 'slot_border', '{{WRAPPER}} .tsb-slot' );
		$this->ctl_slider( 'slot_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-slot', 'border-radius: {{SIZE}}{{UNIT}};', 40, array( 'px', '%' ) );
		$this->ctl_dim( 'slot_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-slot', 'padding' );
		$this->ctl_dim( 'slot_margin', __( 'Margin', 'tsb' ), '{{WRAPPER}} .tsb-slot', 'margin' );
		$this->ctl_shadow( 'slot_shadow', '{{WRAPPER}} .tsb-slot' );

		$this->end_controls_section();

		/* ---------------- Form & fields ---------------- */
		$this->start_controls_section( 'style_form', array(
			'label' => __( 'Form & fields', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_slider( 'form_gap', __( 'Field spacing', 'tsb' ), '{{WRAPPER}} .tsb-form', 'gap: {{SIZE}}{{UNIT}};', 40, array( 'px', 'em' ) );
		$this->ctl_slider( 'form_width', __( 'Form max width', 'tsb' ), '{{WRAPPER}} .tsb-form', 'max-width: {{SIZE}}{{UNIT}};', 900, array( 'px', '%' ) );

		$this->ctl_heading( 'label_head', __( 'Labels', 'tsb' ) );
		$this->ctl_typo( 'label_typo', '{{WRAPPER}} .tsb-form label' );
		$this->ctl_color( 'label_color', __( 'Label color', 'tsb' ), '{{WRAPPER}} .tsb-form label' );

		$this->ctl_heading( 'input_head', __( 'Input fields', 'tsb' ) );
		$this->ctl_typo( 'input_typo', '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' );
		$this->ctl_color( 'input_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' );
		$this->ctl_color( 'input_bg', __( 'Background', 'tsb' ), '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea', 'background' );
		$this->ctl_color( 'input_focus', __( 'Focus border', 'tsb' ), '{{WRAPPER}} .tsb-form input:focus, {{WRAPPER}} .tsb-form textarea:focus', 'border-color' );
		$this->ctl_border( 'input_border', '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea' );
		$this->ctl_slider( 'input_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea', 'border-radius: {{SIZE}}{{UNIT}};', 40 );
		$this->ctl_dim( 'input_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-form input, {{WRAPPER}} .tsb-form textarea', 'padding' );

		$this->end_controls_section();

		/* ---------------- Buttons & messages ---------------- */
		$this->start_controls_section( 'style_buttons', array(
			'label' => __( 'Buttons & messages', 'tsb' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->ctl_heading( 'submit_head', __( 'Confirm button', 'tsb' ) );
		$this->ctl_typo( 'submit_typo', '{{WRAPPER}} .tsb-submit' );
		$this->ctl_color( 'submit_color', __( 'Text color', 'tsb' ), '{{WRAPPER}} .tsb-submit' );
		$this->ctl_color( 'submit_bg', __( 'Background', 'tsb' ), '{{WRAPPER}} .tsb-submit', 'background' );
		$this->ctl_color( 'submit_color_hover', __( 'Text (hover)', 'tsb' ), '{{WRAPPER}} .tsb-submit:hover' );
		$this->ctl_color( 'submit_bg_hover', __( 'Background (hover)', 'tsb' ), '{{WRAPPER}} .tsb-submit:hover', 'background' );
		$this->ctl_border( 'submit_border', '{{WRAPPER}} .tsb-submit' );
		$this->ctl_slider( 'submit_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-submit', 'border-radius: {{SIZE}}{{UNIT}};', 40 );
		$this->ctl_dim( 'submit_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-submit', 'padding' );
		$this->ctl_shadow( 'submit_shadow', '{{WRAPPER}} .tsb-submit' );

		$this->ctl_heading( 'msg_head', __( 'Result messages', 'tsb' ) );
		$this->ctl_typo( 'msg_typo', '{{WRAPPER}} .tsb-result' );
		$this->ctl_color( 'ok_color', __( 'Success text', 'tsb' ), '{{WRAPPER}} .tsb-result.ok' );
		$this->ctl_color( 'ok_bg', __( 'Success background', 'tsb' ), '{{WRAPPER}} .tsb-result.ok', 'background' );
		$this->ctl_color( 'err_color', __( 'Error text', 'tsb' ), '{{WRAPPER}} .tsb-result.err' );
		$this->ctl_color( 'err_bg', __( 'Error background', 'tsb' ), '{{WRAPPER}} .tsb-result.err', 'background' );
		$this->ctl_slider( 'msg_radius', __( 'Corner radius', 'tsb' ), '{{WRAPPER}} .tsb-result', 'border-radius: {{SIZE}}{{UNIT}};', 40 );
		$this->ctl_dim( 'msg_padding', __( 'Padding', 'tsb' ), '{{WRAPPER}} .tsb-result', 'padding' );

		$this->ctl_heading( 'summary_head', __( 'Success summary', 'tsb' ) );
		$this->ctl_color( 'summary_badge', __( 'Check badge color', 'tsb' ), '{{WRAPPER}} .tsb-summary::after', 'border-color' );
		$this->ctl_color( 'summary_badge_bg', __( 'Check badge background', 'tsb' ), '{{WRAPPER}} .tsb-summary::before', 'background' );
		$this->ctl_typo( 'summary_msg_typo', '{{WRAPPER}} .tsb-summary-msg' );
		$this->ctl_color( 'summary_msg_color', __( 'Message color', 'tsb' ), '{{WRAPPER}} .tsb-summary-msg' );

		$this->end_controls_section();
	}

	/** One labelled, validatable field (input or textarea). */
	private function field( $name, $label, $type, $required, $autocomplete = '' ) {
		$ac  = $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '';
		$req = $required ? ' required aria-required="true"' : '';
		?>
		<label class="tsb-field" data-field="<?php echo esc_attr( $name ); ?>">
			<span class="tsb-field-label"><?php echo esc_html( $label ); ?><?php echo $required ? ' <span class="tsb-req">*</span>' : ''; ?></span>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea name="<?php echo esc_attr( $name ); ?>" rows="4"<?php echo $req; // phpcs:ignore ?>></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $ac . $req; // phpcs:ignore ?>>
			<?php endif; ?>
			<span class="tsb-field-error" role="alert" hidden></span>
		</label>
		<?php
	}

	/** Localized "N min" / "N h M min" duration label for a slot length. */
	private function duration_label( $minutes ) {
		$minutes = max( 1, (int) $minutes );
		$h       = intdiv( $minutes, 60 );
		$m       = $minutes % 60;
		if ( $h && $m ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$d h %2$d min', 'tsb' ), $h, $m );
		}
		if ( $h ) {
			/* translators: %d: hours */
			return sprintf( _n( '%d hour', '%d hours', $h, 'tsb' ), $h );
		}
		/* translators: %d: minutes */
		return sprintf( __( '%d min', 'tsb' ), $m );
	}

	protected function render() {
		$id     = 'tsb-' . $this->get_id();
		$types  = TSB_Types::enabled();
		$multi  = count( $types ) > 1; // show the picker first only when there's a real choice
		?>
		<div class="tsb" id="<?php echo esc_attr( $id ); ?>">
			<?php // STEP 0: session-type picker (shown first when more than one type is enabled). ?>
			<div class="tsb-step tsb-step-types"<?php echo $multi ? '' : ' hidden'; ?>>
				<div class="tsb-types-head"><?php esc_html_e( 'Choose a session', 'tsb' ); ?></div>
				<div class="tsb-types">
					<?php foreach ( $types as $t ) : ?>
						<button type="button" class="tsb-type" data-type="<?php echo esc_attr( $t['id'] ); ?>">
							<span class="tsb-type-label"><?php echo esc_html( $t['label'] ); ?></span>
							<span class="tsb-type-dur"><?php echo esc_html( $this->duration_label( $t['slot_minutes'] ) ); ?></span>
							<?php if ( ! empty( $t['description'] ) ) : ?>
								<span class="tsb-type-desc"><?php echo esc_html( $t['description'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php // STEP 1: calendar (day view + slot view) ?>
			<div class="tsb-step tsb-step-cal"<?php echo $multi ? ' hidden' : ''; ?>>
				<div class="tsb-loading"><?php esc_html_e( 'Loading available times…', 'tsb' ); ?></div>

				<div class="tsb-cal" hidden>
					<?php // day view ?>
					<div class="tsb-cal-days">
						<?php
						// Chosen-session chip — not a back button: shows what was picked,
						// with a "Change" action. JS fills + shows it only with >1 type.
						?>
						<div class="tsb-chip" hidden>
							<span class="tsb-chip-label"><span class="tsb-type-name"></span><span class="tsb-chip-dur"></span></span>
							<button type="button" class="tsb-chip-change tsb-type-change"><?php esc_html_e( 'Change', 'tsb' ); ?></button>
						</div>
						<div class="tsb-cal-head">
							<button type="button" class="tsb-cal-nav tsb-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'tsb' ); ?>">&lsaquo;</button>
							<span class="tsb-cal-title"></span>
							<button type="button" class="tsb-cal-nav tsb-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'tsb' ); ?>">&rsaquo;</button>
						</div>
						<div class="tsb-cal-weekdays"></div>
						<div class="tsb-cal-grid"></div>
					</div>

					<?php // slot view (shown after a day is picked) ?>
					<div class="tsb-cal-slots" hidden>
						<div class="tsb-cal-head">
							<button type="button" class="tsb-cal-nav tsb-cal-back" aria-label="<?php esc_attr_e( 'Back to days', 'tsb' ); ?>">&lsaquo;</button>
							<span class="tsb-slots-day"></span>
							<span class="tsb-cal-spacer" aria-hidden="true"></span>
						</div>
						<div class="tsb-slots"></div>
					</div>
				</div>

				<?php // STEP 2: contact form (only after a slot is picked) ?>
				<form class="tsb-form" hidden novalidate>
					<div class="tsb-cal-head">
						<button type="button" class="tsb-cal-nav tsb-back" aria-label="<?php esc_attr_e( 'Back to times', 'tsb' ); ?>">&lsaquo;</button>
						<span class="tsb-chosen"></span>
						<span class="tsb-cal-spacer" aria-hidden="true"></span>
					</div>
					<input type="hidden" name="date" value="">
					<input type="hidden" name="time" value="">

					<?php
					$this->field( 'name', __( 'Name', 'tsb' ), 'text', true, 'name' );
						$this->field( 'email', __( 'Email', 'tsb' ), 'email', true, 'email' );
						foreach ( TSB_Availability::form_fields() as $f ) {
							$this->field( $f['name'], $f['label'], $f['type'], (bool) $f['required'], TSB_Availability::field_autocomplete( $f ) );
						}
					?>

					<?php // Honeypot — hidden from humans, bots fill it. ?>
					<div class="tsb-hp" aria-hidden="true">
						<label><?php esc_html_e( 'Leave this field empty', 'tsb' ); ?>
							<input type="text" name="tsb_hp" tabindex="-1" autocomplete="off">
						</label>
					</div>

					<div class="tsb-actions">
						<button type="submit" class="tsb-submit"><?php esc_html_e( 'Confirm booking', 'tsb' ); ?></button>
					</div>
				</form>

					<?php // Success summary (shown once booked). ?>
					<div class="tsb-summary" hidden>
						<div class="tsb-cal-head"><span class="tsb-summary-when"></span></div>
						<p class="tsb-summary-msg" role="status"></p>
						<p class="tsb-summary-ref"></p>
						<button type="button" class="tsb-book-another tsb-submit"><?php esc_html_e( 'Book another', 'tsb' ); ?></button>
					</div>
			</div>

			<div class="tsb-result" role="alert" hidden></div>
		</div>
		<?php
	}
}
