<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$today   = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
$summary_args = array( 'date' => $today );
if ( ! CAS_Admin::can_view_all_doctors() ) { $summary_args['doctor_ids'] = CAS_Admin::get_current_user_allowed_doctor_ids(); }
$summary = CAS_Reports::get_summary( $summary_args );
$doctors = $this->get_admin_doctors( true );
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Chamber Dashboard', 'cas' ); ?></h1>
	<div class="cas-stat-grid">
		<?php foreach ( array( 'total_appointments' => 'Today Total', 'waiting_list_count' => 'Waiting List', 'sms_sent_count' => 'SMS Sent' ) as $k => $l ) : ?>
			<div class="cas-stat-card"><span><?php echo esc_html__( $l, 'cas' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary[ $k ] ) ); ?></strong></div>
		<?php endforeach; ?>
		<?php foreach ( $summary['counts'] as $s => $c ) : ?>
			<div class="cas-stat-card"><span><?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?></span><strong><?php echo esc_html( number_format_i18n( $c ) ); ?></strong></div>
		<?php endforeach; ?>
	</div>

	<p class="cas-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cas-add-appointment' ) ); ?>"><?php echo esc_html__( 'Add Appointment', 'cas' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cas-appointments&cas_date=' . rawurlencode( $today ) ) ); ?>"><?php echo esc_html__( 'Today’s List', 'cas' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cas-waiting-list&cas_date=' . rawurlencode( $today ) ) ); ?>"><?php echo esc_html__( 'Waiting List', 'cas' ); ?></a>
	</p>

	<div class="cas-card">
		<h2><?php echo esc_html__( 'Print Confirmed / Reconfirmed Patients List', 'cas' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Choose date, doctor, and status. Print or save the patient list as PDF for the chamber attendant.', 'cas' ); ?></p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-filter-bar cas-reconfirmed-print-form" target="_blank">
			<input type="hidden" name="action" value="cas_print_appointments">
			<input type="hidden" name="title" value="<?php echo esc_attr__( 'Patient Appointment List', 'cas' ); ?>">
			<input type="hidden" name="auto_print" value="1">
			<?php wp_nonce_field( 'cas_print_appointments' ); ?>
			<label for="cas-dashboard-print-date" class="cas-inline-label"><?php echo esc_html__( 'Date', 'cas' ); ?></label>
			<input id="cas-dashboard-print-date" type="date" name="cas_date" value="<?php echo esc_attr( $today ); ?>" required>
			<label for="cas-dashboard-print-doctor" class="cas-inline-label"><?php echo esc_html__( 'Doctor', 'cas' ); ?></label>
			<select id="cas-dashboard-print-doctor" name="doctor_id"><option value="0"><?php echo esc_html__( 'All Doctors', 'cas' ); ?></option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->id ); ?>"><?php echo esc_html( $doctor->name ); ?></option><?php endforeach; ?></select>
			<label for="cas-dashboard-print-status" class="cas-inline-label"><?php echo esc_html__( 'Status', 'cas' ); ?></label>
			<select id="cas-dashboard-print-status" name="status"><option value="confirmed"><?php echo esc_html__( 'Confirmed', 'cas' ); ?></option><option value="reconfirmed" selected><?php echo esc_html__( 'Reconfirmed', 'cas' ); ?></option></select>
			<button class="button button-primary"><?php echo esc_html__( 'Print / Save PDF', 'cas' ); ?></button>
		</form>
	</div>
</div>
