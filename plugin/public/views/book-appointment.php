<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$doctors             = $this->get_active_doctors();
$booking_settings    = CAS_DB::get_settings();
$is_single_doctor    = CAS_DB::is_single_doctor_mode();
$single_doctor       = $is_single_doctor ? CAS_DB::get_single_booking_doctor() : null;
$show_single_specialty = ! empty( $booking_settings['single_doctor_show_specialty'] );
$profiles            = $this->get_accessible_patient_profiles();
$relations           = CAS_Patient::relation_options();
$blood_groups        = CAS_Patient::blood_group_options();
$edit_appointment_id = absint( $_GET['cas_edit_appointment'] ?? 0 );
$edit_appointment    = $edit_appointment_id ? CAS_Appointment::get_by_id( $edit_appointment_id ) : null;
$is_editing          = $edit_appointment && CAS_Appointment::is_patient_manageable( $edit_appointment );
$is_locked_edit      = $edit_appointment && ! $is_editing && CAS_Appointment::is_active_for_patient( $edit_appointment );
if ( ! $is_editing ) { $edit_appointment_id = 0; }
?>
<div class="cas-public-wrap cas-booking-wrap cas-booking-reference-flow"
	data-cas-component="booking"
	data-cas-edit-appointment-id="<?php echo esc_attr( $edit_appointment_id ); ?>"
	data-cas-editing="<?php echo $is_editing ? '1' : '0'; ?>"
	data-cas-edit-serial="<?php echo esc_attr( $edit_appointment && $is_editing ? $edit_appointment->serial_number : 0 ); ?>">

	<header class="cas-booking-hero">
		<span class="cas-booking-eyebrow"><?php echo esc_html__( 'Chamber appointment', 'cas' ); ?></span>
		<h2><?php echo esc_html( $is_editing ? __( 'Modify Appointment', 'cas' ) : __( 'Book an Appointment', 'cas' ) ); ?></h2>
		<p><?php echo esc_html( $is_editing ? __( 'Update the date or serial, then review the booking before saving.', 'cas' ) : __( 'Choose the patient, select a date and serial, then confirm.', 'cas' ) ); ?></p>
	</header>

	<?php if ( $is_locked_edit ) : ?>
		<div class="cas-card cas-booking-card cas-booking-locked-card">
			<div class="cas-notice cas-notice-error">
				<strong><?php echo esc_html__( 'Online change is unavailable', 'cas' ); ?></strong><br>
				<?php echo esc_html( CAS_Appointment::patient_management_lock_message( $edit_appointment ) ); ?>
			</div>
			<p><a class="cas-button cas-button-secondary" href="<?php echo esc_url( $this->page_url( absint( CAS_DB::get_settings()['portal_appointments_page_id'] ?? 0 ) ) ); ?>"><?php echo esc_html__( 'Back to My Appointments', 'cas' ); ?></a></p>
		</div>
	<?php else : ?>
		<div class="cas-card cas-booking-card">
			<ol class="cas-stepper cas-stepper-three" aria-label="<?php echo esc_attr__( 'Appointment booking steps', 'cas' ); ?>">
				<li class="is-active" data-step-dot="date"><span>1</span><small><?php echo esc_html__( 'Details', 'cas' ); ?></small></li>
				<li data-step-dot="serial"><span>2</span><small><?php echo esc_html__( 'Serial', 'cas' ); ?></small></li>
				<li data-step-dot="confirm"><span>3</span><small><?php echo esc_html__( 'Confirm', 'cas' ); ?></small></li>
			</ol>

			<div class="cas-alert" data-cas-alert hidden></div>
			<div class="cas-active-appointment-warning" data-cas-active-appointment-warning hidden></div>
			<div class="cas-spinner" data-cas-spinner hidden></div>

			<form data-cas-form="book-appointment" class="cas-booking-form" novalidate>
				<input type="hidden" name="serial_number" data-cas-selected-serial>
				<input type="hidden" name="reporting_time" data-cas-selected-reporting-time>

				<section data-cas-booking-step="date" class="cas-booking-step is-active">
					<div class="cas-booking-stage-heading">
						<span class="cas-stage-number">1</span>
						<div><h3><?php echo esc_html__( 'Appointment details', 'cas' ); ?></h3><p><?php echo esc_html( $is_single_doctor ? __( 'Select who will attend, then choose a suitable date. Available serials will open automatically.', 'cas' ) : __( 'Select your doctor, choose who will attend, then choose a suitable date. Serials will open automatically.', 'cas' ) ); ?></p></div>
					</div>
					<div class="cas-booking-policy-note">
						<strong><?php echo esc_html__( 'Before booking', 'cas' ); ?></strong>
						<span><?php echo esc_html__( 'Each patient profile can keep only one active appointment at a time.', 'cas' ); ?></span>
					</div>
					<div class="cas-booking-details-stack">
						<?php if ( $is_single_doctor && $single_doctor ) : ?>
							<input id="cas-booking-doctor" type="hidden" name="doctor_id" value="<?php echo esc_attr( $single_doctor->id ); ?>" data-cas-doctor-name="<?php echo esc_attr( $single_doctor->name ); ?>">
							<div class="cas-solo-doctor-card" aria-label="<?php echo esc_attr__( 'Selected doctor', 'cas' ); ?>">
								<span class="cas-solo-doctor-label"><?php echo esc_html__( 'Your doctor', 'cas' ); ?></span>
								<strong><?php echo esc_html( $single_doctor->name ); ?></strong>
								<?php if ( $show_single_specialty && ! empty( $single_doctor->specialty ) ) : ?><small><?php echo esc_html( $single_doctor->specialty ); ?></small><?php endif; ?>
							</div>
						<?php else : ?>
							<label for="cas-booking-doctor"><?php echo esc_html__( 'Choose doctor', 'cas' ); ?>
								<select id="cas-booking-doctor" name="doctor_id" required>
									<option value=""><?php echo esc_html__( 'Select Doctor', 'cas' ); ?></option>
									<?php foreach ( $doctors as $doctor ) : ?>
										<option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $edit_appointment ? $edit_appointment->doctor_id : 0, $doctor->id ); ?>><?php echo esc_html( $doctor->name . ( $doctor->specialty ? ' — ' . $doctor->specialty : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>

						<div class="cas-profile-select-label cas-booking-profile-early">
							<div class="cas-profile-select-heading">
								<label for="cas-booking-patient"><?php echo esc_html__( 'Booking for', 'cas' ); ?></label>
								<?php if ( ! $is_editing ) : ?>
									<button type="button" class="cas-add-relative-button" data-cas-open-relative-panel><span aria-hidden="true">＋</span><?php echo esc_html__( 'Add a relative', 'cas' ); ?></button>
								<?php endif; ?>
							</div>
							<select id="cas-booking-patient" name="patient_id" required <?php disabled( $is_editing ); ?>>
								<?php foreach ( $profiles as $profile ) : ?>
									<option value="<?php echo esc_attr( $profile->id ); ?>" <?php selected( $edit_appointment ? $edit_appointment->patient_id : 0, $profile->id ); ?>><?php echo esc_html( $profile->full_name . ' — ' . $profile->mobile ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( $is_editing ) : ?><input type="hidden" name="patient_id" value="<?php echo esc_attr( $edit_appointment->patient_id ); ?>"><?php endif; ?>
							<?php if ( ! $is_editing ) : ?>
								<p class="cas-add-relative-help"><?php echo esc_html__( 'Booking for someone new? Add a relative now; the new profile will be selected for this appointment.', 'cas' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( ! $is_editing ) : ?>
							<fieldset class="cas-quick-relative-panel" data-cas-quick-relative-panel hidden>
								<legend><?php echo esc_html__( 'Add a new relative', 'cas' ); ?></legend>
								<p><?php echo esc_html__( 'This relative will be added under your mobile account and selected for this appointment.', 'cas' ); ?></p>
								<div class="cas-form-grid">
									<label><?php echo esc_html__( 'Relative name', 'cas' ); ?><input type="text" data-cas-relative-name autocomplete="name" maxlength="190"></label>
									<label><?php echo esc_html__( 'Relation', 'cas' ); ?><select data-cas-relative-relation><option value=""><?php echo esc_html__( 'Select relation', 'cas' ); ?></option><?php foreach ( $relations as $relation ) : ?><option value="<?php echo esc_attr( $relation ); ?>"><?php echo esc_html( $relation ); ?></option><?php endforeach; ?></select></label>
									<label><?php echo esc_html__( 'Age', 'cas' ); ?><input type="number" min="0" max="125" inputmode="numeric" data-cas-relative-age></label>
									<label><?php echo esc_html__( 'Gender', 'cas' ); ?><select data-cas-relative-gender><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male"><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female"><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other"><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></label>
									<label class="cas-form-wide"><?php echo esc_html__( 'Blood group', 'cas' ); ?><select data-cas-relative-blood-group><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><?php foreach ( $blood_groups as $blood_group ) : ?><option value="<?php echo esc_attr( $blood_group ); ?>"><?php echo esc_html( $blood_group ); ?></option><?php endforeach; ?></select></label>
								</div>
								<div class="cas-quick-relative-actions">
									<button type="button" class="cas-button cas-button-secondary" data-cas-cancel-relative><?php echo esc_html__( 'Cancel', 'cas' ); ?></button>
									<button type="button" class="cas-button cas-button-primary" data-cas-save-relative><?php echo esc_html__( 'Add Relative & Use for This Appointment', 'cas' ); ?></button>
								</div>
							</fieldset>
						<?php endif; ?>

						<div class="cas-date-entry">
							<label for="cas-booking-date"><?php echo esc_html__( 'Appointment date', 'cas' ); ?>
								<input id="cas-booking-date" type="date" name="appointment_date" required min="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>" max="<?php echo esc_attr( CAS_Appointment::patient_booking_max_date() ); ?>" value="<?php echo esc_attr( $edit_appointment ? $edit_appointment->appointment_date : '' ); ?>" <?php disabled( $is_single_doctor && ! $single_doctor ); ?>>
							</label>
						</div>
					</div>
					<?php if ( $is_single_doctor && ! $single_doctor ) : ?><div class="cas-notice cas-notice-error"><?php echo esc_html__( 'The chamber has not configured its solo doctor yet. Please contact the chamber before booking.', 'cas' ); ?></div><?php endif; ?>
					<div class="cas-date-help" data-cas-date-help aria-live="polite"><?php echo esc_html__( 'After selecting a date, the available serials will appear automatically.', 'cas' ); ?></div>
					<div class="cas-date-serial-fallback">
						<button type="button" class="cas-button cas-button-secondary" data-cas-load-serials hidden><?php echo esc_html__( 'Show available serials', 'cas' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</section>

				<section data-cas-booking-step="serial" class="cas-booking-step" hidden>
					<div class="cas-booking-stage-heading">
						<span class="cas-stage-number">2</span>
						<div><h3><?php echo esc_html__( 'Choose your serial', 'cas' ); ?></h3><p><span data-cas-selected-date-label></span> · <?php echo esc_html__( 'Tap one serial to enable Review booking.', 'cas' ); ?></p></div>
					</div>
					<div class="cas-serial-grid" data-cas-serial-grid></div>
					<div class="cas-waiting-box" data-cas-waiting-box hidden>
						<p><strong><?php echo esc_html__( 'This date is fully booked.', 'cas' ); ?></strong></p>
						<p><?php echo esc_html__( 'You can continue to choose a patient and join the waiting list.', 'cas' ); ?></p>
						<p><?php echo esc_html__( 'Expected queue number:', 'cas' ); ?> <strong data-cas-queue-number></strong></p>
					</div>
					<div class="cas-waiting-success" data-cas-waiting-success hidden></div>
					<div class="cas-step-actions" data-cas-booking-serial-actions>
						<button type="button" class="cas-button cas-button-secondary" data-cas-back-step="date"><?php echo esc_html__( 'Change date', 'cas' ); ?></button>
						<button type="button" class="cas-button cas-button-primary" data-cas-next-step="confirm" disabled aria-disabled="true"><?php echo esc_html__( 'Review booking', 'cas' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</section>

				<div class="cas-selection-dialog" data-cas-serial-confirm-dialog hidden role="dialog" aria-modal="true" aria-labelledby="cas-serial-confirm-title" aria-describedby="cas-serial-confirm-message">
					<div class="cas-selection-dialog-backdrop" data-cas-serial-confirm-cancel></div>
					<div class="cas-selection-dialog-panel" role="document">
						<h3 id="cas-serial-confirm-title" data-cas-serial-confirm-title></h3>
						<p id="cas-serial-confirm-message" data-cas-serial-confirm-message></p>
						<div class="cas-selection-dialog-actions">
							<button type="button" class="cas-button cas-button-secondary" data-cas-serial-confirm-cancel></button>
							<button type="button" class="cas-button cas-button-primary" data-cas-serial-confirm-yes></button>
						</div>
					</div>
				</div>


				<section data-cas-booking-step="confirm" class="cas-booking-step" hidden>
					<div class="cas-booking-stage-heading">
						<span class="cas-stage-number">3</span>
						<div><h3 id="cas-confirm-booking-heading" data-cas-confirm-booking-heading tabindex="-1"><?php echo esc_html( $is_editing ? __( 'Confirm changes', 'cas' ) : __( 'Confirm your booking', 'cas' ) ); ?></h3><p><?php echo esc_html__( 'Review the date, serial and patient information before confirming.', 'cas' ); ?></p></div>
					</div>
					<div class="cas-confirmation-box cas-reference-confirmation">
						<div class="cas-confirmation-row"><div><span><?php echo esc_html__( 'Date', 'cas' ); ?></span><strong data-cas-confirm-date></strong></div><button type="button" class="cas-button cas-button-secondary cas-button-small" data-cas-change-step="date"><?php echo esc_html__( 'Change', 'cas' ); ?></button></div>
						<div class="cas-confirmation-row cas-confirmation-row-serial"><div><span><?php echo esc_html__( 'Serial / reporting time', 'cas' ); ?></span><strong>#<span data-cas-confirm-serial></span> · <span data-cas-confirm-reporting-time></span></strong></div><button type="button" class="cas-button cas-button-secondary cas-button-small" data-cas-change-step="serial"><?php echo esc_html__( 'Change', 'cas' ); ?></button></div>
						<div class="cas-confirmation-row"><div><span><?php echo esc_html__( 'Patient information', 'cas' ); ?></span><strong data-cas-confirm-patient></strong><small data-cas-confirm-doctor></small></div><button type="button" class="cas-button cas-button-secondary cas-button-small" data-cas-change-step="date"><?php echo esc_html__( 'Change', 'cas' ); ?></button></div>
					</div>
					<?php $attendant_phone = sanitize_text_field( $booking_settings['chamber_attendant_phone'] ?? '' ); ?>
					<div class="cas-chamber-help">
						<?php if ( $attendant_phone ) : ?>
							<?php echo esc_html__( 'Need help? Call the chamber attendant:', 'cas' ); ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $attendant_phone ) ); ?>"><strong><?php echo esc_html( $attendant_phone ); ?></strong></a>
						<?php else : ?>
							<?php echo esc_html__( 'Need help? Please contact the chamber attendant.', 'cas' ); ?>
						<?php endif; ?>
					</div>
					<label class="cas-booking-note-label" for="cas-booking-notes"><?php echo esc_html__( 'Notes for the chamber', 'cas' ); ?><textarea id="cas-booking-notes" name="notes" rows="3" placeholder="<?php echo esc_attr__( 'Optional note', 'cas' ); ?>"><?php echo esc_textarea( $edit_appointment ? $edit_appointment->notes : '' ); ?></textarea></label>
					<label class="cas-terms-check"><input type="checkbox" data-cas-booking-terms> <span><?php echo esc_html__( 'I agree to the appointment and privacy policy.', 'cas' ); ?></span></label>
					<div class="cas-step-actions">
						<button type="button" class="cas-button cas-button-secondary" data-cas-back-step="serial"><?php echo esc_html__( 'Back', 'cas' ); ?></button>
						<button type="submit" class="cas-button cas-button-primary" data-cas-booking-submit><?php echo esc_html( $is_editing ? __( 'Save Appointment Changes', 'cas' ) : __( 'Confirm Appointment', 'cas' ) ); ?></button>
					</div>
				</section>
			</form>
		</div>
	<?php endif; ?>
</div>
