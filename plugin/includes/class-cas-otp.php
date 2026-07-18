<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_OTP {
	/** Development mode is an explicit admin-only setting. Never expose test OTPs in Live mode. */
	public static function is_development_mode() {
		return 'development' === sanitize_key( (string) CAS_DB::get_option( 'application_mode', 'live' ) );
	}

	private static function config() {
		return array(
			'digits'   => max( 4, min( 8, absint( CAS_DB::get_option( 'otp_digits', 6 ) ) ) ),
			'expiry'   => max( 1, absint( CAS_DB::get_option( 'otp_expiry_minutes', 10 ) ) ),
			'cooldown' => max( 15, absint( CAS_DB::get_option( 'otp_resend_cooldown_seconds', 60 ) ) ),
			'max'      => max( 1, absint( CAS_DB::get_option( 'otp_max_attempts', 5 ) ) ),
			'lock'     => max( 1, absint( CAS_DB::get_option( 'otp_lockout_minutes', 15 ) ) ),
			'ip'       => max( 1, absint( CAS_DB::get_option( 'otp_ip_hourly_limit', 10 ) ) ),
		);
	}

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	public static function rate_limit_check( $mobile ) {
		global $wpdb;
		$mobile = CAS_DB::normalize_mobile( $mobile );
		if ( ! $mobile ) {
			return new WP_Error( 'cas_otp_invalid_mobile', __( 'Invalid mobile number.', 'cas' ) );
		}

		$c     = self::config();
		$t     = CAS_DB::table( 'otp_logs' );
		$ip    = self::ip();
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE ip_address=%s AND created_at >= %s", $ip, CAS_DB::mysql_datetime( current_time( 'timestamp' ) - HOUR_IN_SECONDS ) ) ) );
		if ( $count >= $c['ip'] ) {
			return new WP_Error( 'cas_otp_ip_limited', __( 'Too many OTP requests from this IP.', 'cas' ) );
		}

		$latest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE mobile=%s ORDER BY id DESC LIMIT 1", $mobile ) );
		if ( $latest && ! absint( $latest->verified ) && strtotime( $latest->created_at ) + $c['cooldown'] > current_time( 'timestamp' ) ) {
			return new WP_Error( 'cas_otp_cooldown', __( 'Please wait before requesting another OTP.', 'cas' ) );
		}

		return true;
	}

	public static function generate( $mobile ) {
		global $wpdb;
		$mobile = CAS_DB::normalize_mobile( $mobile );
		if ( ! $mobile ) {
			return new WP_Error( 'cas_otp_invalid_mobile', __( 'Invalid mobile number.', 'cas' ) );
		}

		$check = self::rate_limit_check( $mobile );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$c       = self::config();
		// Generate the configured number of digits without allowing a leading zero.
		$minimum = (int) pow( 10, $c['digits'] - 1 );
		$maximum = (int) pow( 10, $c['digits'] ) - 1;
		$otp     = (string) wp_rand( $minimum, $maximum );
		$expires = CAS_DB::mysql_datetime( current_time( 'timestamp' ) + $c['expiry'] * MINUTE_IN_SECONDS );

		$wpdb->insert(
			CAS_DB::table( 'otp_logs' ),
			array(
				'mobile'     => $mobile,
				'otp_hash'   => wp_hash_password( $otp ),
				'expires_at' => $expires,
				'attempts'   => 0,
				'verified'   => 0,
				'ip_address' => self::ip(),
				'created_at' => CAS_DB::now(),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return array( 'success' => true, 'otp' => $otp, 'mobile' => $mobile, 'expires_at' => $expires, 'log_id' => absint( $wpdb->insert_id ) );
	}

	/**
	 * Return valid registered patient emails for a mobile number.
	 * Only active existing patient profiles are considered.
	 */
	private static function get_registered_emails( $mobile ) {
		$emails = array();
		foreach ( CAS_DB::get_patients_by_mobile( $mobile ) as $patient ) {
			$email = sanitize_email( $patient->email ?? '' );
			if ( $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}
		return array_values( array_unique( $emails ) );
	}

	/**
	 * Send a duplicate OTP by email when enabled in backend OTP settings.
	 * Email sending failure does not block SMS OTP login.
	 */
	private static function maybe_send_email_copy( $mobile, $otp, $expires_at ) {
		// Development mode deliberately has no external delivery side effects.
		if ( self::is_development_mode() ) {
			return array( 'enabled' => false, 'sent' => false, 'email' => '' );
		}

		if ( ! absint( CAS_DB::get_option( 'email_otp_enabled', 0 ) ) ) {
			return array( 'enabled' => false, 'sent' => false, 'email' => '' );
		}

		$emails = self::get_registered_emails( $mobile );
		if ( empty( $emails ) ) {
			return array( 'enabled' => true, 'sent' => false, 'email' => '' );
		}

		$brand   = sanitize_text_field( CAS_DB::get_option( 'brand_name', 'Chamber Appointment System' ) );
		$subject = sprintf( __( '%s patient portal OTP', 'cas' ), $brand );
		$body    = sprintf(
			__( "Dear Patient,

Your %1\$s OTP is %2\$s. This OTP will expire at %3\$s.

If you did not request this OTP, please ignore this email.", 'cas' ),
			$brand,
			$otp,
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $expires_at ) )
		);

		$sent_any = false;
		foreach ( $emails as $email ) {
			if ( wp_mail( $email, $subject, $body ) ) {
				$sent_any = true;
			}
		}

		return array( 'enabled' => true, 'sent' => $sent_any, 'email' => $sent_any ? implode( ', ', $emails ) : '' );
	}

	/**
	 * Build the domain-bound final line required by the browser WebOTP API.
	 * Browsers ignore this line when WebOTP is unsupported, so normal SMS delivery
	 * and manual entry remain fully functional.
	 *
	 * @param string $otp Numeric one-time code.
	 * @return string
	 */
	private static function web_otp_sms_suffix( $otp ) {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = strtolower( preg_replace( '/[^a-z0-9.-]/i', '', $host ) );
		$otp  = preg_replace( '/[^0-9]/', '', (string) $otp );

		if ( '' === $host || '' === $otp ) {
			return '';
		}

		return "\n\n@{$host} #{$otp}";
	}

	public static function send( $mobile ) {
		$g = self::generate( $mobile );
		if ( is_wp_error( $g ) ) {
			return $g;
		}

		/*
		 * Append the WebOTP domain binding after rendering the administrator's OTP
		 * template. This preserves custom wording while keeping the final SMS line in
		 * the exact format expected by supported Android browsers.
		 */
		$template = CAS_SMS::get_template( 'otp_template' );
		if ( '' === trim( (string) $template ) ) {
			$sms = new WP_Error( 'cas_sms_template_missing', __( 'SMS template is missing.', 'cas' ) );
		} else {
			$message = CAS_SMS::replace_placeholders( $template, array( 'otp' => $g['otp'] ) );
			$message .= self::web_otp_sms_suffix( $g['otp'] );
			$sms = CAS_SMS::send( $g['mobile'], $message, null, 'otp' );
		}
		if ( is_wp_error( $sms ) ) {
			return $sms;
		}

		$email = self::maybe_send_email_copy( $g['mobile'], $g['otp'], $g['expires_at'] );

		$development_mode = self::is_development_mode();

		return array(
			'success'                 => true,
			'mobile'                  => $g['mobile'],
			'expires_at'              => $g['expires_at'],
			'resend_cooldown_seconds' => self::config()['cooldown'],
			'otp_length'               => self::config()['digits'],
			'sms'                     => $sms,
			'email_otp_enabled'       => ! empty( $email['enabled'] ),
			'email_otp_sent'          => ! empty( $email['sent'] ),
			'email'                   => sanitize_text_field( $email['email'] ?? '' ),
			'development_mode'        => $development_mode,
			// This value is intentionally present only while the backend is explicitly in Development mode.
			'development_otp'         => $development_mode ? (string) $g['otp'] : '',
		);
	}

	public static function verify( $mobile, $otp ) {
		global $wpdb;
		$mobile = CAS_DB::normalize_mobile( $mobile );
		$otp    = preg_replace( '/[^0-9]/', '', (string) $otp );
		$digits = self::config()['digits'];
		if ( ! $mobile || ! preg_match( '/^[0-9]{' . $digits . '}$/', $otp ) ) {
			return new WP_Error( 'cas_otp_invalid', __( 'Invalid OTP request.', 'cas' ) );
		}

		$t   = CAS_DB::table( 'otp_logs' );
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE mobile=%s AND verified=0 ORDER BY id DESC LIMIT 1", $mobile ) );
		if ( ! $log || strtotime( $log->expires_at ) < current_time( 'timestamp' ) ) {
			return new WP_Error( 'cas_otp_expired', __( 'OTP has expired.', 'cas' ) );
		}

		$c = self::config();
		if ( absint( $log->attempts ) >= $c['max'] ) {
			return new WP_Error( 'cas_otp_locked', __( 'Too many attempts.', 'cas' ) );
		}

		if ( ! wp_check_password( $otp, $log->otp_hash ) ) {
			$wpdb->update( $t, array( 'attempts' => absint( $log->attempts ) + 1 ), array( 'id' => absint( $log->id ) ), array( '%d' ), array( '%d' ) );
			return new WP_Error( 'cas_otp_incorrect', __( 'Incorrect OTP.', 'cas' ) );
		}

		$wpdb->update( $t, array( 'verified' => 1, 'attempts' => absint( $log->attempts ) + 1 ), array( 'id' => absint( $log->id ) ), array( '%d', '%d' ), array( '%d' ) );
		$patients = CAS_DB::get_patients_by_mobile( $mobile );
		$action   = count( $patients ) === 1 ? 'auto_login' : ( count( $patients ) > 1 ? 'select_profile' : 'create_profile' );
		do_action( 'cas_otp_verified', $mobile, $patients, $action );
		return array( 'success' => true, 'mobile' => $mobile, 'action' => $action, 'patients' => $patients );
	}

	public static function cleanup_expired() {
		global $wpdb;
		$t = CAS_DB::table( 'otp_logs' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE expires_at < %s OR (verified=1 AND created_at < %s)", CAS_DB::now(), CAS_DB::mysql_datetime( current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ) ) );
		return absint( $wpdb->rows_affected );
	}
}
