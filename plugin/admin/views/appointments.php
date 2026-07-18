<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$table = new CAS_Appointments_List_Table();
$table->prepare_items();
$doctors = $this->get_admin_doctors( true );
$date = isset( $_GET['cas_date'] ) ? CAS_Admin::sanitize_date( wp_unslash( $_GET['cas_date'] ) ) : '';
$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$active_tab = isset( $_GET['tab'] ) && 'print' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'print' : 'list';
$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
$list_filters = CAS_Admin::get_appointment_list_filters();
$return_query = CAS_Admin::appointment_return_query_args( $list_filters );
$add_appointment_url = add_query_arg( array_merge( array( 'page' => 'cas-add-appointment' ), $return_query ), admin_url( 'admin.php' ) );
$csv_url = wp_nonce_url( add_query_arg( array( 'action' => 'cas_export_csv', 'type' => 'appointments', 'cas_date' => $date, 'doctor_id' => $doctor_id, 'status' => $status, 's' => $search ), admin_url( 'admin-post.php' ) ), 'cas_export_csv' );
$filtered_print_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'    => 'cas_print_appointments',
			'cas_date'  => $date,
			'doctor_id' => $doctor_id,
			'status'    => $status,
			'title'     => rawurlencode( __( 'Filtered Appointment List', 'cas' ) ),
		),
		admin_url( 'admin-post.php' )
	),
	'cas_print_appointments'
);
$list_tab_url  = add_query_arg( array( 'page' => 'cas-appointments', 'tab' => 'list' ), admin_url( 'admin.php' ) );
$print_tab_url = add_query_arg( array( 'page' => 'cas-appointments', 'tab' => 'print' ), admin_url( 'admin.php' ) );
?>
<div class="wrap cas-admin-wrap<?php echo $date ? ' cas-daily-worklist' : ''; ?>">
	<h1><?php echo esc_html__( 'Appointments', 'cas' ); ?></h1>
	<nav class="nav-tab-wrapper cas-admin-tabs" aria-label="<?php echo esc_attr__( 'Appointment sections', 'cas' ); ?>">
		<a class="nav-tab <?php echo 'list' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $list_tab_url ); ?>"><?php echo esc_html__( 'All Appointments', 'cas' ); ?></a>
		<a class="nav-tab <?php echo 'print' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $print_tab_url ); ?>"><?php echo esc_html__( 'Print Patient List', 'cas' ); ?></a>
	</nav>

	<?php if ( 'print' === $active_tab ) : ?>
		<div class="cas-card cas-tab-panel">
			<h2><?php echo esc_html__( 'Print Confirmed / Reconfirmed Patients List', 'cas' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Select date, doctor, and status before printing. Use today’s date by default or choose another date.', 'cas' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-filter-bar cas-reconfirmed-print-form" target="_blank">
				<input type="hidden" name="action" value="cas_print_appointments">
				<input type="hidden" name="title" value="<?php echo esc_attr__( 'Reconfirmed Patients List', 'cas' ); ?>">
				<input type="hidden" name="auto_print" value="1">
				<?php wp_nonce_field( 'cas_print_appointments' ); ?>
				<label for="cas-reconfirmed-print-date" class="cas-inline-label"><?php echo esc_html__( 'Date', 'cas' ); ?></label>
				<input id="cas-reconfirmed-print-date" type="date" name="cas_date" value="<?php echo esc_attr( $date ? $date : $today ); ?>" required>
				<label for="cas-reconfirmed-print-doctor" class="cas-inline-label"><?php echo esc_html__( 'Doctor', 'cas' ); ?></label>
				<select id="cas-reconfirmed-print-doctor" name="doctor_id">
					<option value="0"><?php echo esc_html__( 'All Doctors', 'cas' ); ?></option>
					<?php foreach ( $doctors as $doctor ) : ?>
						<option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $doctor_id, $doctor->id ); ?>><?php echo esc_html( $doctor->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="cas-reconfirmed-print-status" class="cas-inline-label"><?php echo esc_html__( 'Status', 'cas' ); ?></label>
				<select id="cas-reconfirmed-print-status" name="status">
					<option value="confirmed"><?php echo esc_html__( 'Confirmed', 'cas' ); ?></option>
					<option value="reconfirmed" selected><?php echo esc_html__( 'Reconfirmed', 'cas' ); ?></option>
				</select>
				<button class="button button-primary"><?php echo esc_html__( 'Print / Save PDF', 'cas' ); ?></button>
			</form>
		</div>
	<?php else : ?>
		<p><a class="button button-primary" href="<?php echo esc_url( $add_appointment_url ); ?>"><?php echo esc_html__( 'Add New Appointment', 'cas' ); ?></a></p>
		<?php if ( $date ) : ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Daily worklist for %s. Reconfirm, edit, cancel, and return to this same filtered list.', 'cas' ), $date ) ); ?></p>
			<p class="description cas-daily-worklist-note"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php echo esc_html__( 'All patient details are kept open below so you can call and reconfirm patients one by one.', 'cas' ); ?></p>
		<?php else : ?>
			<p class="description"><?php echo esc_html__( 'Use the filters to find appointments, then update individual rows or apply a bulk status.', 'cas' ); ?></p>
		<?php endif; ?>

		<form method="get" class="cas-filter-bar cas-appointment-filter-bar">
			<input type="hidden" name="page" value="cas-appointments">
			<input type="hidden" name="tab" value="list">
			<label for="cas-filter-date" class="cas-inline-label"><?php echo esc_html__( 'Date', 'cas' ); ?></label><input id="cas-filter-date" type="date" name="cas_date" value="<?php echo esc_attr( $date ); ?>">
			<label for="cas-filter-doctor" class="cas-inline-label"><?php echo esc_html__( 'Doctor', 'cas' ); ?></label><select id="cas-filter-doctor" name="doctor_id"><option value="0"><?php echo esc_html__( 'All Doctors', 'cas' ); ?></option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->id ); ?>" <?php selected( $doctor_id, $doctor->id ); ?>><?php echo esc_html( $doctor->name ); ?></option><?php endforeach; ?></select>
			<label for="cas-filter-status" class="cas-inline-label"><?php echo esc_html__( 'Status', 'cas' ); ?></label><select id="cas-filter-status" name="status"><option value=""><?php echo esc_html__( 'All Statuses', 'cas' ); ?></option><?php foreach ( CAS_Appointment::$statuses as $item_status ) : ?><option value="<?php echo esc_attr( $item_status ); ?>" <?php selected( $status, $item_status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $item_status ) ) ); ?></option><?php endforeach; ?></select>
			<label for="cas-filter-search" class="cas-inline-label"><?php echo esc_html__( 'Search', 'cas' ); ?></label><input id="cas-filter-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Patient, mobile, doctor', 'cas' ); ?>">
			<button class="button button-primary"><?php echo esc_html__( 'Filter', 'cas' ); ?></button><a class="button" href="<?php echo esc_url( $list_tab_url ); ?>"><?php echo esc_html__( 'Reset', 'cas' ); ?></a><a class="button" href="<?php echo esc_url( $csv_url ); ?>"><?php echo esc_html__( 'CSV Export', 'cas' ); ?></a><?php if ( $date ) : ?><a class="button" target="_blank" href="<?php echo esc_url( $filtered_print_url ); ?>"><?php echo esc_html__( 'Print Filtered List', 'cas' ); ?></a><?php endif; ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cas_bulk_appointment_status"><?php wp_nonce_field( 'cas_bulk_appointment_status' ); ?>
			<?php foreach ( $return_query as $return_key => $return_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $return_key ); ?>" value="<?php echo esc_attr( $return_value ); ?>">
			<?php endforeach; ?>
			<div class="cas-bulk-bar"><label for="cas-bulk-status" class="cas-inline-label"><?php echo esc_html__( 'Bulk Status', 'cas' ); ?></label><select id="cas-bulk-status" name="bulk_status"><option value=""><?php echo esc_html__( 'Choose status', 'cas' ); ?></option><?php foreach ( CAS_Appointment::$statuses as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?></option><?php endforeach; ?></select><button class="button"><?php echo esc_html__( 'Apply', 'cas' ); ?></button></div>
			<?php $table->display(); ?>
		</form>
	<?php endif; ?>
</div>
