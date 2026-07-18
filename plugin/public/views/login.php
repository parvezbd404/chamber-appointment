<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings = CAS_DB::get_settings();
$blood_groups = CAS_Patient::blood_group_options();
$email_otp_enabled = ! empty( $settings['email_otp_enabled'] );
$development_mode = 'development' === ( $settings['application_mode'] ?? 'live' );
$cas_otp_digits = max( 4, min( 8, absint( $settings['otp_digits'] ?? 6 ) ) );
?>
<div class="cas-public-wrap cas-login-wrap" data-cas-component="login" data-cas-otp-length="<?php echo esc_attr( $cas_otp_digits ); ?>">
	<div class="cas-card cas-login-card">
		<div class="cas-login-brand-mark" aria-hidden="true">♥</div>
		<span class="cas-eyebrow">Patient Care Portal</span>
		<h2><?php echo esc_html__( 'Patient Login', 'cas' ); ?></h2>
		<p class="cas-muted">
			<?php
			if ( $development_mode ) {
				echo esc_html__( 'Development Mode is active. No SMS or email OTP will be sent; the test OTP will appear on the next screen.', 'cas' );
			} elseif ( $email_otp_enabled ) {
				echo esc_html__( 'Enter your mobile number, receive OTP at your mobile, then verify to continue (If you are already registered, a copy of the OTP will also be sent to your email address).', 'cas' );
			} else {
				echo esc_html__( 'Enter your mobile number, receive OTP at your mobile, then verify to continue.', 'cas' );
			}
			?>
		</p>
		<div class="cas-step-indicator" aria-label="<?php echo esc_attr__( 'Login steps', 'cas' ); ?>">
			<span class="cas-step is-active" data-step-dot="mobile"><?php echo esc_html__( 'Mobile', 'cas' ); ?></span>
			<span class="cas-step" data-step-dot="otp"><?php echo esc_html__( 'OTP', 'cas' ); ?></span>
			<span class="cas-step" data-step-dot="profile"><?php echo esc_html__( 'Profile', 'cas' ); ?></span>
		</div>
		<div class="cas-alert" data-cas-alert hidden></div>
		<div class="cas-spinner" data-cas-spinner hidden></div>
		<form class="cas-login-step is-active" data-cas-form="send-otp" data-cas-login-step="mobile">
			<?php wp_nonce_field( CAS_Public::AJAX_NONCE_ACTION, 'nonce' ); ?>
			<label for="cas-login-mobile"><?php echo esc_html__( 'Mobile Number', 'cas' ); ?></label>
			<input type="tel" id="cas-login-mobile" name="mobile" required placeholder="01XXXXXXXXX" autocomplete="tel">
			<button type="submit" class="cas-button cas-button-primary cas-button-full" data-cas-send-otp><?php echo esc_html__( 'Send OTP', 'cas' ); ?></button>
		</form>
		<form class="cas-login-step" data-cas-form="verify-otp" data-cas-login-step="otp" hidden>
			<?php wp_nonce_field( CAS_Public::AJAX_NONCE_ACTION, 'nonce' ); ?>
			<input type="hidden" name="mobile" data-cas-otp-mobile value="">
			<p class="cas-muted" data-cas-otp-delivery-label><?php echo esc_html__( 'OTP has been sent to', 'cas' ); ?> <strong data-cas-mobile-label></strong><span data-cas-email-otp-label-wrap hidden> &amp; <?php echo esc_html__( 'Your Registered email', 'cas' ); ?> <strong data-cas-email-otp-label></strong></span>.</p>
			<div class="cas-development-otp" data-cas-development-otp hidden>
				<span><?php echo esc_html__( 'Development Mode test OTP:', 'cas' ); ?></span> <strong data-cas-development-otp-code></strong>
			</div>
			<label for="cas-login-otp"><?php echo esc_html__( 'Enter OTP', 'cas' ); ?></label>
			<input type="text" id="cas-login-otp" name="otp" required maxlength="<?php echo esc_attr( $cas_otp_digits ); ?>" inputmode="numeric" pattern="[0-9]{<?php echo esc_attr( $cas_otp_digits ); ?>}" autocomplete="one-time-code" placeholder="<?php echo esc_attr( str_repeat( '-', $cas_otp_digits ) ); ?>" aria-describedby="cas-otp-autofill-help">
			<p id="cas-otp-autofill-help" class="cas-muted cas-otp-autofill-help"><?php echo esc_html__( 'On supported phones, the code may be filled automatically from the SMS. You can also paste or enter it manually.', 'cas' ); ?></p>
			<div class="cas-login-actions">
				<button type="submit" class="cas-button cas-button-primary"><?php echo esc_html__( 'Verify OTP', 'cas' ); ?></button>
				<button type="button" class="cas-button cas-button-secondary" data-cas-resend-otp disabled><?php echo esc_html__( 'Resend OTP', 'cas' ); ?></button>
				<button type="button" class="cas-button cas-button-link" data-cas-back-step="mobile"><?php echo esc_html__( 'Change mobile number', 'cas' ); ?></button>
			</div>
			<p class="cas-muted cas-countdown-text" data-cas-countdown-text></p>
		</form>
		<form class="cas-login-step" data-cas-form="select-profile" data-cas-login-step="profile" hidden>
			<?php wp_nonce_field( CAS_Public::AJAX_NONCE_ACTION, 'nonce' ); ?>
			<div data-cas-existing-profile-section>
				<h3><?php echo esc_html__( 'Choose Patient Profile', 'cas' ); ?></h3>
				<p class="cas-muted"><?php echo esc_html__( 'Select an existing patient profile or create a new one.', 'cas' ); ?></p>
				<div class="cas-profile-list" data-cas-profile-list></div>
				<button type="button" class="cas-button cas-button-secondary" data-cas-show-new-profile><?php echo esc_html__( 'Create New Profile / New Patient', 'cas' ); ?></button>
			</div>
			<div class="cas-new-profile-form" data-cas-new-profile-form hidden>
				<h3><?php echo esc_html__( 'Provide Your Basic Information', 'cas' ); ?></h3>
				<p class="cas-muted"><?php echo esc_html__( 'Provide the information below to book an appointment. Other profile details can be added later.', 'cas' ); ?></p>
				<div class="cas-verified-mobile-summary"><span><?php echo esc_html__( 'Mobile Number', 'cas' ); ?></span><strong data-cas-verified-mobile-display></strong><em><?php echo esc_html__( 'Verified', 'cas' ); ?></em></div>
				<div class="cas-form-grid">
					<label><?php echo esc_html__( 'New Patient Name', 'cas' ); ?> <span class="cas-required">*</span><input type="text" name="full_name" placeholder="<?php echo esc_attr__( 'Full name', 'cas' ); ?>" required></label>
					<label><?php echo esc_html__( 'Age', 'cas' ); ?> <span class="cas-required">*</span><input type="number" name="age" min="0" max="125" required></label>
					<label><?php echo esc_html__( 'Gender', 'cas' ); ?> <span class="cas-required">*</span><select name="gender" required><option value=""><?php echo esc_html__( 'Select', 'cas' ); ?></option><option value="male"><?php echo esc_html__( 'Male', 'cas' ); ?></option><option value="female"><?php echo esc_html__( 'Female', 'cas' ); ?></option><option value="other"><?php echo esc_html__( 'Other', 'cas' ); ?></option></select></label>
				</div>
				<div class="cas-login-actions">
					<button type="submit" class="cas-button cas-button-primary"><?php echo esc_html__( 'Create Profile & Continue', 'cas' ); ?></button>
					<button type="button" class="cas-button cas-button-link" data-cas-hide-new-profile><?php echo esc_html__( 'Back to Profiles', 'cas' ); ?></button>
				</div>
			</div>
		</form>
		<p class="cas-muted cas-login-brand"><?php echo esc_html( sprintf( __( 'Secure login for %s patient portal.', 'cas' ), $settings['brand_name'] ) ); ?></p>
	</div>
</div>
