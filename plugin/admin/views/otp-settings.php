<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s = CAS_DB::get_settings();
?>
<div class="wrap cas-admin-wrap">
	<h1><?php echo esc_html__( 'OTP Settings', 'cas' ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cas-card cas-form">
		<input type="hidden" name="action" value="cas_save_otp_settings">
		<?php wp_nonce_field( 'cas_save_otp_settings' ); ?>

		<h2><?php echo esc_html__( 'Application Mode', 'cas' ); ?></h2>
		<p>
			<label style="display:block;margin-bottom:8px">
				<input type="radio" name="application_mode" value="live" <?php checked( ( $s['application_mode'] ?? 'live' ), 'live' ); ?>>
				<strong><?php echo esc_html__( 'Live App Mode', 'cas' ); ?></strong> — <?php echo esc_html__( 'Uses the configured SMS provider and optional registered-email OTP copy.', 'cas' ); ?>
			</label>
			<label style="display:block">
				<input type="radio" name="application_mode" value="development" <?php checked( ( $s['application_mode'] ?? 'live' ), 'development' ); ?>>
				<strong><?php echo esc_html__( 'Development Mode', 'cas' ); ?></strong> — <?php echo esc_html__( 'Never calls the real SMS API or sends OTP email. The test OTP is shown beside the web/app OTP screen.', 'cas' ); ?>
			</label>
		</p>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html__( 'Security warning:', 'cas' ); ?></strong> <?php echo esc_html__( 'Development Mode exposes OTPs to the login screen. Turn Live App Mode back on before allowing real patients to use the portal.', 'cas' ); ?></p></div>

		<h2><?php echo esc_html__( 'Mobile OTP Rules', 'cas' ); ?></h2>
		<p>
			<label for="cas-otp-digits"><strong><?php echo esc_html__( 'OTP Number of Digits', 'cas' ); ?></strong></label><br>
			<input id="cas-otp-digits" type="number" name="otp_digits" min="4" max="8" step="1" value="<?php echo esc_attr( absint( $s['otp_digits'] ?? 6 ) ); ?>">
			<span class="description"><?php echo esc_html__( 'Choose 4 to 8 digits. Six digits is recommended for normal production use.', 'cas' ); ?></span>
		</p>
		<?php
		$otp_rule_labels = array(
			'otp_expiry_minutes'          => __( 'OTP Expiry (minutes)', 'cas' ),
			'otp_resend_cooldown_seconds' => __( 'Resend Cooldown (seconds)', 'cas' ),
			'otp_max_attempts'            => __( 'Maximum Verification Attempts', 'cas' ),
			'otp_lockout_minutes'         => __( 'Lockout Duration (minutes)', 'cas' ),
			'otp_ip_hourly_limit'         => __( 'Maximum OTP Requests per IP per Hour', 'cas' ),
		);
		foreach ( $otp_rule_labels as $k => $label ) : ?>
			<p>
				<label>
					<?php echo esc_html( $label ); ?>
					<input type="number" min="1" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $s[ $k ] ); ?>">
				</label>
			</p>
		<?php endforeach; ?>

		<h2><?php echo esc_html__( 'Email OTP Copy', 'cas' ); ?></h2>
		<p>
			<label>
				<input type="checkbox" name="email_otp_enabled" value="1" <?php checked( ! empty( $s['email_otp_enabled'] ) ); ?>>
				<?php echo esc_html__( 'Send a copy of the OTP to the registered patient email address when available.', 'cas' ); ?>
			</label>
		</p>
		<p class="description">
			<?php echo esc_html__( 'This uses WordPress wp_mail(). Configure SMTP on the website for reliable email delivery. The email copy is sent only for already registered active patients who have an email address saved.', 'cas' ); ?>
		</p>

		<button class="button button-primary"><?php echo esc_html__( 'Save OTP Settings', 'cas' ); ?></button>
	</form>
</div>
