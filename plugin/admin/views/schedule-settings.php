<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$doctors   = $this->get_admin_doctors( true );
$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;

if ( 0 === $doctor_id && ! empty( $doctors ) ) {
	$doctor_id = absint( $doctors[0]->id );
}

$schedule = null;

if ( $doctor_id > 0 ) {
	$table    = CAS_DB::table( 'schedules' );
	$schedule = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE doctor_id = %d LIMIT 1",
			$doctor_id
		)
	);
}

$active_days = $schedule && ! empty( $schedule->active_days ) ? array_map( 'absint', explode( ',', $schedule->active_days ) ) : array( 0, 1, 2, 3, 4, 5, 6 );
$holidays    = $schedule && ! empty( $schedule->holidays ) ? json_decode( $schedule->holidays, true ) : array();
$holidays    = is_array( $holidays ) ? $holidays : array();
$weekday_breaks = $schedule && ! empty( $schedule->weekday_breaks ) ? json_decode( $schedule->weekday_breaks, true ) : array();
$weekday_breaks = is_array( $weekday_breaks ) ? $weekday_breaks : array();
$day_labels  = array(
	0 => __( 'Sunday', 'cas' ),
	1 => __( 'Monday', 'cas' ),
	2 => __( 'Tuesday', 'cas' ),
	3 => __( 'Wednesday', 'cas' ),
	4 => __( 'Thursday', 'cas' ),
	5 => __( 'Friday', 'cas' ),
	6 => __( 'Saturday', 'cas' ),
);
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Schedule Settings', 'cas' ); ?></h1>

	<form method="get" class="cas-filter-bar">
		<input type="hidden" name="page" value="cas-schedule-settings">
		<label for="cas-schedule-doctor-filter"><strong><?php echo esc_html__( 'Select Doctor to Configure', 'cas' ); ?></strong></label>
		<select id="cas-schedule-doctor-filter" name="doctor_id">
			<?php foreach ( $doctors as $doctor ) : ?>
				<option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $doctor_id, $doctor->id ); ?>><?php echo esc_html( $doctor->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button"><?php echo esc_html__( 'Load Schedule', 'cas' ); ?></button>
	</form>

	<?php if ( empty( $doctors ) ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html__( 'Please add an active doctor before configuring schedules.', 'cas' ); ?></p></div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form">
			<input type="hidden" name="action" value="cas_save_schedule">
			<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor_id ); ?>">
			<?php wp_nonce_field( 'cas_save_schedule' ); ?>

			<h2><?php echo esc_html__( 'Doctor Chamber Schedule', 'cas' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'These settings control daily appointment capacity, serial availability, and reporting time calculation.', 'cas' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cas-daily-limit"><?php echo esc_html__( 'Daily Appointment Limit', 'cas' ); ?></label></th>
					<td><input type="number" id="cas-daily-limit" name="daily_limit" min="1" value="<?php echo esc_attr( $schedule ? $schedule->daily_limit : 40 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cas-start-time"><?php echo esc_html__( 'Chamber Start Time', 'cas' ); ?></label></th>
					<td><input type="time" id="cas-start-time" name="start_time" value="<?php echo esc_attr( substr( $schedule ? $schedule->start_time : '14:00:00', 0, 5 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cas-end-time"><?php echo esc_html__( 'Chamber End Time', 'cas' ); ?></label></th>
					<td><input type="time" id="cas-end-time" name="end_time" value="<?php echo esc_attr( substr( $schedule ? $schedule->end_time : '18:00:00', 0, 5 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cas-batch-size"><?php echo esc_html__( 'Batch Size', 'cas' ); ?></label></th>
					<td>
						<input type="number" id="cas-batch-size" name="batch_size" min="1" value="<?php echo esc_attr( $schedule ? $schedule->batch_size : 10 ); ?>">
						<p class="description"><?php echo esc_html__( 'How many serials share the same reporting time.', 'cas' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cas-reporting-interval"><?php echo esc_html__( 'Reporting Interval in Minutes', 'cas' ); ?></label></th>
					<td>
						<input type="number" id="cas-reporting-interval" name="reporting_interval" min="1" value="<?php echo esc_attr( $schedule ? $schedule->reporting_interval : 60 ); ?>">
						<p class="description"><?php echo esc_html__( 'Minutes added after each batch. Example: batch size 10 and interval 60 means serials 1-10 report at start time, 11-20 one hour later.', 'cas' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Active Chamber Days', 'cas' ); ?></th>
					<td>
						<?php foreach ( $day_labels as $day_number => $day_label ) : ?>
							<label class="cas-check" for="cas-day-<?php echo esc_attr( $day_number ); ?>">
								<input type="checkbox" id="cas-day-<?php echo esc_attr( $day_number ); ?>" name="active_days[]" value="<?php echo esc_attr( $day_number ); ?>" <?php checked( in_array( $day_number, $active_days, true ) ); ?>>
								<?php echo esc_html( $day_label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Weekday Break Times', 'cas' ); ?></th>
					<td>
						<p class="description"><?php echo esc_html__( 'Optional. Normal serial reporting times pause during the break and continue afterward. Chamber staff may book a VIP patient inside this time without consuming a normal serial.', 'cas' ); ?></p>
						<table class="widefat striped" style="max-width:760px;margin-top:10px">
							<thead><tr><th><?php echo esc_html__( 'Day', 'cas' ); ?></th><th><?php echo esc_html__( 'Enable Break', 'cas' ); ?></th><th><?php echo esc_html__( 'Break Starts', 'cas' ); ?></th><th><?php echo esc_html__( 'Break Ends', 'cas' ); ?></th></tr></thead>
							<tbody><?php foreach ( $day_labels as $day_number => $day_label ) : $day_break = isset( $weekday_breaks[ (string) $day_number ] ) && is_array( $weekday_breaks[ (string) $day_number ] ) ? $weekday_breaks[ (string) $day_number ] : array(); ?>
							<tr><td><strong><?php echo esc_html( $day_label ); ?></strong></td><td><label><input type="checkbox" name="break_enabled[<?php echo esc_attr( $day_number ); ?>]" value="1" <?php checked( ! empty( $day_break['enabled'] ) ); ?>> <?php echo esc_html__( 'Use break', 'cas' ); ?></label></td><td><input type="time" name="break_start[<?php echo esc_attr( $day_number ); ?>]" value="<?php echo esc_attr( $day_break['start'] ?? '' ); ?>"></td><td><input type="time" name="break_end[<?php echo esc_attr( $day_number ); ?>]" value="<?php echo esc_attr( $day_break['end'] ?? '' ); ?>"></td></tr>
							<?php endforeach; ?></tbody>
						</table>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Holiday Dates', 'cas' ); ?></th>
					<td>
						<div id="cas-holiday-list">
							<?php if ( empty( $holidays ) ) : ?>
								<p><label><?php echo esc_html__( 'Holiday Date', 'cas' ); ?> <input type="date" name="holidays[]" value=""></label></p>
							<?php else : ?>
								<?php foreach ( $holidays as $index => $holiday ) : ?>
									<p><label><?php echo esc_html__( 'Holiday Date', 'cas' ); ?> <input type="date" name="holidays[]" value="<?php echo esc_attr( $holiday ); ?>"></label></p>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="button cas-add-holiday"><?php echo esc_html__( 'Add Holiday Date', 'cas' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Manual Serial Selection', 'cas' ); ?></th>
					<td><label for="cas-allow-manual-pick"><input type="checkbox" id="cas-allow-manual-pick" name="allow_manual_pick" value="1" <?php checked( $schedule ? $schedule->allow_manual_pick : 1, 1 ); ?>> <?php echo esc_html__( 'Allow patients/admin to manually pick serial numbers', 'cas' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Schedule Status', 'cas' ); ?></th>
					<td><label for="cas-schedule-active"><input type="checkbox" id="cas-schedule-active" name="is_active" value="1" <?php checked( $schedule ? $schedule->is_active : 1, 1 ); ?>> <?php echo esc_html__( 'Schedule is active', 'cas' ); ?></label></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Schedule', 'cas' ) ); ?>
		</form>
	<?php endif; ?>
</div>
