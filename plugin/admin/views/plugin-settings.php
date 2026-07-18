<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = CAS_DB::get_settings();
$pages    = get_pages(
	array(
		'post_status' => array( 'publish', 'draft', 'private' ),
		'sort_column' => 'post_title',
	)
);
$active_doctors = method_exists( $this, 'get_admin_doctors' ) ? $this->get_admin_doctors( true ) : array();
$appointment_mode = sanitize_key( (string) ( $settings['appointment_mode'] ?? 'multiple' ) );
$single_doctor_id = absint( $settings['single_doctor_id'] ?? 0 );

$page_fields = array(
	'portal_login_page_id'        => array(
		'label'       => __( 'Patient Login Page', 'cas' ),
		'shortcode'   => '[cas_patient_login]',
		'description' => __( 'Page where patients enter mobile number and OTP.', 'cas' ),
	),
	'portal_dashboard_page_id'    => array(
		'label'       => __( 'Patient Dashboard Page', 'cas' ),
		'shortcode'   => '[cas_patient_dashboard]',
		'description' => __( 'Page patients see after login.', 'cas' ),
	),
	'portal_booking_page_id'      => array(
		'label'       => __( 'Book Appointment Page', 'cas' ),
		'shortcode'   => '[cas_book_appointment]',
		'description' => __( 'Page containing the appointment booking wizard.', 'cas' ),
	),
	'portal_appointments_page_id' => array(
		'label'       => __( 'My Appointments Page', 'cas' ),
		'shortcode'   => '[cas_my_appointments]',
		'description' => __( 'Page where patients view past and upcoming appointments.', 'cas' ),
	),
	'portal_messages_page_id'     => array(
		'label'       => __( 'Patient Messages Page', 'cas' ),
		'shortcode'   => '[cas_messages]',
		'description' => __( 'Page where patients send and view chamber messages.', 'cas' ),
	),
);
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Plugin Settings', 'cas' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form">
		<input type="hidden" name="action" value="cas_save_plugin_settings">
		<?php wp_nonce_field( 'cas_save_plugin_settings' ); ?>

		<h2><?php echo esc_html__( 'General Settings', 'cas' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cas-brand-name"><?php echo esc_html__( 'Brand Name', 'cas' ); ?></label></th>
				<td>
					<input type="text" id="cas-brand-name" name="brand_name" class="regular-text" value="<?php echo esc_attr( $settings['brand_name'] ); ?>">
					<p class="description"><?php echo esc_html__( 'Used in OTP and SMS templates.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-items-per-page"><?php echo esc_html__( 'Admin Items Per Page', 'cas' ); ?></label></th>
				<td><input type="number" id="cas-items-per-page" name="items_per_page" min="1" value="<?php echo esc_attr( $settings['items_per_page'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-currency"><?php echo esc_html__( 'Currency', 'cas' ); ?></label></th>
				<td>
					<input type="text" id="cas-currency" name="currency" class="regular-text" value="<?php echo esc_attr( $settings['currency'] ); ?>">
					<p class="description"><?php echo esc_html__( 'Reserved for future payment features. Example: BDT.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-frontend-language"><?php echo esc_html__( 'Patient Portal Default Language', 'cas' ); ?></label></th>
				<td><select id="cas-frontend-language" name="frontend_default_language"><option value="bn" <?php selected( $settings['frontend_default_language'] ?? 'bn', 'bn' ); ?>>বাংলা</option><option value="en" <?php selected( $settings['frontend_default_language'] ?? 'bn', 'en' ); ?>>English</option></select><p class="description"><?php echo esc_html__( 'Patients can switch between Bangla and English from the portal.', 'cas' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-after-registration"><?php echo esc_html__( 'After New Patient Registration', 'cas' ); ?></label></th>
				<td>
					<select id="cas-after-registration" name="after_new_patient_registration">
						<option value="booking" <?php selected( $settings['after_new_patient_registration'] ?? 'booking', 'booking' ); ?>><?php echo esc_html__( 'Go directly to Book Appointment', 'cas' ); ?></option>
						<option value="dashboard" <?php selected( $settings['after_new_patient_registration'] ?? 'booking', 'dashboard' ); ?>><?php echo esc_html__( 'Go to Dashboard', 'cas' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Controls the destination only after a newly verified patient creates the minimum profile. Existing-patient login behavior is unchanged.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-booking-window-days"><?php echo esc_html__( 'Patient Advance Booking Window', 'cas' ); ?></label></th>
				<td>
					<input id="cas-booking-window-days" type="number" min="1" max="3650" name="patient_booking_window_days" value="<?php echo esc_attr( $settings['patient_booking_window_days'] ?? 30 ); ?>"> <?php echo esc_html__( 'days', 'cas' ); ?>
					<p class="description"><?php echo esc_html__( 'Patients may book from today through this many days ahead. Examples: 7, 30, or 365. Chamber staff are not restricted by this setting.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-patient-modify-limit"><?php echo esc_html__( 'Patient Appointment Modification Limit', 'cas' ); ?></label></th>
				<td>
					<input id="cas-patient-modify-limit" type="number" min="0" max="20" name="patient_appointment_modify_limit" value="<?php echo esc_attr( $settings['patient_appointment_modify_limit'] ?? 2 ); ?>"> <?php echo esc_html__( 'times', 'cas' ); ?>
					<p class="description"><?php echo esc_html__( 'Maximum number of times a patient may change a confirmed appointment from the patient portal. Enter 0 to disable patient-side modifications. Chamber staff are not restricted.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-attendant-phone"><?php echo esc_html__( 'Chamber Attendant Phone', 'cas' ); ?></label></th>
				<td>
					<input id="cas-attendant-phone" type="text" name="chamber_attendant_phone" class="regular-text" value="<?php echo esc_attr( $settings['chamber_attendant_phone'] ?? '' ); ?>" placeholder="01XXXXXXXXX">
					<p class="description"><?php echo esc_html__( 'Shown to patients when they need help or have reached the configured online appointment modification limit.', 'cas' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Appointment Email Notifications', 'cas' ); ?></th>
				<td><label><input type="checkbox" name="appointment_email_notifications_enabled" value="1" <?php checked( ! empty( $settings['appointment_email_notifications_enabled'] ) ); ?>> <?php echo esc_html__( 'Email patients when appointment date, reporting time, or status changes (when the patient has an email address).', 'cas' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-poll-seconds"><?php echo esc_html__( 'Live Availability Refresh', 'cas' ); ?></label></th>
				<td><input id="cas-poll-seconds" type="number" min="5" max="60" name="availability_poll_seconds" value="<?php echo esc_attr( $settings['availability_poll_seconds'] ?? 15 ); ?>"> <?php echo esc_html__( 'seconds', 'cas' ); ?><p class="description"><?php echo esc_html__( 'How often an open booking page checks for changed serial availability.', 'cas' ); ?></p></td>
			</tr>
		</table>


		<h2><?php echo esc_html__( 'Appointment Setup', 'cas' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Choose whether this installation accepts appointments for one doctor/chamber or lets patients choose from multiple doctors.', 'cas' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Appointment Mode', 'cas' ); ?></th>
				<td>
					<fieldset>
						<label style="display:block;margin-bottom:8px;">
							<input type="radio" name="appointment_mode" value="single" <?php checked( 'single', $appointment_mode ); ?>>
							<strong><?php echo esc_html__( 'Single / Solo Doctor', 'cas' ); ?></strong>
							— <?php echo esc_html__( 'Patient booking hides the doctor selector and uses the selected doctor automatically.', 'cas' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="appointment_mode" value="multiple" <?php checked( 'multiple', $appointment_mode ); ?>>
							<strong><?php echo esc_html__( 'Multiple Doctors / Chambers', 'cas' ); ?></strong>
							— <?php echo esc_html__( 'Patients choose the doctor before selecting an appointment date and serial.', 'cas' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cas-single-doctor-id"><?php echo esc_html__( 'Solo Doctor / Chamber', 'cas' ); ?></label></th>
				<td>
					<select id="cas-single-doctor-id" name="single_doctor_id">
						<option value="0"><?php echo esc_html__( 'Select an active doctor', 'cas' ); ?></option>
						<?php foreach ( $active_doctors as $doctor ) : ?>
							<option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $single_doctor_id, absint( $doctor->id ) ); ?>><?php echo esc_html( $doctor->name . ( ! empty( $doctor->specialty ) ? ' — ' . $doctor->specialty : '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $active_doctors ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html__( 'No active doctor is available. Add and activate a doctor under Doctors/Chambers first.', 'cas' ); ?></p>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Required only for Single / Solo Doctor mode. It is also enforced for mobile/API appointment requests.', 'cas' ); ?></p>
					<?php endif; ?>
					<label style="display:block;margin-top:10px;"><input type="checkbox" name="single_doctor_show_specialty" value="1" <?php checked( ! empty( $settings['single_doctor_show_specialty'] ) ); ?>> <?php echo esc_html__( 'Show the doctor specialty on the patient booking page.', 'cas' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Patient Portal Pages', 'cas' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'The plugin can create and assign these patient portal pages automatically. These pages provide the patient appointment portal.', 'cas' ); ?></p>

		<div class="cas-portal-page-actions">
			<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'cas_create_portal_pages' ), admin_url( 'admin-post.php' ) ), 'cas_create_portal_pages' ) ); ?>"><?php echo esc_html__( 'Create / Repair Patient Portal Pages Automatically', 'cas' ); ?></a>
		</div>

		<table class="form-table" role="presentation">
			<?php foreach ( $page_fields as $field => $data ) : ?>
				<tr>
					<th scope="row"><label for="cas-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $data['label'] ); ?></label></th>
					<td>
						<select id="cas-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
							<option value="0"><?php echo esc_html__( 'Select a page', 'cas' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( absint( $settings[ $field ] ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php echo esc_html( $data['description'] ); ?>
							<br>
							<?php echo esc_html__( 'Required shortcode:', 'cas' ); ?> <code><?php echo esc_html( $data['shortcode'] ); ?></code>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save Plugin Settings', 'cas' ) ); ?>
	</form>
</div>
