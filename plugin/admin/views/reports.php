<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$date_from = isset( $_GET['date_from'] ) ? CAS_Admin::sanitize_date( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', current_time( 'timestamp' ) );
$date_to   = isset( $_GET['date_to'] ) ? CAS_Admin::sanitize_date( wp_unslash( $_GET['date_to'] ) ) : $date_from;
$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
$doctors   = $this->get_admin_doctors( true );
if ( ! CAS_Admin::can_view_all_doctors() && $doctor_id && ! CAS_Admin::user_can_access_doctor( $doctor_id ) ) { $doctor_id = 0; }
$args = array( 'date_from' => $date_from, 'date_to' => $date_to, 'doctor_id' => $doctor_id );
if ( ! CAS_Admin::can_view_all_doctors() && ! $doctor_id ) { $args['doctor_ids'] = CAS_Admin::get_current_user_allowed_doctor_ids(); }
$summary = CAS_Reports::get_summary( $args );
$csv_url = wp_nonce_url( add_query_arg( array( 'action' => 'cas_export_csv', 'type' => 'appointments', 'date_from' => $date_from, 'date_to' => $date_to, 'doctor_id' => $doctor_id ), admin_url( 'admin-post.php' ) ), 'cas_export_csv' );
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Reports', 'cas' ); ?></h1>
	<form method="get" class="cas-filter-bar">
		<input type="hidden" name="page" value="cas-reports">
		<label for="cas-report-from" class="cas-inline-label"><?php echo esc_html__( 'Date From', 'cas' ); ?></label><input id="cas-report-from" type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
		<label for="cas-report-to" class="cas-inline-label"><?php echo esc_html__( 'Date To', 'cas' ); ?></label><input id="cas-report-to" type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
		<label for="cas-report-doctor" class="cas-inline-label"><?php echo esc_html__( 'Doctor', 'cas' ); ?></label>
		<select id="cas-report-doctor" name="doctor_id"><option value="0"><?php echo esc_html__( 'All Allowed Doctors', 'cas' ); ?></option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $doctor_id, $doctor->id ); ?>><?php echo esc_html( $doctor->name ); ?></option><?php endforeach; ?></select>
		<button class="button button-primary"><?php echo esc_html__( 'Filter', 'cas' ); ?></button>
		<a class="button" href="<?php echo esc_url( $csv_url ); ?>"><?php echo esc_html__( 'CSV Export', 'cas' ); ?></a>
	</form>
	<div class="cas-stat-grid">
		<div class="cas-stat-card"><span><?php echo esc_html__( 'Total', 'cas' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['total_appointments'] ) ); ?></strong></div>
		<div class="cas-stat-card"><span><?php echo esc_html__( 'Waiting', 'cas' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['waiting_list_count'] ) ); ?></strong></div>
		<div class="cas-stat-card"><span><?php echo esc_html__( 'No Shows', 'cas' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['no_shows'] ) ); ?></strong></div>
		<div class="cas-stat-card"><span><?php echo esc_html__( 'SMS Sent', 'cas' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['sms_sent_count'] ) ); ?></strong></div>
	</div>
	<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Status', 'cas' ); ?></th><th><?php echo esc_html__( 'Count', 'cas' ); ?></th></tr></thead><tbody><?php foreach ( $summary['counts'] as $status => $count ) : ?><tr><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr><?php endforeach; ?></tbody></table>
</div>
