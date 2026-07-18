<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$doctors = $this->get_admin_doctors( true );
$date = CAS_Admin::sanitize_date( $_GET['cas_date'] ?? gmdate( 'Y-m-d', current_time( 'timestamp' ) ) );
$doctor = absint( $_GET['doctor_id'] ?? ( isset( $doctors[0] ) ? $doctors[0]->id : 0 ) );
if ( $doctor && ! CAS_Admin::user_can_access_doctor( $doctor ) ) {
	$doctor = isset( $doctors[0] ) ? absint( $doctors[0]->id ) : 0;
}
$rows = $doctor ? CAS_Waiting_List::get_by_date_doctor( $date, $doctor, 'waiting' ) : array();
$development_mode = CAS_OTP::is_development_mode();
// Chamber staff may manually add a patient in both application modes.
// Development Mode suppresses outbound SMS; Live Mode follows the configured notification rules.
$patients = $this->get_active_patients( 500 );
$doctor_names = wp_list_pluck( $doctors, 'name', 'id' );
$selected_doctor_name = isset( $doctor_names[ $doctor ] ) ? $doctor_names[ $doctor ] : '';
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Waiting List', 'cas' ); ?></h1>
	<p class="description"><?php echo esc_html__( 'Use the appointment list icon to view all slots for the selected doctor/date. Booked slots are red; free slots can be selected for promotion.', 'cas' ); ?></p>

	<form method="get" class="cas-filter-bar">
		<input type="hidden" name="page" value="cas-waiting-list">
		<input type="date" name="cas_date" value="<?php echo esc_attr( $date ); ?>" data-cas-admin-date>
		<select name="doctor_id" data-cas-admin-doctor>
			<?php foreach ( $doctors as $d ) : ?>
				<option value="<?php echo esc_attr( $d->id ); ?>" <?php selected( $doctor, $d->id ); ?>><?php echo esc_html( $d->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button"><?php echo esc_html__( 'Filter', 'cas' ); ?></button>
		<button type="button" class="button button-primary" id="cas-show-add-waiting"><?php echo esc_html__( 'Add to Waiting List', 'cas' ); ?></button>
	</form>

	<div id="cas-admin-add-waiting-panel" class="card" style="display:none;max-width:900px;margin:12px 0;padding:18px;">
			<h2 style="margin-top:0;"><?php echo esc_html__( 'Add Patient to Waiting List', 'cas' ); ?></h2>
			<?php if ( $development_mode ) : ?>
				<p class="description"><?php echo esc_html__( 'Development Mode: chamber staff can add a patient even when regular slots are available. No real waiting-list SMS is sent.', 'cas' ); ?></p>
			<?php else : ?>
				<p class="description"><?php echo esc_html__( 'Live Mode: chamber staff can manually add a patient even when regular slots are available. Configured waiting-list notifications will be sent.', 'cas' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cas_admin_add_waiting">
				<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor ); ?>">
				<input type="hidden" name="appointment_date" value="<?php echo esc_attr( $date ); ?>">
				<?php wp_nonce_field( 'cas_admin_add_waiting' ); ?>
				<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="cas-waiting-patient-id"><?php echo esc_html__( 'Patient', 'cas' ); ?></label></th>
					<td><select id="cas-waiting-patient-id" name="patient_id" required style="min-width:360px;max-width:100%;">
						<option value=""><?php echo esc_html__( 'Select an active patient', 'cas' ); ?></option>
						<?php foreach ( $patients as $patient ) : ?>
							<option value="<?php echo esc_attr( $patient->id ); ?>"><?php echo esc_html( $patient->full_name . ' — ' . $patient->mobile ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr><th scope="row"><?php echo esc_html__( 'Doctor / Date', 'cas' ); ?></th><td><strong><?php echo esc_html( $selected_doctor_name ); ?></strong> — <?php echo esc_html( $date ); ?></td></tr>
				</tbody></table>
				<p><button class="button button-primary"><?php echo esc_html__( 'Add Patient to Waiting List', 'cas' ); ?></button> <button type="button" class="button" id="cas-hide-add-waiting"><?php echo esc_html__( 'Cancel', 'cas' ); ?></button></p>
			</form>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var panel = document.getElementById('cas-admin-add-waiting-panel');
			var show = document.getElementById('cas-show-add-waiting');
			var hide = document.getElementById('cas-hide-add-waiting');
			if (show && panel) show.addEventListener('click', function () { panel.style.display = 'block'; var field = document.getElementById('cas-waiting-patient-id'); if (field) field.focus(); });
			if (hide && panel) hide.addEventListener('click', function () { panel.style.display = 'none'; });
		});
		</script>

	<table class="widefat striped">
		<thead><tr><th><?php echo esc_html__( 'Queue', 'cas' ); ?></th><th><?php echo esc_html__( 'Patient', 'cas' ); ?></th><th><?php echo esc_html__( 'Mobile', 'cas' ); ?></th><th><?php echo esc_html__( 'Action', 'cas' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr><td><?php echo esc_html( $r->queue_number ); ?></td><td><?php echo esc_html( $r->patient_name ); ?></td><td><?php echo esc_html( $r->patient_mobile ); ?></td><td>
				<form class="cas-inline-form cas-waiting-promote-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cas-waiting-promote-form>
					<input type="hidden" name="action" value="cas_promote_waiting"><input type="hidden" name="waiting_id" value="<?php echo esc_attr( $r->id ); ?>"><input type="hidden" name="doctor_id" value="<?php echo esc_attr( $r->doctor_id ); ?>" data-cas-admin-doctor><input type="hidden" name="appointment_date" value="<?php echo esc_attr( $r->appointment_date ); ?>" data-cas-admin-date><?php wp_nonce_field( 'cas_promote_waiting' ); ?>
					<input type="number" name="serial_number" class="small-text" placeholder="Serial" data-cas-admin-serial required min="1">
					<button type="button" class="button cas-open-slot-picker" title="<?php echo esc_attr__( 'Show appointment slots', 'cas' ); ?>">📋</button>
					<button class="button button-primary"><?php echo esc_html__( 'Promote', 'cas' ); ?></button>
				</form>
			</td></tr>
		<?php endforeach; ?>
		<?php if ( empty( $rows ) ) : ?><tr><td colspan="4"><?php echo esc_html__( 'No waiting list patients found for this date and doctor.', 'cas' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
	<div class="cas-slot-modal" data-cas-slot-modal hidden><div class="cas-slot-modal-inner"><button type="button" class="button-link cas-slot-modal-close" data-cas-slot-close>×</button><h2><?php echo esc_html__( 'Appointment Serial Slots', 'cas' ); ?></h2><p class="description"><?php echo esc_html__( 'Change the date if all slots are booked. Click a free slot to place the waiting-list patient there.', 'cas' ); ?></p><div class="cas-slot-toolbar"><label><?php echo esc_html__( 'Date', 'cas' ); ?> <input type="date" data-cas-slot-date></label><button type="button" class="button" data-cas-slot-refresh><?php echo esc_html__( 'Refresh Slots', 'cas' ); ?></button></div><div class="cas-slot-grid" data-cas-slot-grid></div></div></div>
</div>
