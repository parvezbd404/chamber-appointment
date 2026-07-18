<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s = CAS_DB::get_settings();
$templates = CAS_SMS::get_templates();
global $wpdb;
$logs = $wpdb->get_results( 'SELECT * FROM ' . CAS_DB::table( 'sms_logs' ) . ' ORDER BY created_at DESC LIMIT 50' );
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'SMS Settings', 'cas' ); ?></h1>
	<?php if ( 'development' === ( $s['application_mode'] ?? 'live' ) ) : ?>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html__( 'Development Mode is active.', 'cas' ); ?></strong> <?php echo esc_html__( 'No real SMS provider calls will be made until you switch to Live App Mode in OTP Settings.', 'cas' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form">
		<input type="hidden" name="action" value="cas_save_sms_settings">
		<?php wp_nonce_field( 'cas_save_sms_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="cas-sms-enabled"><?php echo esc_html__( 'Enable SMS', 'cas' ); ?></label></th><td><label><input id="cas-sms-enabled" type="checkbox" name="sms_enabled" value="1" <?php checked( $s['sms_enabled'], 1 ); ?>> <?php echo esc_html__( 'Enable SMS sending', 'cas' ); ?></label></td></tr>
			<tr><th><label for="cas-sms-api-url"><?php echo esc_html__( 'Single SMS API URL', 'cas' ); ?></label></th><td><input id="cas-sms-api-url" name="sms_api_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['sms_api_url'] ); ?>"><p class="description"><?php echo esc_html__( 'Default BulkSMSBD single SMS endpoint: http://bulksmsbd.net/api/smsapi', 'cas' ); ?></p></td></tr>
			<tr><th><label for="cas-sms-balance-url"><?php echo esc_html__( 'Balance Check API URL', 'cas' ); ?></label></th><td><input id="cas-sms-balance-url" name="sms_balance_url" type="url" class="regular-text" placeholder="http://bulksmsbd.net/api/getBalanceApi?api_key={api_key}" value="<?php echo esc_attr( isset( $s['sms_balance_url'] ) ? $s['sms_balance_url'] : '' ); ?>"><p class="description"><?php echo esc_html__( 'For BulkSMSBD use: http://bulksmsbd.net/api/getBalanceApi?api_key={api_key}. The plugin replaces {api_key} server-side. Full URL with real api_key is also supported, but placeholder is safer.', 'cas' ); ?></p><button type="button" class="button" data-cas-check-balance><?php echo esc_html__( 'Check Balance', 'cas' ); ?></button><span class="spinner" data-cas-balance-spinner></span><div class="cas-balance-result" data-cas-balance-result aria-live="polite"></div></td></tr>
			<tr><th><label for="cas-sms-api-key"><?php echo esc_html__( 'API Key', 'cas' ); ?></label></th><td><input id="cas-sms-api-key" type="password" name="sms_api_key" class="regular-text" value="" autocomplete="new-password"><p class="description"><?php echo esc_html__( 'Leave blank to keep existing key. The saved key is not printed in HTML.', 'cas' ); ?></p></td></tr>
			<tr><th><label for="cas-sms-senderid"><?php echo esc_html__( 'Sender ID', 'cas' ); ?></label></th><td><input id="cas-sms-senderid" name="sms_senderid" class="regular-text" value="<?php echo esc_attr( $s['sms_senderid'] ); ?>"></td></tr>
		</table>
		<h2><?php echo esc_html__( 'SMS Templates', 'cas' ); ?></h2>
		<?php foreach ( $templates as $key => $value ) : ?>
			<p><label for="cas-template-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></strong></label><textarea id="cas-template-<?php echo esc_attr( $key ); ?>" name="sms_templates[<?php echo esc_attr( $key ); ?>]" class="large-text" rows="3"><?php echo esc_textarea( $value ); ?></textarea></p>
		<?php endforeach; ?>
		<?php submit_button( __( 'Save SMS Settings', 'cas' ) ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form">
		<h2><?php echo esc_html__( 'Test SMS', 'cas' ); ?></h2>
		<input type="hidden" name="action" value="cas_test_sms">
		<?php wp_nonce_field( 'cas_test_sms' ); ?>
		<p><label for="cas-test-mobile"><?php echo esc_html__( 'Test Mobile', 'cas' ); ?></label><input id="cas-test-mobile" name="test_mobile" class="regular-text" placeholder="01XXXXXXXXX"></p>
		<p><label for="cas-test-message"><?php echo esc_html__( 'Test Message', 'cas' ); ?></label><textarea id="cas-test-message" name="test_message" class="large-text" rows="2"><?php echo esc_textarea( __( 'Test SMS', 'cas' ) ); ?></textarea></p>
		<?php submit_button( __( 'Send Test SMS', 'cas' ), 'secondary' ); ?>
	</form>
	<div class="cas-card"><h2><?php echo esc_html__( 'Recent SMS Logs', 'cas' ); ?></h2><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Time', 'cas' ); ?></th><th><?php echo esc_html__( 'Recipient', 'cas' ); ?></th><th><?php echo esc_html__( 'Code', 'cas' ); ?></th><th><?php echo esc_html__( 'Message', 'cas' ); ?></th></tr></thead><tbody><?php foreach ( $logs as $log ) : ?><tr><td><?php echo esc_html( $log->created_at ); ?></td><td><?php echo esc_html( $log->recipient ); ?></td><td><?php echo esc_html( $log->api_response_code ); ?></td><td><?php echo esc_html( wp_trim_words( $log->message, 12 ) ); ?></td></tr><?php endforeach; ?><?php if ( empty( $logs ) ) : ?><tr><td colspan="4"><?php echo esc_html__( 'No SMS logs found.', 'cas' ); ?></td></tr><?php endif; ?></tbody></table></div>
</div>
