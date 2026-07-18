<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_SMS {
	/** Return true when this installation is intentionally running without real delivery providers. */
	public static function is_development_mode() {
		return 'development' === sanitize_key( (string) CAS_DB::get_option( 'application_mode', 'live' ) );
	}

	public static function send_template( $key, $number, $placeholders = array(), $appointment_id = null, $sent_by = 'system' ) {
		$key      = sanitize_key( $key );
		$template = self::get_template( $key );

		if ( '' === trim( (string) $template ) ) {
			$message = __( 'SMS template is missing.', 'cas' );
			self::log_sms( $number, '', 'template_missing', $message, $appointment_id, $sent_by );
			return new WP_Error( 'cas_sms_template_missing', $message );
		}

		return self::send( $number, self::replace_placeholders( $template, $placeholders ), $appointment_id, $sent_by );
	}

	public static function send( $number, $message, $appointment_id = null, $sent_by = 'system' ) {
		$raw_number = sanitize_text_field( (string) $number );
		$number     = CAS_DB::normalize_mobile( $number );
		$message    = sanitize_textarea_field( wp_strip_all_tags( (string) $message ) );
		$sent_by    = sanitize_text_field( (string) $sent_by );

		if ( ! $number ) {
			self::log_sms( $raw_number, $message, '1001', 'Invalid Bangladeshi mobile number.', $appointment_id, $sent_by );
			return new WP_Error( 'cas_sms_invalid_number', __( 'Invalid Bangladeshi mobile number.', 'cas' ) );
		}

		if ( '' === trim( $message ) ) {
			self::log_sms( $number, $message, '1003', 'Empty SMS message.', $appointment_id, $sent_by );
			return new WP_Error( 'cas_sms_empty_message', __( 'SMS message is empty.', 'cas' ) );
		}

		// Development mode never calls the configured SMS provider. Messages are logged only.
		if ( self::is_development_mode() ) {
			self::log_sms( $number, $message, 'dev_skip', 'Development mode: SMS API was not called.', $appointment_id, $sent_by );
			return array(
				'success'       => true,
				'development'   => true,
				'recipient'     => $number,
				'response_code' => 'dev_skip',
				'response_body' => 'Development mode: SMS API was not called.',
			);
		}

		if ( ! absint( CAS_DB::get_option( 'sms_enabled', 0 ) ) ) {
			self::log_sms( $number, $message, 'disabled', 'SMS sending is disabled in Chamber Appointment System settings.', $appointment_id, $sent_by );
			return new WP_Error( 'cas_sms_disabled', __( 'SMS sending is disabled in SMS Settings.', 'cas' ) );
		}

		$url     = esc_url_raw( CAS_DB::get_option( 'sms_api_url', 'http://bulksmsbd.net/api/smsapi' ) );
		$api_key = (string) CAS_DB::get_option( 'sms_api_key', '' );
		$sender  = sanitize_text_field( CAS_DB::get_option( 'sms_senderid', '' ) );

		if ( ! $url || ! $api_key || ! $sender ) {
			self::log_sms( $number, $message, '1003', 'Missing SMS API URL, API key, or sender ID.', $appointment_id, $sent_by );
			return new WP_Error( 'cas_sms_missing_fields', __( 'SMS API URL, API key, and sender ID are required.', 'cas' ) );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'body'    => array(
					'api_key'  => $api_key,
					'senderid' => $sender,
					'number'   => $number,
					'message'  => $message,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_sms( $number, $message, 'http_error', $response->get_error_message(), $appointment_id, $sent_by );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = self::parse_response_code( $body, $response );

		self::log_sms( $number, $message, $code, $body, $appointment_id, $sent_by );

		if ( '202' !== (string) $code ) {
			return new WP_Error( 'cas_sms_failed', self::get_response_message( $code ), array( 'response_code' => $code, 'response_body' => $body ) );
		}

		return array( 'success' => true, 'recipient' => $number, 'response_code' => $code, 'response_body' => $body );
	}

	public static function log_sms( $recipient, $message, $code = '', $body = '', $appointment_id = null, $sent_by = 'system' ) {
		$id = CAS_DB::insert_sms_log( $recipient, $message, $code, $body, $appointment_id, $sent_by );
		do_action( 'cas_sms_sent', $recipient, $message, $code, $body, $appointment_id );
		return $id;
	}

	public static function parse_response_code( $body, $response = null ) {
		$body = trim( (string) $body );
		$json = json_decode( $body, true );

		if ( is_array( $json ) ) {
			foreach ( array( 'response_code', 'code', 'status_code', 'status' ) as $key ) {
				if ( isset( $json[ $key ] ) ) {
					$value = preg_replace( '/[^0-9]/', '', (string) $json[ $key ] );
					if ( '' !== $value ) { return $value; }
				}
			}
		}

		if ( preg_match( '/\b(202|1001|1002|1003|1005|1007|1032)\b/', $body, $matches ) ) {
			return $matches[1];
		}

		if ( is_array( $response ) ) {
			$http_code = wp_remote_retrieve_response_code( $response );
			return $http_code ? (string) $http_code : 'unknown';
		}

		return 'unknown';
	}

	public static function test_sms( $number, $message = '' ) {
		if ( '' === trim( (string) $message ) ) {
			$message = sprintf( __( 'This is a test SMS from %s.', 'cas' ), CAS_DB::get_option( 'brand_name', 'Chamber Appointment System' ) );
		}

		return self::send( $number, $message, null, 'test' );
	}



	/**
	 * Checks SMS balance from BulkSMSBD or a compatible balance endpoint.
	 *
	 * Supported URL formats in SMS Settings:
	 * - http://bulksmsbd.net/api/getBalanceApi?api_key={api_key}
	 * - http://bulksmsbd.net/api/getBalanceApi?api_key=(APIKEY)
	 * - http://bulksmsbd.net/api/getBalanceApi
	 *
	 * The saved SMS API key is inserted server-side so it is not printed in the page.
	 *
	 * @param string $override_url Optional URL supplied from the admin form before saving.
	 * @return array|WP_Error
	 */
	public static function check_balance( $override_url = '' ) {
		if ( self::is_development_mode() ) {
			return new WP_Error( 'cas_sms_development_mode', __( 'SMS balance checking is disabled while Application Mode is Development.', 'cas' ) );
		}

		$stored_url = (string) CAS_DB::get_option( 'sms_balance_url', '' );
		$url        = '' !== trim( (string) $override_url ) ? (string) $override_url : $stored_url;
		$url        = '' !== trim( $url ) ? $url : 'http://bulksmsbd.net/api/getBalanceApi?api_key={api_key}';
		$api_key    = (string) CAS_DB::get_option( 'sms_api_key', '' );

		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'cas_sms_api_key_missing', __( 'SMS API key is missing. Save your BulkSMSBD API key first.', 'cas' ) );
		}

		$url = self::prepare_balance_url( $url, $api_key );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success'       => true,
			'response_code' => wp_remote_retrieve_response_code( $response ),
			'body'          => wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Adds the API key to the balance URL in a safe, provider-compatible way.
	 *
	 * @param string $url     Balance endpoint URL.
	 * @param string $api_key Saved BulkSMSBD API key.
	 * @return string|WP_Error
	 */
	private static function prepare_balance_url( $url, $api_key ) {
		$url     = html_entity_decode( trim( (string) $url ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$api_key = trim( (string) $api_key );

		if ( '' === $url ) {
			$url = 'http://bulksmsbd.net/api/getBalanceApi?api_key={api_key}';
		}

		/*
		 * Do not sanitize with esc_url_raw() before replacing the placeholder.
		 * WordPress may encode {api_key} as %7Bapi_key%7D; then BulkSMSBD receives
		 * the placeholder text instead of the real key and returns response_code 1011.
		 */
		$decoded_url = rawurldecode( $url );
		if ( false !== strpos( $decoded_url, '{api_key}' ) || false !== strpos( $decoded_url, '{API_KEY}' ) || false !== strpos( $decoded_url, '(APIKEY)' ) || false !== strpos( $decoded_url, '[api_key]' ) ) {
			$url = $decoded_url;
		}

		$placeholders = array( '{api_key}', '{API_KEY}', '(APIKEY)', '(api_key)', '[api_key]', '[API_KEY]' );
		$has_placeholder = false;
		foreach ( $placeholders as $placeholder ) {
			if ( false !== strpos( $url, $placeholder ) ) {
				$has_placeholder = true;
				$url = str_replace( $placeholder, rawurlencode( $api_key ), $url );
			}
		}

		/*
		 * If api_key is missing, add the saved key. If api_key is present but still
		 * contains a placeholder/empty value, replace it with the saved key. A full URL
		 * with a real api_key is also supported for compatibility.
		 */
		$parts = wp_parse_url( $url );
		$query = array();
		if ( isset( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query );
		}

		if ( ! isset( $query['api_key'] ) || '' === trim( (string) $query['api_key'] ) || false !== strpos( (string) $query['api_key'], '{' ) || false !== strpos( (string) $query['api_key'], '(' ) || false !== strpos( (string) $query['api_key'], '[' ) ) {
			$url = remove_query_arg( 'api_key', $url );
			$url = add_query_arg( 'api_key', $api_key, $url );
		}

		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( ! $url ) {
			return new WP_Error( 'cas_sms_balance_url_invalid', __( 'Balance check URL is invalid.', 'cas' ) );
		}

		return $url;
	}

	public static function get_templates() {
		$defaults  = CAS_DB::get_default_options();
		$templates = CAS_DB::get_option( 'sms_templates', array() );
		return wp_parse_args( is_array( $templates ) ? $templates : array(), $defaults['sms_templates'] );
	}

	public static function get_template( $key ) {
		$templates = self::get_templates();
		$key       = sanitize_key( $key );
		return isset( $templates[ $key ] ) ? (string) $templates[ $key ] : '';
	}

	public static function replace_placeholders( $template, $placeholders = array() ) {
		$placeholders = wp_parse_args(
			is_array( $placeholders ) ? $placeholders : array(),
			array(
				'brand'          => CAS_DB::get_option( 'brand_name', 'Chamber Appointment System' ),
				'patient_name'   => '',
				'serial'         => '',
				'date'           => '',
				'reporting_time' => '',
				'doctor_name'    => '',
				'queue_number'   => '',
				'otp'            => '',
			)
		);

		foreach ( $placeholders as $key => $value ) {
			$template = str_replace( '{' . sanitize_key( $key ) . '}', sanitize_text_field( (string) $value ), $template );
		}

		return sanitize_textarea_field( $template );
	}

	public static function get_response_message( $code ) {
		$messages = array(
			'202'              => __( 'SMS sent successfully.', 'cas' ),
			'1001'             => __( 'Invalid mobile number.', 'cas' ),
			'1002'             => __( 'Invalid sender ID.', 'cas' ),
			'1003'             => __( 'Missing SMS API fields.', 'cas' ),
			'1005'             => __( 'SMS provider internal error.', 'cas' ),
			'1007'             => __( 'Insufficient SMS balance.', 'cas' ),
			'1032'             => __( 'SMS provider IP whitelist restriction.', 'cas' ),
			'disabled'         => __( 'SMS sending is disabled in SMS Settings.', 'cas' ),
			'template_missing' => __( 'SMS template is missing.', 'cas' ),
			'http_error'       => __( 'HTTP error while sending SMS.', 'cas' ),
			'unknown'          => __( 'Unknown SMS provider response.', 'cas' ),
		);

		$code = sanitize_text_field( (string) $code );
		return isset( $messages[ $code ] ) ? $messages[ $code ] : sprintf( __( 'SMS provider returned response code %s.', 'cas' ), $code );
	}
}
