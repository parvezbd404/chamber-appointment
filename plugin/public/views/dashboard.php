<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$patient        = $this->get_current_patient();
$appointment    = $patient ? $this->get_upcoming_appointment( $patient->id ) : null;
$family_members = $this->get_family_members();
$profiles       = $this->get_accessible_patient_profiles();
$blood_groups   = CAS_Patient::blood_group_options();
$relations      = CAS_Patient::relation_options();
$settings       = CAS_DB::get_settings();
$booking_url    = $this->page_url( absint( $settings['portal_booking_page_id'] ?? 0 ) );
$appointments_url = $this->page_url( absint( $settings['portal_appointments_page_id'] ?? 0 ) );

$patient_name = $patient ? (string) $patient->full_name : '';
$patient_age  = $patient ? CAS_Patient::calculate_age( $patient->date_of_birth ) : '';
$doctor_name  = $appointment ? trim( (string) $appointment->doctor_name ) : '';
$doctor_initial = $doctor_name ? ( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $doctor_name, 0, 1 ) ) : strtoupper( substr( $doctor_name, 0, 1 ) ) ) : 'D';
$gender_label = $patient && $patient->gender ? ucwords( str_replace( '_', ' ', $patient->gender ) ) : '—';
?>
<div class="cas-public-wrap cas-dashboard-wrap" data-cas-component="dashboard">
	<?php echo $this->render_profile_completion_notice( $patient, 'dashboard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<header class="cas-dashboard-hero">
		<div class="cas-dashboard-welcome">
			<span class="cas-eyebrow"><?php echo esc_html__( 'Patient Care Portal', 'cas' ); ?></span>
			<h2><?php echo esc_html( sprintf( __( 'Welcome, %s', 'cas' ), $patient_name ) ); ?></h2>
			<p><?php echo esc_html__( 'Manage appointments, your profile, family members and chamber messages in one place.', 'cas' ); ?></p>
		</div>
		<div class="cas-dashboard-header-actions" aria-label="<?php echo esc_attr__( 'Quick actions', 'cas' ); ?>">
			<a class="cas-button cas-button-primary cas-dashboard-book-button" href="<?php echo esc_url( $booking_url ); ?>"><span aria-hidden="true">＋</span><?php echo esc_html__( 'Book an Appointment', 'cas' ); ?></a>
			<button class="cas-icon-button cas-icon-button-danger" type="button" data-cas-logout aria-label="<?php echo esc_attr__( 'Log out', 'cas' ); ?>" title="<?php echo esc_attr__( 'Log out', 'cas' ); ?>"><span aria-hidden="true">↪</span><span class="cas-icon-button-label"><?php echo esc_html__( 'Logout', 'cas' ); ?></span></button>
		</div>
	</header>

	<section class="cas-care-actions" aria-label="<?php echo esc_attr__( 'Quick patient actions', 'cas' ); ?>">
		<a class="cas-care-action cas-care-action-primary" href="<?php echo esc_url( $booking_url ); ?>">
			<span class="cas-care-action-icon" aria-hidden="true">📅</span>
			<span><strong><?php echo esc_html__( 'Schedule an Appointment', 'cas' ); ?></strong><small><?php echo esc_html__( 'Choose a doctor, date and available serial', 'cas' ); ?></small></span>
			<b aria-hidden="true">›</b>
		</a>
		<a class="cas-care-action" href="<?php echo esc_url( $appointments_url ); ?>">
			<span class="cas-care-action-icon" aria-hidden="true">🗓</span>
			<span><strong><?php echo esc_html__( 'Visits and Appointments', 'cas' ); ?></strong><small><?php echo esc_html__( 'Review upcoming and previous appointments', 'cas' ); ?></small></span>
			<b aria-hidden="true">›</b>
		</a>
		<a class="cas-care-action" href="<?php echo esc_url( $this->page_url( absint( $settings['portal_messages_page_id'] ?? 0 ) ) ); ?>">
			<span class="cas-care-action-icon" aria-hidden="true">✉</span>
			<span><strong><?php echo esc_html__( 'Messages', 'cas' ); ?></strong><small><?php echo esc_html__( 'Contact the chamber securely', 'cas' ); ?></small></span>
			<b aria-hidden="true">›</b>
		</a>
		<a class="cas-care-action" href="#cas-family-section">
			<span class="cas-care-action-icon" aria-hidden="true">👪</span>
			<span><strong><?php echo esc_html__( 'Family Access', 'cas' ); ?></strong><small><?php echo esc_html__( 'Manage patients using this mobile number', 'cas' ); ?></small></span>
			<b aria-hidden="true">›</b>
		</a>
	</section>

	<section class="cas-dashboard-feature-grid" aria-label="<?php echo esc_attr__( 'Your care overview', 'cas' ); ?>">
		<article class="cas-card cas-upcoming-card">
			<div class="cas-card-kicker"><span aria-hidden="true">📅</span><?php echo esc_html__( 'Upcoming Appointment', 'cas' ); ?></div>
			<?php if ( $appointment ) : ?>
				<span class="cas-status-badge cas-status-<?php echo esc_attr( $appointment->status ); ?> cas-card-status"><?php echo esc_html( ucwords( str_replace( '_', ' ', $appointment->status ) ) ); ?></span>
				<div class="cas-doctor-summary">
					<div class="cas-doctor-avatar" aria-hidden="true"><?php echo esc_html( $doctor_initial ); ?></div>
					<div><h3><?php echo esc_html( $doctor_name ); ?></h3><p><?php echo esc_html( $appointment->patient_name ); ?></p></div>
				</div>
				<div class="cas-appointment-meta" role="list">
					<span role="listitem"><b aria-hidden="true">▣</b><?php echo esc_html( $this->format_date( $appointment->appointment_date ) ); ?></span>
					<span role="listitem"><b aria-hidden="true">◷</b><?php echo esc_html( $this->format_time( $appointment->reporting_time ) ); ?></span>
					<span role="listitem"><b aria-hidden="true">#</b><?php echo esc_html( sprintf( __( 'Serial %d', 'cas' ), absint( $appointment->serial_number ) ) ); ?></span>
				</div>
				<a class="cas-button cas-button-secondary cas-card-action" href="<?php echo esc_url( $appointments_url ); ?>"><?php echo esc_html__( 'View appointment details', 'cas' ); ?> <span aria-hidden="true">→</span></a>
			<?php else : ?>
				<div class="cas-empty-illustration" aria-hidden="true">📅</div>
				<h3><?php echo esc_html__( 'No upcoming appointment', 'cas' ); ?></h3>
				<p class="cas-muted"><?php echo esc_html__( 'Choose a doctor and available serial when you are ready.', 'cas' ); ?></p>
				<a class="cas-button cas-button-primary cas-card-action" href="<?php echo esc_url( $booking_url ); ?>"><?php echo esc_html__( 'Book an Appointment', 'cas' ); ?></a>
			<?php endif; ?>
		</article>
	</section>

	<section id="cas-profile-section" class="cas-card cas-profile-card" data-cas-component="profile">
		<div class="cas-card-heading">
			<div><span class="cas-eyebrow"><?php echo esc_html__( 'Account', 'cas' ); ?></span><h3><?php echo esc_html__( 'My Profile', 'cas' ); ?></h3></div>
			<button class="cas-button cas-button-secondary cas-edit-profile-button" type="button" data-cas-profile-edit><span aria-hidden="true">✎</span><?php echo esc_html__( 'Edit', 'cas' ); ?></button>
		</div>
		<div class="cas-alert" data-cas-alert hidden></div><div class="cas-spinner" data-cas-spinner hidden></div>
		<div class="cas-profile-summary" data-cas-profile-summary>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'Full Name', 'cas' ); ?></span><strong data-cas-profile-summary-value="full_name"><?php echo esc_html( $patient_name ?: '—' ); ?></strong></div>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'Mobile', 'cas' ); ?></span><strong><?php echo esc_html( $patient ? $patient->mobile : '—' ); ?></strong></div>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'Age', 'cas' ); ?></span><strong data-cas-profile-summary-value="age"><?php echo esc_html( '' !== (string) $patient_age ? $patient_age : '—' ); ?></strong></div>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'Gender', 'cas' ); ?></span><strong data-cas-profile-summary-value="gender"><?php echo esc_html( $gender_label ); ?></strong></div>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'Blood Group', 'cas' ); ?></span><strong data-cas-profile-summary-value="blood_group"><?php echo esc_html( $patient && $patient->blood_group ? $patient->blood_group : '—' ); ?></strong></div>
			<div class="cas-profile-item"><span><?php echo esc_html__( 'City', 'cas' ); ?></span><strong data-cas-profile-summary-value="city"><?php echo esc_html( $patient && $patient->city ? $patient->city : '—' ); ?></strong></div>
			<div class="cas-profile-item cas-profile-item-wide"><span><?php echo esc_html__( 'Email', 'cas' ); ?></span><strong data-cas-profile-summary-value="email"><?php echo esc_html( $patient && $patient->email ? $patient->email : '—' ); ?></strong></div>
			<div class="cas-profile-item cas-profile-item-wide"><span><?php echo esc_html__( 'Address', 'cas' ); ?></span><strong data-cas-profile-summary-value="address"><?php echo esc_html( $patient && $patient->address ? $patient->address : '—' ); ?></strong></div>
		</div>
		<form data-cas-form="update-profile" class="cas-form-grid cas-profile-locked cas-profile-editor" data-cas-profile-editor hidden>
			<label><?php echo esc_html__( 'Full Name', 'cas' ); ?><input type="text" name="full_name" value="<?php echo esc_attr( $patient ? $patient->full_name : '' ); ?>" required readonly data-cas-profile-field></label>
			<label><?php echo esc_html__( 'Mobile', 'cas' ); ?><input type="text" value="<?php echo esc_attr( $patient ? $patient->mobile : '' ); ?>" disabled></label>
			<label><?php echo esc_html__( 'Age', 'cas' ); ?><input type="number" name="age" min="0" max="125" value="<?php echo esc_attr( $patient_age ); ?>" readonly data-cas-profile-field></label>
			<label><?php echo esc_html__( 'Gender', 'cas' ); ?><select name="gender" disabled data-cas-profile-field><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male" <?php selected( $patient ? $patient->gender : '', 'male' ); ?>><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female" <?php selected( $patient ? $patient->gender : '', 'female' ); ?>><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other" <?php selected( $patient ? $patient->gender : '', 'other' ); ?>><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></label>
			<label><?php echo esc_html__( 'Blood Group', 'cas' ); ?><select name="blood_group" disabled data-cas-profile-field><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><?php foreach ( $blood_groups as $bg ) : ?><option value="<?php echo esc_attr( $bg ); ?>" <?php selected( $patient ? $patient->blood_group : '', $bg ); ?>><?php echo esc_html( $bg ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html__( 'City', 'cas' ); ?><input type="text" name="city" value="<?php echo esc_attr( $patient ? $patient->city : '' ); ?>" readonly data-cas-profile-field></label>
			<label class="cas-form-wide"><?php echo esc_html__( 'Email', 'cas' ); ?><input type="email" name="email" value="<?php echo esc_attr( $patient ? $patient->email : '' ); ?>" readonly data-cas-profile-field></label>
			<label class="cas-form-wide"><?php echo esc_html__( 'Address', 'cas' ); ?><textarea name="address" rows="3" readonly data-cas-profile-field><?php echo esc_textarea( $patient ? $patient->address : '' ); ?></textarea></label>
			<div class="cas-form-wide cas-profile-actions" data-cas-profile-actions hidden><button class="cas-button cas-button-primary" type="submit"><?php echo esc_html__( 'Save Profile Changes', 'cas' ); ?></button><button class="cas-button cas-button-link" type="button" data-cas-profile-cancel><?php echo esc_html__( 'Cancel', 'cas' ); ?></button></div>
		</form>
	</section>

	<section id="cas-family-section" class="cas-card cas-family-section" data-cas-component="family">
		<div class="cas-card-heading"><div><span class="cas-eyebrow"><?php echo esc_html__( 'People under this mobile number', 'cas' ); ?></span><h3><?php echo esc_html__( 'Family Members / Same Mobile Profiles', 'cas' ); ?></h3></div></div>
		<div class="cas-alert" data-cas-alert hidden></div><div class="cas-spinner" data-cas-spinner hidden></div>
		<?php if ( ! empty( $family_members ) ) : ?>
			<div class="cas-family-cards">
			<?php foreach ( $family_members as $member ) :
				$member_initial = $member->full_name ? ( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $member->full_name, 0, 1 ) ) : strtoupper( substr( $member->full_name, 0, 1 ) ) ) : '?';
				$member_age = CAS_Patient::calculate_age( $member->date_of_birth );
			?>
				<div class="cas-family-card" data-family-member-id="<?php echo esc_attr( $member->id ); ?>">
					<button type="button" class="cas-family-toggle" data-cas-toggle-family aria-expanded="false">
						<span class="cas-family-avatar" aria-hidden="true"><?php echo esc_html( $member_initial ); ?></span>
						<span class="cas-family-heading"><strong><?php echo esc_html( $member->full_name ); ?></strong><small><?php echo esc_html( sprintf( __( '%1$s · Age %2$s', 'cas' ), $member->relation, $member_age ) ); ?></small></span>
						<span class="cas-member-chip <?php echo $member->is_active ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( $member->is_active ? __( 'Active', 'cas' ) : __( 'Inactive', 'cas' ) ); ?></span>
						<span class="cas-chevron" aria-hidden="true">⌄</span>
					</button>
					<div class="cas-family-details" hidden>
						<div class="cas-family-actions"><button type="button" class="cas-button cas-button-secondary" data-cas-edit-family><?php echo esc_html__( 'Edit', 'cas' ); ?></button><button type="button" class="cas-button cas-button-link" data-cas-deactivate-family><?php echo esc_html__( 'Deactivate', 'cas' ); ?></button><button type="button" class="cas-button cas-button-link cas-danger-link" data-cas-delete-family><?php echo esc_html__( 'Delete', 'cas' ); ?></button></div>
						<form data-cas-form="edit-family-member" class="cas-family-edit-form cas-form-grid" hidden><input type="hidden" name="family_member_id" value="<?php echo esc_attr( $member->id ); ?>"><label><?php echo esc_html__( 'Name', 'cas' ); ?><input name="full_name" value="<?php echo esc_attr( $member->full_name ); ?>" required></label><label><?php echo esc_html__( 'Relation', 'cas' ); ?><select name="relation" required><?php foreach ( $relations as $relation ) : ?><option value="<?php echo esc_attr( $relation ); ?>" <?php selected( $member->relation, $relation ); ?>><?php echo esc_html( $relation ); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__( 'Age', 'cas' ); ?><input type="number" min="0" max="125" name="age" value="<?php echo esc_attr( $member_age ); ?>"></label><label><?php echo esc_html__( 'Gender', 'cas' ); ?><select name="gender"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male" <?php selected( $member->gender, 'male' ); ?>><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female" <?php selected( $member->gender, 'female' ); ?>><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other" <?php selected( $member->gender, 'other' ); ?>><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></label><label><?php echo esc_html__( 'Blood Group', 'cas' ); ?><select name="blood_group"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><?php foreach ( $blood_groups as $bg ) : ?><option value="<?php echo esc_attr( $bg ); ?>" <?php selected( $member->blood_group, $bg ); ?>><?php echo esc_html( $bg ); ?></option><?php endforeach; ?></select></label><label class="cas-family-active-field"><input type="checkbox" name="is_active" value="1" <?php checked( $member->is_active, 1 ); ?>> <?php echo esc_html__( 'Active', 'cas' ); ?></label><div class="cas-form-wide"><button class="cas-button cas-button-primary" type="submit"><?php echo esc_html__( 'Save Family Member', 'cas' ); ?></button></div></form>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
		<?php else : ?><div class="cas-empty-state cas-family-empty"><span aria-hidden="true">👪</span><p><?php echo esc_html__( 'No family members have been added yet.', 'cas' ); ?></p></div><?php endif; ?>
		<details class="cas-add-family-details"><summary><?php echo esc_html__( 'Add a family member', 'cas' ); ?></summary><form data-cas-form="add-family-member" class="cas-form-grid"><label><?php echo esc_html__( 'Family Member Name', 'cas' ); ?><input type="text" name="full_name" required></label><label><?php echo esc_html__( 'Relation', 'cas' ); ?><select name="relation" required><option value=""><?php echo esc_html__( 'Select relation', 'cas' ); ?></option><?php foreach ( $relations as $relation ) : ?><option value="<?php echo esc_attr( $relation ); ?>"><?php echo esc_html( $relation ); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__( 'Age', 'cas' ); ?><input type="number" min="0" max="125" name="age"></label><label><?php echo esc_html__( 'Gender', 'cas' ); ?><select name="gender"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male"><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female"><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other"><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></label><label><?php echo esc_html__( 'Blood Group', 'cas' ); ?><select name="blood_group"><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><?php foreach ( $blood_groups as $bg ) : ?><option value="<?php echo esc_attr( $bg ); ?>"><?php echo esc_html( $bg ); ?></option><?php endforeach; ?></select></label><div class="cas-form-wide"><button class="cas-button cas-button-primary" type="submit"><?php echo esc_html__( 'Add Family Member', 'cas' ); ?></button></div></form></details>
	</section>
</div>
