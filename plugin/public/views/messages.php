<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$p        = $this->get_current_patient();
$messages = $p ? $this->get_patient_messages( $p->id ) : array();
?>
<div class="cas-public-wrap cas-messages-wrap" data-cas-component="messages">
	<div class="cas-card cas-chat-card">
		<div class="cas-chat-header">
			<div>
				<h2><?php echo esc_html__( 'Patient Messages', 'cas' ); ?></h2>
				<p class="cas-muted"><?php echo esc_html__( 'Send messages, photos, or files to the chamber and view replies like a chat.', 'cas' ); ?></p>
			</div>
			<button type="button" class="cas-button cas-button-secondary" data-cas-refresh-messages><?php echo esc_html__( 'Refresh', 'cas' ); ?></button>
		</div>
		<div class="cas-alert" data-cas-alert hidden></div>
		<div class="cas-spinner" data-cas-spinner hidden></div>
		<div class="cas-message-thread" data-cas-message-thread>
			<?php foreach ( $messages as $m ) : $attachment = CAS_DB::message_attachment_for_display( $m ); ?>
				<div class="cas-message cas-message-<?php echo esc_attr( $m->direction ); ?>">
					<div class="cas-message-bubble">
						<?php if ( trim( (string) $m->message ) !== '' ) : ?><p><?php echo esc_html( $m->message ); ?></p><?php endif; ?>
						<?php if ( $attachment ) : ?>
							<?php if ( ! empty( $attachment['is_image'] ) ) : ?>
								<a class="cas-message-attachment cas-message-attachment-image" href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo esc_url( $attachment['url'] ); ?>" alt="<?php echo esc_attr( $attachment['name'] ); ?>">
									<span><?php echo esc_html( $attachment['name'] ); ?></span>
								</a>
							<?php else : ?>
								<a class="cas-message-attachment cas-message-attachment-file" href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" rel="noopener">📎 <?php echo esc_html( $attachment['name'] ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $m->created_at ) ); ?></small>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<form data-cas-form="send-message" class="cas-chat-compose" enctype="multipart/form-data">
			<label for="cas-patient-message"><strong><?php echo esc_html__( 'Message', 'cas' ); ?></strong></label>
			<textarea id="cas-patient-message" name="message" rows="3" placeholder="<?php echo esc_attr__( 'Write your message...', 'cas' ); ?>"></textarea>
			<label for="cas-patient-attachment" class="cas-message-file-label"><strong><?php echo esc_html__( 'Attach photo or file', 'cas' ); ?></strong></label>
			<input id="cas-patient-attachment" type="file" name="attachment" accept="image/*,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
			<p class="cas-muted cas-file-help"><?php echo esc_html__( 'Allowed: image, HEIC, PDF, Word, Excel, text. Maximum 5 MB.', 'cas' ); ?></p>
			<button class="cas-button cas-button-primary"><?php echo esc_html__( 'Send Message', 'cas' ); ?></button>
		</form>
	</div>
</div>
