<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$appointment_id = isset( $_GET['appointment_id'] ) ? absint( $_GET['appointment_id'] ) : 0;
$appointment    = $appointment_id ? CAS_Appointment::get_by_id( $appointment_id ) : null;
if ( $appointment && ! CAS_Admin::user_can_access_doctor( absint( $appointment->doctor_id ) ) ) { wp_die( esc_html__( 'You cannot access this doctor appointment.', 'cas' ) ); }
$doctors        = $this->get_admin_doctors( true );
$patients       = $this->get_active_patients( 1000 );
$blood_groups   = CAS_Patient::blood_group_options();
$return_filters = CAS_Admin::get_appointment_list_filters( $_GET, 'return_' );
$return_query   = CAS_Admin::appointment_return_query_args( $return_filters );
$return_url     = add_query_arg( array_merge( array( 'page' => 'cas-appointments' ), $return_filters ), admin_url( 'admin.php' ) );
$initial_tab    = $appointment ? 'existing' : 'existing';
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo $appointment ? esc_html__( 'Edit Appointment', 'cas' ) : esc_html__( 'Add Appointment', 'cas' ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form cas-labelled-form" data-cas-admin-appointment-form novalidate>
		<input type="hidden" name="action" value="cas_add_appointment"><input type="hidden" name="appointment_id" value="<?php echo esc_attr( $appointment_id ); ?>"><input type="hidden" name="patient_mode" value="<?php echo esc_attr( $initial_tab ); ?>" data-cas-patient-mode><?php wp_nonce_field( 'cas_add_appointment' ); ?>
		<?php foreach ( $return_query as $return_key => $return_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $return_key ); ?>" value="<?php echo esc_attr( $return_value ); ?>">
		<?php endforeach; ?>
		<h2><?php echo esc_html__( 'Appointment Details', 'cas' ); ?></h2>
		<?php if ( $appointment && ! empty( $return_filters ) ) : ?>
			<p class="description"><?php echo esc_html__( 'After saving this change you will return to the same filtered appointment list so you can continue reconfirmation calls.', 'cas' ); ?></p>
		<?php endif; ?>
		<div class="cas-form-grid">
			<p><label for="cas-doctor-id"><?php echo esc_html__( 'Doctor', 'cas' ); ?></label><select id="cas-doctor-id" name="doctor_id" required data-cas-admin-doctor><option value=""><?php echo esc_html__( 'Select Doctor', 'cas' ); ?></option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $appointment ? $appointment->doctor_id : 0, $doctor->id ); ?>><?php echo esc_html( $doctor->name . ( $doctor->specialty ? ' — ' . $doctor->specialty : '' ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="cas-appointment-date"><?php echo esc_html__( 'Appointment Date', 'cas' ); ?></label><input id="cas-appointment-date" type="date" name="appointment_date" required value="<?php echo esc_attr( $appointment ? $appointment->appointment_date : '' ); ?>" data-cas-admin-date></p>
			<p><label><input type="checkbox" name="is_vip" value="1" <?php checked( $appointment ? ! empty( $appointment->is_vip ) : false ); ?> data-cas-vip-toggle> <?php echo esc_html__( 'VIP appointment (does not consume or change the normal queue)', 'cas' ); ?></label><span class="description"><?php echo esc_html__( 'Use this only for a chamber-authorized VIP patient. Set an exact reporting time below.', 'cas' ); ?></span></p><p data-cas-vip-time-row><label for="cas-vip-time"><?php echo esc_html__( 'VIP Reporting Time', 'cas' ); ?></label><input id="cas-vip-time" type="time" name="vip_reporting_time" value="<?php echo esc_attr( $appointment && ! empty( $appointment->is_vip ) ? substr( $appointment->reporting_time, 0, 5 ) : '' ); ?>"></p><p><label for="cas-serial-number"><?php echo esc_html__( 'Serial Number', 'cas' ); ?></label><span class="cas-serial-picker-field"><input id="cas-serial-number" type="number" min="1" name="serial_number" required value="<?php echo esc_attr( $appointment ? $appointment->serial_number : '' ); ?>" data-cas-admin-serial><button type="button" class="button cas-open-slot-picker" title="<?php echo esc_attr__( 'Open appointment slot list', 'cas' ); ?>">📋</button></span><span class="description"><?php echo esc_html__( 'Click the appointment list icon to select from booked/free serial slots with reporting time.', 'cas' ); ?></span></p>
			<p><label for="cas-status"><?php echo esc_html__( 'Status', 'cas' ); ?></label><select id="cas-status" name="status"><?php foreach ( CAS_Appointment::$statuses as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $appointment ? $appointment->status : 'confirmed', $status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></option><?php endforeach; ?></select></p>
		</div>

		<div class="cas-booking-patient-tabs" data-cas-patient-tabs>
			<div class="nav-tab-wrapper cas-admin-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Patient selection method', 'cas' ); ?>">
				<button type="button" class="nav-tab nav-tab-active" role="tab" aria-selected="true" aria-controls="cas-existing-patient-panel" data-cas-patient-tab="existing"><?php echo esc_html__( 'Existing Patient', 'cas' ); ?></button>
				<button type="button" class="nav-tab" role="tab" aria-selected="false" aria-controls="cas-new-patient-panel" data-cas-patient-tab="new"><?php echo esc_html__( 'Create New Patient', 'cas' ); ?></button>
			</div>

			<section id="cas-existing-patient-panel" class="cas-patient-tab-panel" role="tabpanel" data-cas-patient-panel="existing">
				<p><label for="cas-patient-id"><?php echo esc_html__( 'Existing Patient', 'cas' ); ?></label><select id="cas-patient-id" name="patient_id" data-cas-existing-patient><option value="0"><?php echo esc_html__( 'Select existing patient', 'cas' ); ?></option><?php foreach ( $patients as $patient ) : ?><option value="<?php echo esc_attr( $patient->id ); ?>" <?php selected( $appointment ? $appointment->patient_id : 0, $patient->id ); ?>><?php echo esc_html( $patient->full_name . ' — ' . $patient->mobile ); ?></option><?php endforeach; ?></select></p>
				<p><label for="cas-existing-notes"><?php echo esc_html__( 'Notes', 'cas' ); ?></label><textarea id="cas-existing-notes" name="existing_notes" rows="3" class="large-text"><?php echo esc_textarea( $appointment ? $appointment->notes : '' ); ?></textarea></p>
				<?php submit_button( $appointment ? __( 'Update Appointment', 'cas' ) : __( 'Book Appointment', 'cas' ), 'primary', 'submit', false ); ?>
			</section>

			<section id="cas-new-patient-panel" class="cas-patient-tab-panel" role="tabpanel" data-cas-patient-panel="new" hidden>
				<p class="description"><?php echo esc_html__( 'Use these fields when the patient is not already available in the existing-patient list.', 'cas' ); ?></p>
				<div class="cas-form-grid">
					<p><label for="cas-new-name"><?php echo esc_html__( 'New Patient Name', 'cas' ); ?></label><input id="cas-new-name" name="new_patient_full_name" data-cas-new-name></p>
					<p><label for="cas-new-mobile"><?php echo esc_html__( 'New Patient Mobile', 'cas' ); ?></label><input id="cas-new-mobile" name="new_patient_mobile" placeholder="01XXXXXXXXX" inputmode="tel" data-cas-new-mobile></p>
					<p><label for="cas-new-age"><?php echo esc_html__( 'New Patient Age', 'cas' ); ?></label><input id="cas-new-age" type="number" min="0" max="125" name="new_patient_age" data-cas-new-age></p>
					<p><label for="cas-new-gender"><?php echo esc_html__( 'New Patient Gender', 'cas' ); ?></label><select id="cas-new-gender" name="new_patient_gender"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male"><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female"><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other"><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></p>
					<p><label for="cas-new-blood"><?php echo esc_html__( 'New Patient Blood Group', 'cas' ); ?></label><select id="cas-new-blood" name="new_patient_blood_group"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><?php foreach ( $blood_groups as $bg ) : ?><option value="<?php echo esc_attr( $bg ); ?>"><?php echo esc_html( $bg ); ?></option><?php endforeach; ?></select></p>
					<p><label for="cas-new-city"><?php echo esc_html__( 'New Patient City', 'cas' ); ?></label><input id="cas-new-city" name="new_patient_city"></p>
					<p><label for="cas-new-email"><?php echo esc_html__( 'New Patient Email', 'cas' ); ?></label><input id="cas-new-email" type="email" name="new_patient_email" data-cas-new-email></p>
					<p class="cas-form-wide"><label for="cas-new-address"><?php echo esc_html__( 'New Patient Address', 'cas' ); ?></label><textarea id="cas-new-address" name="new_patient_address" rows="2"></textarea></p>
				</div>
				<p><label for="cas-new-notes"><?php echo esc_html__( 'Notes', 'cas' ); ?></label><textarea id="cas-new-notes" name="new_notes" rows="3" class="large-text"></textarea></p>
				<?php submit_button( __( 'Book Appointment', 'cas' ), 'primary', 'submit', false ); ?>
			</section>
		</div>
		<?php if ( $appointment ) : ?><a class="button cas-back-to-list" href="<?php echo esc_url( $return_url ); ?>"><?php echo esc_html__( 'Back to Filtered Appointments', 'cas' ); ?></a><?php endif; ?>
	</form>
	<div class="cas-slot-modal" data-cas-slot-modal hidden><div class="cas-slot-modal-inner"><button type="button" class="button-link cas-slot-modal-close" data-cas-slot-close>×</button><h2><?php echo esc_html__( 'Appointment Serial Slots', 'cas' ); ?></h2><p class="description"><?php echo esc_html__( 'Booked serials are red. Free serials can be selected.', 'cas' ); ?></p><div class="cas-slot-toolbar"><label><?php echo esc_html__( 'Date', 'cas' ); ?> <input type="date" data-cas-slot-date></label><button type="button" class="button" data-cas-slot-refresh><?php echo esc_html__( 'Refresh Slots', 'cas' ); ?></button></div><div class="cas-slot-grid" data-cas-slot-grid></div></div></div>
</div>
