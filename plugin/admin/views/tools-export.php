<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$doctors = method_exists( $this, 'get_admin_doctors' ) ? $this->get_admin_doctors( true ) : array();
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Secure Data Tools', 'cas' ); ?></h1>
	<p class="description"><?php echo esc_html__( 'Exports contain protected patient information. Download only to an authorized device, encrypt backups, and delete local copies when no longer needed.', 'cas' ); ?></p>
	<div class="cas-card" style="max-width:860px;margin:18px 0;padding:20px;">
		<h2><?php echo esc_html__( 'Export', 'cas' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cas_export_data"><?php wp_nonce_field( 'cas_export_data' ); ?>
			<p><label><strong><?php echo esc_html__( 'Data set', 'cas' ); ?></strong><br><select name="dataset"><option value="appointments"><?php echo esc_html__( 'Appointments', 'cas' ); ?></option><option value="patients"><?php echo esc_html__( 'Patient records', 'cas' ); ?></option></select></label></p>
			<p><label><strong><?php echo esc_html__( 'Format', 'cas' ); ?></strong><br><select name="format"><option value="csv"><?php echo esc_html__( 'CSV spreadsheet', 'cas' ); ?></option><option value="json"><?php echo esc_html__( 'JSON complete backup (patients + appointments)', 'cas' ); ?></option></select></label></p>
			<p><label><strong><?php echo esc_html__( 'Doctor scope', 'cas' ); ?></strong><br><select name="doctor_id"><option value="0"><?php echo esc_html__( 'All permitted doctors', 'cas' ); ?></option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->id ); ?>"><?php echo esc_html( $doctor->name ); ?></option><?php endforeach; ?></select></label></p>
			<?php submit_button( __( 'Download Protected Export', 'cas' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<div class="cas-card" style="max-width:860px;margin:18px 0;padding:20px;">
		<h2><?php echo esc_html__( 'Restore / Import', 'cas' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'JSON restores the full backup. CSV imports one data set at a time. Existing patients are matched by mobile number; appointments with unavailable doctors, invalid rows, or occupied serials are skipped rather than overwritten.', 'cas' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cas_import_data"><?php wp_nonce_field( 'cas_import_data' ); ?>
			<p><label><strong><?php echo esc_html__( 'CSV data set (ignored for JSON)', 'cas' ); ?></strong><br><select name="csv_type"><option value="patients"><?php echo esc_html__( 'Patient records CSV', 'cas' ); ?></option><option value="appointments"><?php echo esc_html__( 'Appointments CSV', 'cas' ); ?></option></select></label></p>
			<p><label><strong><?php echo esc_html__( 'Backup file', 'cas' ); ?></strong><br><input type="file" name="cas_import_file" accept=".csv,.json,text/csv,application/json" required></label></p>
			<p><label><input type="checkbox" name="confirm_privacy" value="1" required> <?php echo esc_html__( 'I am authorized to restore this protected patient data and understand that imports do not send SMS messages.', 'cas' ); ?></label></p>
			<?php submit_button( __( 'Validate and Import', 'cas' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
</div>
