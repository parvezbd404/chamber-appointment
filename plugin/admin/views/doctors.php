<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $wpdb;
$edit_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
$doctor  = $edit_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CAS_DB::table( 'doctors' ) . ' WHERE id=%d', $edit_id ) ) : null;
$doctors = $this->get_admin_doctors( false );
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Doctors/Chambers', 'cas' ); ?></h1>
	<div class="cas-two-column">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form cas-labelled-form">
			<h2><?php echo $doctor ? esc_html__( 'Edit Doctor', 'cas' ) : esc_html__( 'Add Doctor', 'cas' ); ?></h2>
			<input type="hidden" name="action" value="cas_save_doctor">
			<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $edit_id ); ?>">
			<?php wp_nonce_field( 'cas_save_doctor' ); ?>
			<p><label for="cas-doctor-name"><?php echo esc_html__( 'Doctor Name', 'cas' ); ?></label><input id="cas-doctor-name" name="name" class="widefat" required value="<?php echo esc_attr( $doctor ? $doctor->name : '' ); ?>"></p>
			<p><label for="cas-specialty"><?php echo esc_html__( 'Specialty', 'cas' ); ?></label><input id="cas-specialty" name="specialty" class="widefat" value="<?php echo esc_attr( $doctor ? $doctor->specialty : '' ); ?>"></p>
			<p><label for="cas-doctor-mobile"><?php echo esc_html__( 'Mobile', 'cas' ); ?></label><input id="cas-doctor-mobile" name="mobile" class="widefat" placeholder="01XXXXXXXXX" value="<?php echo esc_attr( $doctor ? $doctor->mobile : '' ); ?>"></p>
			<p><label for="cas-doctor-email"><?php echo esc_html__( 'Email', 'cas' ); ?></label><input id="cas-doctor-email" type="email" name="email" class="widefat" value="<?php echo esc_attr( $doctor ? $doctor->email : '' ); ?>"></p>
			<p><label for="cas-bio"><?php echo esc_html__( 'Bio', 'cas' ); ?></label><textarea id="cas-bio" name="bio" class="widefat" rows="4"><?php echo esc_textarea( $doctor ? $doctor->bio : '' ); ?></textarea></p>
			<p><label><input type="checkbox" name="is_active" value="1" <?php checked( $doctor ? $doctor->is_active : 1, 1 ); ?>> <?php echo esc_html__( 'Active doctor', 'cas' ); ?></label></p>
			<?php submit_button( $doctor ? __( 'Update Doctor', 'cas' ) : __( 'Save Doctor', 'cas' ) ); ?>
			<?php if ( $doctor ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cas-doctors' ) ); ?>"><?php echo esc_html__( 'Cancel Edit', 'cas' ); ?></a><?php endif; ?>
		</form>
		<div class="cas-card">
			<h2><?php echo esc_html__( 'Doctor List', 'cas' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php echo esc_html__( 'Name', 'cas' ); ?></th><th><?php echo esc_html__( 'Specialty', 'cas' ); ?></th><th><?php echo esc_html__( 'Mobile', 'cas' ); ?></th><th><?php echo esc_html__( 'Status', 'cas' ); ?></th><th><?php echo esc_html__( 'Actions', 'cas' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $doctors as $row ) : ?>
				<tr class="<?php echo $row->is_active ? '' : 'cas-inactive-record'; ?>"><td><strong><?php echo esc_html( $row->name ); ?></strong></td><td><?php echo esc_html( $row->specialty ); ?></td><td><?php echo esc_html( $row->mobile ); ?></td><td><span class="<?php echo $row->is_active ? 'cas-status-active' : 'cas-status-inactive'; ?>"><?php echo $row->is_active ? esc_html__( 'Active', 'cas' ) : esc_html__( 'Inactive', 'cas' ); ?></span></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=cas-doctors&doctor_id=' . absint( $row->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'cas' ); ?></a> | <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'cas_deactivate_doctor', 'doctor_id' => absint( $row->id ) ), admin_url( 'admin-post.php' ) ), 'cas_deactivate_doctor_' . absint( $row->id ) ) ); ?>"><?php echo esc_html__( 'Deactivate', 'cas' ); ?></a> | <a class="cas-danger-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'cas_delete_doctor', 'doctor_id' => absint( $row->id ) ), admin_url( 'admin-post.php' ) ), 'cas_delete_doctor_' . absint( $row->id ) ) ); ?>"><?php echo esc_html__( 'Delete', 'cas' ); ?></a></td></tr>
				<?php endforeach; ?>
				<?php if ( empty( $doctors ) ) : ?><tr><td colspan="5"><?php echo esc_html__( 'No doctors found.', 'cas' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
