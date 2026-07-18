<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
$manual_url = admin_url( 'admin.php?page=cas-add-appointment' );
$list_url = add_query_arg( array( 'page' => 'cas-appointments', 'cas_date' => $today ), admin_url( 'admin.php' ) );
?>
<div class="wrap cas-admin-wrap cas-booking-desk">
	<h1><?php echo esc_html__( 'Booking Desk', 'cas' ); ?></h1>
	<p class="description"><?php echo esc_html__( 'Use this quick desk for manual bookings and same-day status actions. Cancellations free the serial immediately; No Show keeps the completed-day record.', 'cas' ); ?></p>
	<div class="cas-dashboard-grid">
		<div class="cas-card"><h2><?php echo esc_html__( 'Manual Booking', 'cas' ); ?></h2><p><?php echo esc_html__( 'Create a booking for a caller or walk-in patient. The serial selector loads the current free slots.', 'cas' ); ?></p><p><a class="button button-primary" href="<?php echo esc_url( $manual_url ); ?>"><?php echo esc_html__( 'Add Manual Booking', 'cas' ); ?></a></p></div>
		<div class="cas-card"><h2><?php echo esc_html__( 'Today\'s Appointments', 'cas' ); ?></h2><p><?php echo esc_html__( 'Open the daily list to Reconfirm, Cancel, Check In, Complete, or mark No Show from each appointment row.', 'cas' ); ?></p><p><a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Open Today\'s List', 'cas' ); ?></a></p></div>
		<div class="cas-card"><h2><?php echo esc_html__( 'Slot Safety', 'cas' ); ?></h2><p><?php echo esc_html__( 'Free/booked serial lists are backed by an active-slot database reservation. A staff booking cannot overwrite another active booking.', 'cas' ); ?></p></div>
	</div>
</div>
