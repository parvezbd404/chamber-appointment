<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
CAS_DB::ensure_message_schema();
$messages = CAS_DB::table( 'messages' );
$patients  = CAS_DB::table( 'patients' );
$selected_patient = isset( $_GET['patient_id'] ) ? absint( $_GET['patient_id'] ) : 0;

if ( ! $selected_patient ) {
	$selected_patient = absint( $wpdb->get_var( "SELECT patient_id FROM {$messages} ORDER BY created_at DESC LIMIT 1" ) );
}

$all_patients = $wpdb->get_results( "SELECT id,full_name,mobile,gender,city FROM {$patients} WHERE is_active=1 ORDER BY full_name ASC,mobile ASC LIMIT 1000" );
$threads = $wpdb->get_results( "SELECT m.patient_id,p.full_name,p.mobile,MAX(m.created_at) last_message,SUM(CASE WHEN m.direction='patient_to_chamber' AND m.is_read=0 THEN 1 ELSE 0 END) unread_count FROM {$messages} m LEFT JOIN {$patients} p ON p.id=m.patient_id GROUP BY m.patient_id ORDER BY last_message DESC LIMIT 200" );
$thread_messages = $selected_patient ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$messages} WHERE patient_id=%d ORDER BY created_at ASC", $selected_patient ) ) : array();

if ( $selected_patient ) {
	$wpdb->update( $messages, array( 'is_read' => 1 ), array( 'patient_id' => $selected_patient, 'direction' => 'patient_to_chamber' ), array( '%d' ), array( '%d', '%s' ) );
}

$patient = $selected_patient ? CAS_Patient::get_by_id( $selected_patient ) : null;

if ( ! function_exists( 'cas_admin_render_message_attachment' ) ) {
	function cas_admin_render_message_attachment( $message_row ) {
		$attachment = CAS_DB::message_attachment_for_display( $message_row );
		if ( empty( $attachment ) || empty( $attachment['url'] ) ) {
			return;
		}
		$name = ! empty( $attachment['name'] ) ? $attachment['name'] : __( 'Attachment', 'cas' );
		if ( ! empty( $attachment['is_image'] ) ) : ?>
			<a class="cas-message-attachment cas-message-attachment-image" href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" rel="noopener">
				<img src="<?php echo esc_url( $attachment['url'] ); ?>" alt="<?php echo esc_attr( $name ); ?>">
				<span><?php echo esc_html( $name ); ?></span>
			</a>
		<?php else : ?>
			<a class="cas-message-attachment cas-message-attachment-file" href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" rel="noopener">📎 <?php echo esc_html( $name ); ?></a>
		<?php endif;
	}
}
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'Message Center', 'cas' ); ?></h1>
	<p><?php echo esc_html__( 'Send messages to patients, view patient messages, reply from the chamber, and share photo/file attachments.', 'cas' ); ?></p>

	<div class="cas-card cas-new-admin-message-card">
		<h2><?php echo esc_html__( 'Send New Message to Patient', 'cas' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Use this form to start a new message thread or send a new chamber message to any active patient.', 'cas' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="cas-labelled-form cas-admin-start-message-form">
			<input type="hidden" name="action" value="cas_reply_message">
			<?php wp_nonce_field( 'cas_reply_message' ); ?>
			<div class="cas-form-grid">
				<p>
					<label for="cas-admin-new-message-patient"><?php echo esc_html__( 'Select Patient', 'cas' ); ?> <span class="cas-required">*</span></label>
					<select id="cas-admin-new-message-patient" name="patient_id" required>
						<option value=""><?php echo esc_html__( 'Select patient...', 'cas' ); ?></option>
						<?php foreach ( $all_patients as $p ) : ?>
							<option value="<?php echo esc_attr( absint( $p->id ) ); ?>" <?php selected( $selected_patient, absint( $p->id ) ); ?>>
								<?php echo esc_html( sprintf( '#%1$d — %2$s — %3$s%4$s', absint( $p->id ), $p->full_name, $p->mobile, $p->city ? ' — ' . $p->city : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="cas-form-wide">
					<label for="cas-admin-new-message-text"><?php echo esc_html__( 'Message', 'cas' ); ?></label>
					<textarea id="cas-admin-new-message-text" name="message" rows="3" class="large-text" placeholder="<?php echo esc_attr__( 'Write message to patient...', 'cas' ); ?>"></textarea>
				</p>
				<p class="cas-form-wide">
					<label for="cas-admin-new-message-attachment"><?php echo esc_html__( 'Attach photo or file', 'cas' ); ?></label>
					<input id="cas-admin-new-message-attachment" type="file" name="attachment" accept="image/*,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
					<span class="description"><?php echo esc_html__( 'Allowed: image, HEIC, PDF, Word, Excel, text. Maximum 5 MB.', 'cas' ); ?></span>
				</p>
			</div>
			<?php submit_button( __( 'Send Message to Patient', 'cas' ), 'primary', 'submit', false ); ?>
		</form>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-cleanup-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all message attachment files older than 1 month? Message text will remain.', 'cas' ) ); ?>');">
		<input type="hidden" name="action" value="cas_clear_old_message_attachments">
		<?php wp_nonce_field( 'cas_clear_old_message_attachments' ); ?>
		<?php submit_button( __( 'Clear Attachments Older Than 1 Month', 'cas' ), 'secondary', 'submit', false ); ?>
		<span class="description"><?php echo esc_html__( 'This deletes old uploaded files only. Recent files and message text remain.', 'cas' ); ?></span>
	</form>

	<div class="cas-message-center-grid">
		<div class="cas-card cas-thread-list-card">
			<h2><?php echo esc_html__( 'Patient Threads', 'cas' ); ?></h2>
			<?php if ( empty( $threads ) ) : ?>
				<p><?php echo esc_html__( 'No message threads found yet. Use Send New Message to Patient above, or wait for a patient to send a message from the Patient Messages page.', 'cas' ); ?></p>
			<?php else : ?>
				<ul class="cas-admin-thread-list">
					<?php foreach ( $threads as $thread ) : ?>
						<li class="<?php echo $selected_patient === absint( $thread->patient_id ) ? 'is-active' : ''; ?>">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cas-message-center&patient_id=' . absint( $thread->patient_id ) ) ); ?>">
								<strong><?php echo esc_html( $thread->full_name ?: __( 'Unknown Patient', 'cas' ) ); ?></strong>
								<span><?php echo esc_html( $thread->mobile ); ?></span>
								<?php if ( absint( $thread->unread_count ) ) : ?><em><?php echo esc_html( absint( $thread->unread_count ) ); ?></em><?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="cas-card cas-admin-chat-card">
			<h2><?php echo $patient ? esc_html( $patient->full_name . ' — ' . $patient->mobile ) : esc_html__( 'Conversation', 'cas' ); ?></h2>
			<?php if ( ! $selected_patient ) : ?>
				<p><?php echo esc_html__( 'Select a message thread or send a new message to a patient.', 'cas' ); ?></p>
			<?php else : ?>
				<div class="cas-admin-message-thread">
					<?php if ( empty( $thread_messages ) ) : ?>
						<p><?php echo esc_html__( 'No messages in this conversation yet.', 'cas' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $thread_messages as $m ) : ?>
						<div class="cas-admin-message cas-admin-message-<?php echo esc_attr( $m->direction ); ?>">
							<div class="cas-admin-message-bubble">
								<?php if ( trim( (string) $m->message ) !== '' ) : ?><p><?php echo esc_html( $m->message ); ?></p><?php endif; ?>
								<?php cas_admin_render_message_attachment( $m ); ?>
								<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $m->created_at ) ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="cas-reply-form">
					<input type="hidden" name="action" value="cas_reply_message">
					<input type="hidden" name="patient_id" value="<?php echo esc_attr( $selected_patient ); ?>">
					<?php wp_nonce_field( 'cas_reply_message' ); ?>
					<label for="cas-admin-reply-message"><strong><?php echo esc_html__( 'Reply Message', 'cas' ); ?></strong></label>
					<textarea id="cas-admin-reply-message" name="message" rows="3" class="large-text" placeholder="<?php echo esc_attr__( 'Write reply to patient...', 'cas' ); ?>"></textarea>
					<label for="cas-admin-reply-attachment" class="cas-message-file-label"><strong><?php echo esc_html__( 'Attach photo or file', 'cas' ); ?></strong></label>
					<input id="cas-admin-reply-attachment" type="file" name="attachment" accept="image/*,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
					<p class="description"><?php echo esc_html__( 'Allowed: image, HEIC, PDF, Word, Excel, text. Maximum 5 MB.', 'cas' ); ?></p>
					<?php submit_button( __( 'Send Reply', 'cas' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
