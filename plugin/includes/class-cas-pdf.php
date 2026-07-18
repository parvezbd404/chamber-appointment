<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_PDF {
	public static function generate_printable_html( $date, $doctor_id = 0, $args = array() ) {
		$date      = self::sanitize_date( $date );
		$doctor_id = absint( $doctor_id );
		$args      = wp_parse_args( (array) $args, array( 'status' => '', 'title' => __( 'Appointment List', 'cas' ), 'auto_print' => false, 'doctor_ids' => array() ) );
		$status    = sanitize_key( $args['status'] );
		$title     = sanitize_text_field( $args['title'] );

		if ( '' === $date ) { return new WP_Error( 'cas_print_invalid_date', __( 'Valid date is required.', 'cas' ) ); }
		if ( $status && ! in_array( $status, CAS_Appointment::$statuses, true ) ) { return new WP_Error( 'cas_print_invalid_status', __( 'Invalid appointment status.', 'cas' ) ); }

		$appointments = CAS_Appointment::search( array( 'date' => $date, 'doctor_id' => $doctor_id, 'doctor_ids' => array_map( 'absint', (array) $args['doctor_ids'] ), 'status' => $status, 'limit' => 500, 'orderby' => 'serial_number', 'order' => 'ASC' ) );
		$brand        = sanitize_text_field( CAS_DB::get_option( 'brand_name', 'Chamber Appointment System' ) );
		$generated    = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) );
		$display_date = self::format_date( $date );
		$status_label = $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'All Statuses', 'cas' );
		$doctor_label = ! empty( $args['doctor_ids'] ) ? __( 'Assigned Doctors', 'cas' ) : __( 'All Doctors', 'cas' );

		if ( $doctor_id > 0 ) {
			$doctor = self::get_doctor( $doctor_id );
			if ( $doctor ) { $doctor_label = $doctor->name . ( $doctor->specialty ? ' - ' . $doctor->specialty : '' ); }
		}

		ob_start();
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title . ' - ' . $display_date ); ?></title>
	<style>
		@page{size:A4;margin:12mm}*{box-sizing:border-box}body{margin:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.35}.actions{text-align:right;margin:12px 0}.btn{background:#111;border:0;border-radius:4px;color:#fff;cursor:pointer;font-weight:700;padding:8px 12px}.page{max-width:210mm;margin:0 auto}.head{border-bottom:2px solid #111;display:flex;justify-content:space-between;gap:16px;margin-bottom:12px;padding-bottom:10px}.brand{font-size:22px;font-weight:800;margin:0 0 4px}.title{font-size:17px;font-weight:800;margin:0}.meta{text-align:right;color:#333}.summary{border:1px solid #888;display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin:0 0 12px}.summary div{border-right:1px solid #aaa;padding:8px}.summary div:last-child{border-right:0}.label{color:#555;display:block;font-size:10px;font-weight:700;text-transform:uppercase}.value{font-size:13px;font-weight:800}table{width:100%;border-collapse:collapse}th,td{border:1px solid #444;padding:6px;text-align:left;vertical-align:top}th{background:#eee;font-weight:800}.sl{width:42px;text-align:center}.serial{font-size:16px;font-weight:900;text-align:center}.signature{height:28px}.checkin{text-align:center;font-size:16px;font-weight:900}.checkintime{white-space:nowrap}.badge{border:1px solid #777;border-radius:3px;display:inline-block;font-size:10px;font-weight:800;padding:2px 5px;text-transform:uppercase}.footer{display:flex;justify-content:space-between;margin-top:18px}.sigline{border-top:1px solid #111;margin-top:36px;padding-top:4px;text-align:center;width:180px}.empty{border:1px dashed #999;font-size:15px;font-weight:800;padding:28px;text-align:center}@media print{.actions{display:none}thead{display:table-header-group}tr{page-break-inside:avoid}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
	</style>
</head>
<body<?php echo ! empty( $args['auto_print'] ) ? ' onload="window.print()"' : ''; ?>>
	<div class="page">
		<div class="actions"><button class="btn" onclick="window.print()"><?php echo esc_html__( 'Print / Save as PDF', 'cas' ); ?></button></div>
		<header class="head">
			<div><h1 class="brand"><?php echo esc_html( $brand ); ?></h1><h2 class="title"><?php echo esc_html( $title ); ?></h2></div>
			<div class="meta"><strong><?php echo esc_html__( 'Generated', 'cas' ); ?>:</strong> <?php echo esc_html( $generated ); ?><br><strong><?php echo esc_html__( 'Total', 'cas' ); ?>:</strong> <?php echo esc_html( number_format_i18n( count( $appointments ) ) ); ?></div>
		</header>
		<section class="summary">
			<div><span class="label"><?php echo esc_html__( 'Date', 'cas' ); ?></span><span class="value"><?php echo esc_html( $display_date ); ?></span></div>
			<div><span class="label"><?php echo esc_html__( 'Doctor', 'cas' ); ?></span><span class="value"><?php echo esc_html( $doctor_label ); ?></span></div>
			<div><span class="label"><?php echo esc_html__( 'Status', 'cas' ); ?></span><span class="value"><?php echo esc_html( $status_label ); ?></span></div>
			<div><span class="label"><?php echo esc_html__( 'List Type', 'cas' ); ?></span><span class="value"><?php echo esc_html__( 'Attendant Copy', 'cas' ); ?></span></div>
		</section>
		<?php if ( empty( $appointments ) ) : ?>
			<div class="empty"><?php echo esc_html__( 'No appointments found for this list.', 'cas' ); ?></div>
		<?php else : ?>
			<table>
				<thead><tr><th class="sl"><?php echo esc_html__( 'SL', 'cas' ); ?></th><th><?php echo esc_html__( 'Patient Name', 'cas' ); ?></th><th><?php echo esc_html__( 'Mobile', 'cas' ); ?></th><th><?php echo esc_html__( 'Doctor', 'cas' ); ?></th><th><?php echo esc_html__( 'Serial', 'cas' ); ?></th><th><?php echo esc_html__( 'Reporting Time', 'cas' ); ?></th><th><?php echo esc_html__( 'Status', 'cas' ); ?></th><th><?php echo esc_html__( 'Check-in Status', 'cas' ); ?></th><th><?php echo esc_html__( 'Check-in Time', 'cas' ); ?></th><th><?php echo esc_html__( 'Check / Signature', 'cas' ); ?></th></tr></thead>
				<tbody>
				<?php $i = 1; foreach ( $appointments as $a ) : ?>
					<?php
						$checked_in_at = isset( $a->checked_in_at ) ? (string) $a->checked_in_at : '';
						$is_checked_in = ( '' !== $checked_in_at && '0000-00-00 00:00:00' !== $checked_in_at ) || in_array( $a->status, array( 'checked_in', 'completed' ), true );
						$checkin_time  = ( '' !== $checked_in_at && '0000-00-00 00:00:00' !== $checked_in_at ) ? self::format_datetime_time( $checked_in_at ) : '';
					?>
					<tr><td class="sl"><?php echo esc_html( $i++ ); ?></td><td><?php echo esc_html( $a->patient_name ); ?></td><td><?php echo esc_html( $a->patient_mobile ); ?></td><td><?php echo esc_html( $a->doctor_name ); ?></td><td class="serial">#<?php echo esc_html( absint( $a->serial_number ) ); ?></td><td><?php echo esc_html( self::format_time( $a->reporting_time ) ); ?></td><td><span class="badge"><?php echo esc_html( ucwords( str_replace( '_', ' ', $a->status ) ) ); ?></span></td><td class="checkin"><?php echo $is_checked_in ? esc_html( '✓' ) : esc_html( '☐' ); ?></td><td class="checkintime"><?php echo esc_html( $checkin_time ); ?></td><td class="signature">&nbsp;</td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<footer class="footer"><span><?php echo esc_html__( 'Prepared for chamber attendant.', 'cas' ); ?></span><span class="sigline"><?php echo esc_html__( 'Authorized Signature', 'cas' ); ?></span></footer>
	</div>
</body>
</html><?php
		return ob_get_clean();
	}

	private static function get_doctor( $doctor_id ) { global $wpdb; $t = CAS_DB::table( 'doctors' ); return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d LIMIT 1", absint( $doctor_id ) ) ); }
	private static function sanitize_date( $date ) { $date = sanitize_text_field( (string) $date ); if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return ''; } $p = explode( '-', $date ); return checkdate( absint( $p[1] ), absint( $p[2] ), absint( $p[0] ) ) ? $date : ''; }
	private static function format_date( $date ) { $ts = strtotime( $date ); return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : sanitize_text_field( $date ); }
	private static function format_time( $time ) { $ts = strtotime( '2000-01-01 ' . $time ); return $ts ? date_i18n( get_option( 'time_format' ), $ts ) : sanitize_text_field( $time ); }
	private static function format_datetime_time( $datetime ) { $ts = strtotime( (string) $datetime ); return $ts ? date_i18n( get_option( 'time_format' ), $ts ) : ''; }
}
