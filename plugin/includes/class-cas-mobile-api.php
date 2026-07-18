<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Mobile REST API for the CAS Diabetes Care patient app.
 *
 * Namespace: /wp-json/cas-dc/v1
 * Authentication: Bearer token returned after OTP verification.
 * The mobile app never talks to the database directly; it uses these endpoints only.
 */
class CAS_Mobile_API {
	const NAMESPACE = 'cas-dc/v1';
	const TOKEN_TTL = 2592000; // 30 days.

	/** Return mobile session table name. */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cas_mobile_sessions';
	}

	/** Create/upgrade the mobile session table. */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(191) NOT NULL,
			mobile VARCHAR(20) NOT NULL,
			device_name VARCHAR(190) DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_used_at DATETIME DEFAULT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY mobile (mobile),
			KEY expires_at (expires_at),
			KEY revoked_at (revoked_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/** Register all REST endpoints. */
	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/** REST route definitions. */
	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'status' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/login/send-otp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'send_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/login/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'verify_otp' ),
			'permission_callback' => '__return_true',
		) );

		// New patients complete a standard patient profile only after OTP verification.
		register_rest_route( self::NAMESPACE, '/registration/complete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'complete_registration' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/logout', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'logout' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/patients', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'patients' ),
			'permission_callback' => '__return_true',
		) );


		// Appointment endpoints for the Patient Care mobile app.
		register_rest_route( self::NAMESPACE, '/appointments/doctors', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'appointment_doctors' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/appointments/available-slots', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'appointment_available_slots' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/appointments', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'appointments' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/appointments/book', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'book_appointment' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/appointments/update', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'update_appointment' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/appointments/cancel', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'cancel_appointment' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** Simple endpoint for app connectivity checks. */
	public static function status() {
		return rest_ensure_response( array(
			'success'          => true,
			'plugin'           => 'Chamber Appointment System',
			'version'          => defined( 'CAS_VERSION' ) ? CAS_VERSION : '',
			'application_mode' => class_exists( 'CAS_OTP' ) && CAS_OTP::is_development_mode() ? 'development' : 'live',
			'time'             => current_time( 'mysql' ),
		) );
	}

	/** Send OTP using the existing CAS OTP/SMS/email workflow. */
	public static function send_otp( WP_REST_Request $request ) {
		$mobile = CAS_DB::normalize_mobile( $request->get_param( 'mobile' ) );
		if ( ! $mobile ) {
			return new WP_Error( 'cas_mobile_invalid_mobile', __( 'Invalid mobile number.', 'cas' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'CAS_OTP' ) ) {
			return new WP_Error( 'cas_mobile_otp_unavailable', __( 'OTP service is unavailable.', 'cas' ), array( 'status' => 500 ) );
		}

		$result = CAS_OTP::send( $mobile );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success'                 => true,
			'mobile'                  => $mobile,
			'expires_at'              => sanitize_text_field( $result['expires_at'] ?? '' ),
			'resend_cooldown_seconds' => absint( $result['resend_cooldown_seconds'] ?? 60 ),
			'otp_length'               => absint( $result['otp_length'] ?? CAS_DB::get_option( 'otp_digits', 6 ) ),
			'email_otp_enabled'       => ! empty( $result['email_otp_enabled'] ),
			'email_otp_sent'          => ! empty( $result['email_otp_sent'] ),
			'email'                   => sanitize_text_field( $result['email'] ?? '' ),
			'development_mode'        => ! empty( $result['development_mode'] ),
			'development_otp'         => ! empty( $result['development_mode'] ) ? preg_replace( '/[^0-9]/', '', (string) ( $result['development_otp'] ?? '' ) ) : '',
		) );
	}

	/** Verify OTP and issue a mobile Bearer token. */
	public static function verify_otp( WP_REST_Request $request ) {
		$mobile      = CAS_DB::normalize_mobile( $request->get_param( 'mobile' ) );
		$otp         = preg_replace( '/[^0-9]/', '', (string) $request->get_param( 'otp' ) );
		$device_name = sanitize_text_field( $request->get_param( 'device_name' ) );

		$otp_digits = max( 4, min( 8, absint( CAS_DB::get_option( 'otp_digits', 6 ) ) ) );
		if ( ! $mobile || ! preg_match( '/^[0-9]{' . $otp_digits . '}$/', $otp ) ) {
			return new WP_Error( 'cas_mobile_invalid_otp', __( 'Invalid OTP request.', 'cas' ), array( 'status' => 400 ) );
		}
		$result = CAS_OTP::verify( $mobile, $otp );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$token = self::create_token( $mobile, $device_name );
		return rest_ensure_response( array(
			'success'    => true,
			'token'      => $token['token'],
			'expires_at' => $token['expires_at'],
			'mobile'     => $mobile,
			'action'     => sanitize_key( $result['action'] ?? '' ),
			'patients'   => self::patient_list_for_mobile( $mobile ),
		) );
	}

	/**
	 * Create the first standard patient profile for an OTP-verified mobile account.
	 * The mobile number is taken from the authenticated bearer token, never from
	 * request data, so a user cannot register a profile under another account.
	 */
	public static function complete_registration( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		if ( ! class_exists( 'CAS_Patient' ) ) {
			return new WP_Error( 'cas_mobile_patient_unavailable', __( 'Patient registration service is unavailable.', 'cas' ), array( 'status' => 500 ) );
		}

		$existing = CAS_DB::get_patients_by_mobile( $session->mobile );
		if ( ! empty( $existing ) ) {
			return new WP_Error( 'cas_mobile_profile_exists', __( 'A patient profile already exists for this mobile number. Please log in instead.', 'cas' ), array( 'status' => 409 ) );
		}

		$full_name   = sanitize_text_field( $request->get_param( 'full_name' ) );
		$age         = absint( $request->get_param( 'age' ) );
		$gender      = sanitize_key( $request->get_param( 'gender' ) );
		$blood_group = sanitize_text_field( $request->get_param( 'blood_group' ) );
		$address     = sanitize_textarea_field( $request->get_param( 'address' ) );
		$city        = sanitize_text_field( $request->get_param( 'city' ) );
		$email       = sanitize_email( $request->get_param( 'email' ) );

		if ( ! $full_name || $age < 1 || $age > 125 || ! in_array( $gender, array( 'male', 'female', 'other' ), true ) ) {
			return new WP_Error( 'cas_mobile_registration_required', __( 'Please enter full name, a valid age, and gender to create your patient profile.', 'cas' ), array( 'status' => 400 ) );
		}
		if ( $email && ! is_email( $email ) ) {
			return new WP_Error( 'cas_mobile_invalid_email', __( 'Please enter a valid email address.', 'cas' ), array( 'status' => 400 ) );
		}

		$patient_id = CAS_Patient::create( array(
			'full_name'            => $full_name,
			'mobile'               => $session->mobile,
			'age'                  => $age,
			'gender'               => $gender,
			'blood_group'          => $blood_group,
			'address'              => $address,
			'city'                 => $city,
			'email'                => $email,
			'is_active'            => 1,
			'require_demographics' => true,
		) );
		if ( is_wp_error( $patient_id ) ) { return $patient_id; }

		return rest_ensure_response( array(
			'success'  => true,
			'message'  => __( 'Your patient profile has been created successfully.', 'cas' ),
			'patient_id' => absint( $patient_id ),
			'patients' => self::patient_list_for_mobile( $session->mobile ),
		) );
	}

	/** Revoke current mobile token. */
	public static function logout( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		global $wpdb;
		$wpdb->update( self::table(), array( 'revoked_at' => current_time( 'mysql' ) ), array( 'id' => absint( $session->id ) ), array( '%s' ), array( '%d' ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** Return accessible patient profiles. */
	public static function patients( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		return rest_ensure_response( array(
			'success'  => true,
			'mobile'   => $session->mobile,
			'patients' => self::patient_list_for_mobile( $session->mobile ),
		) );
	}







	/**
	 * Return active doctors and their appointment scheduling details.
	 */
	public static function appointment_doctors( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		global $wpdb;
		$doctors = CAS_DB::table( 'doctors' );
		$schedules = CAS_DB::table( 'schedules' );
		$solo_doctor_id = CAS_DB::resolve_booking_doctor_id( 0 );
		if ( CAS_DB::is_single_doctor_mode() ) {
			if ( ! $solo_doctor_id ) {
				return new WP_Error( 'cas_mobile_solo_doctor_missing', __( 'The chamber has not configured its solo booking doctor yet.', 'cas' ), array( 'status' => 503 ) );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.name, d.specialty, s.daily_limit, s.start_time, s.end_time, s.batch_size, s.reporting_interval, s.active_days FROM {$doctors} d INNER JOIN {$schedules} s ON s.doctor_id = d.id WHERE d.is_active = 1 AND s.is_active = 1 AND d.id = %d ORDER BY d.name ASC", $solo_doctor_id ) );
		} else {
			$rows = $wpdb->get_results( "SELECT d.id, d.name, d.specialty, s.daily_limit, s.start_time, s.end_time, s.batch_size, s.reporting_interval, s.active_days FROM {$doctors} d INNER JOIN {$schedules} s ON s.doctor_id = d.id WHERE d.is_active = 1 AND s.is_active = 1 ORDER BY d.name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id' => absint( $row->id ),
				'name' => sanitize_text_field( $row->name ),
				'specialty' => sanitize_text_field( $row->specialty ),
				'daily_limit' => absint( $row->daily_limit ),
				'start_time' => sanitize_text_field( $row->start_time ),
				'end_time' => sanitize_text_field( $row->end_time ),
			);
		}
		return rest_ensure_response( array( 'success' => true, 'appointment_mode' => CAS_DB::is_single_doctor_mode() ? 'single' : 'multiple', 'single_doctor_id' => absint( CAS_DB::resolve_booking_doctor_id( 0 ) ), 'doctors' => $out ) );
	}

	/** Return free serials for a doctor/date. */
	public static function appointment_available_slots( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $request->get_param( 'doctor_id' ) ) );
		$date = self::sanitize_date( $request->get_param( 'date' ) );
		if ( ! $doctor_id || ! $date ) {
			return new WP_Error( 'cas_mobile_slot_args', __( 'Doctor and valid appointment date are required.', 'cas' ), array( 'status' => 400 ) );
		}
		$map = CAS_Appointment::get_slot_map( $doctor_id, $date );
		if ( is_wp_error( $map ) ) { return $map; }
		$slots = array();
		foreach ( (array) $map['slots'] as $slot ) {
			if ( empty( $slot['is_booked'] ) ) {
				$slots[] = array(
					'serial_number' => absint( $slot['serial'] ),
					'reporting_time' => sanitize_text_field( $slot['reporting_time'] ),
					'reporting_time_display' => sanitize_text_field( $slot['reporting_time_display'] ),
				);
			}
		}
		return rest_ensure_response( array( 'success' => true, 'doctor_id' => $doctor_id, 'date' => $date, 'slots' => $slots ) );
	}

	/** List appointments belonging to the authenticated mobile number. */
	public static function appointments( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		$patient_id = absint( $request->get_param( 'patient_id' ) );
		$ids = self::accessible_patient_ids( $session->mobile );
		if ( $patient_id && ! in_array( $patient_id, $ids, true ) ) {
			return new WP_Error( 'cas_mobile_forbidden_patient', __( 'This patient profile is not available for this login.', 'cas' ), array( 'status' => 403 ) );
		}
		if ( $patient_id ) { $ids = array( $patient_id ); }
		$limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 50 ) ) );
		$items = array();
		foreach ( $ids as $id ) {
			$items = array_merge( $items, CAS_Appointment::get_by_patient( $id, array( 'limit' => $limit ) ) );
		}
		usort( $items, function( $a, $b ) { return strcmp( $b->appointment_date . ' ' . $b->serial_number, $a->appointment_date . ' ' . $a->serial_number ); } );
		$items = array_slice( $items, 0, $limit );
		return rest_ensure_response( array( 'success' => true, 'appointments' => array_map( array( __CLASS__, 'appointment_to_array' ), $items ) ) );
	}

	/** Book an appointment for self or an accessible family profile. */
	public static function book_appointment( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		$patient_id = absint( $request->get_param( 'patient_id' ) );
		if ( ! $patient_id || ! in_array( $patient_id, self::accessible_patient_ids( $session->mobile ), true ) ) {
			return new WP_Error( 'cas_mobile_forbidden_patient', __( 'Select a valid patient profile.', 'cas' ), array( 'status' => 403 ) );
		}
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $request->get_param( 'doctor_id' ) ) );
		$date = self::sanitize_date( $request->get_param( 'appointment_date' ) );
		$serial = absint( $request->get_param( 'serial_number' ) );
		if ( ! $doctor_id || ! $date || ! $serial ) {
			return new WP_Error( 'cas_mobile_booking_required', __( 'Doctor, date and serial are required.', 'cas' ), array( 'status' => 400 ) );
		}
		$created = CAS_Appointment::create( array(
			'doctor_id' => $doctor_id,
			'patient_id' => $patient_id,
			'appointment_date' => $date,
			'serial_number' => $serial,
			'status' => 'confirmed',
			'source' => 'frontend',
			'send_sms' => true,
		) );
		if ( is_wp_error( $created ) ) { return $created; }
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Appointment booked successfully.', 'cas' ), 'appointment' => self::appointment_to_array( CAS_Appointment::get_by_id( $created ) ) ) );
	}

	/** Update an appointment owned by this mobile number. */
	public static function update_appointment( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		$appointment_id = absint( $request->get_param( 'appointment_id' ) );
		$appointment = CAS_Appointment::get_by_id( $appointment_id );
		if ( ! $appointment || ! in_array( absint( $appointment->patient_id ), self::accessible_patient_ids( $session->mobile ), true ) ) { return new WP_Error( 'cas_mobile_appointment_not_found', __( 'Appointment was not found for this login.', 'cas' ), array( 'status' => 404 ) ); }
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $request->get_param( 'doctor_id' ) ) );
		$updated = CAS_Appointment::update_patient_booking( $appointment_id, array( 'doctor_id' => $doctor_id, 'appointment_date' => self::sanitize_date( $request->get_param( 'appointment_date' ) ), 'serial_number' => absint( $request->get_param( 'serial_number' ) ), 'notes' => sanitize_textarea_field( $request->get_param( 'notes' ) ) ) );
		if ( is_wp_error( $updated ) ) { return $updated; }
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Appointment updated successfully.', 'cas' ), 'appointment' => self::appointment_to_array( CAS_Appointment::get_by_id( $appointment_id ) ) ) );
	}

	/** Cancel an appointment owned by this mobile number. */
	public static function cancel_appointment( WP_REST_Request $request ) {
		$session = self::authenticate( $request );
		if ( is_wp_error( $session ) ) { return $session; }
		$appointment_id = absint( $request->get_param( 'appointment_id' ) );
		$appointment = CAS_Appointment::get_by_id( $appointment_id );
		if ( ! $appointment || ! in_array( absint( $appointment->patient_id ), self::accessible_patient_ids( $session->mobile ), true ) ) {
			return new WP_Error( 'cas_mobile_appointment_not_found', __( 'Appointment was not found for this login.', 'cas' ), array( 'status' => 404 ) );
		}
		if ( ! CAS_Appointment::is_patient_manageable( $appointment ) ) {
			return new WP_Error( 'cas_mobile_appointment_not_cancellable', CAS_Appointment::patient_management_lock_message( $appointment ), array( 'status' => 400 ) );
		}
		$result = CAS_Appointment::cancel( $appointment_id, __( 'Cancelled by patient through mobile app.', 'cas' ) );
		if ( is_wp_error( $result ) || ! $result ) { return is_wp_error( $result ) ? $result : new WP_Error( 'cas_mobile_cancel_failed', __( 'Could not cancel appointment.', 'cas' ), array( 'status' => 500 ) ); }
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Appointment cancelled.', 'cas' ) ) );
	}

	/** Submit all currently required monitoring forms with one action. */

	/** Validate and normalize a single data payload. */

	/** Get patient IDs that the verified mobile number is allowed to access. */
	private static function accessible_patient_ids( $mobile ) {
		$patients = CAS_DB::get_patients_by_mobile( $mobile );
		return array_values( array_filter( array_map( 'absint', wp_list_pluck( $patients, 'id' ) ) ) );
	}

	/** Normalize appointment data for mobile clients. */
	private static function appointment_to_array( $appointment ) {
		if ( ! $appointment ) { return null; }
		return array(
			'id' => absint( $appointment->id ),
			'patient_id' => absint( $appointment->patient_id ),
			'patient_name' => sanitize_text_field( $appointment->patient_name ),
			'doctor_id' => absint( $appointment->doctor_id ),
			'doctor_name' => sanitize_text_field( $appointment->doctor_name ),
			'doctor_specialty' => sanitize_text_field( $appointment->doctor_specialty ),
			'appointment_date' => sanitize_text_field( $appointment->appointment_date ),
			'serial_number' => absint( $appointment->serial_number ),
			'reporting_time' => sanitize_text_field( $appointment->reporting_time ),
			'status' => sanitize_key( $appointment->status ),
			'notes' => sanitize_textarea_field( $appointment->notes ),
		);
	}

	/** Create mobile session token. */
	private static function create_token( $mobile, $device_name = '' ) {
		global $wpdb;
		$token = wp_generate_password( 64, false, false );
		$expires_at = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + self::TOKEN_TTL );
		$wpdb->insert(
			self::table(),
			array(
				'token_hash'   => hash( 'sha256', $token ),
				'mobile'       => sanitize_text_field( $mobile ),
				'device_name'  => sanitize_text_field( $device_name ),
				'created_at'   => current_time( 'mysql' ),
				'last_used_at' => current_time( 'mysql' ),
				'expires_at'   => get_date_from_gmt( $expires_at, 'Y-m-d H:i:s' ),
				'revoked_at'   => null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return array( 'token' => $token, 'expires_at' => get_date_from_gmt( $expires_at, 'Y-m-d H:i:s' ) );
	}

	/** Public wrapper used only by sibling mobile API modules. */
	public static function authenticate_request( WP_REST_Request $request ) {
		return self::authenticate( $request );
	}

	/** Extract and validate Bearer token. */
	private static function authenticate( WP_REST_Request $request ) {
		global $wpdb;
		$header = $request->get_header( 'authorization' );
		if ( ! $header && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}
		$token = '';
		if ( $header && preg_match( '/Bearer\s+(.+)/i', $header, $m ) ) {
			$token = trim( $m[1] );
		} elseif ( $request->get_param( 'auth_token' ) ) {
			$token = sanitize_text_field( $request->get_param( 'auth_token' ) );
		}
		if ( '' === $token ) {
			return new WP_Error( 'cas_mobile_no_token', __( 'Missing mobile app token.', 'cas' ), array( 'status' => 401 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token_hash = %s LIMIT 1', hash( 'sha256', $token ) ) );
		if ( ! $row || ! empty( $row->revoked_at ) || strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
			return new WP_Error( 'cas_mobile_invalid_token', __( 'Invalid or expired mobile app token.', 'cas' ), array( 'status' => 401 ) );
		}
		$wpdb->update( self::table(), array( 'last_used_at' => current_time( 'mysql' ) ), array( 'id' => absint( $row->id ) ), array( '%s' ), array( '%d' ) );
		return $row;
	}

	/** Patient list with enrollment status for a mobile number. */
	private static function patient_list_for_mobile( $mobile ) {
		global $wpdb;
		$patients = CAS_DB::get_patients_by_mobile( $mobile );
		$out = array();
		foreach ( $patients as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out[] = array(
				'id'                  => absint( $p->id ),
				'full_name'           => sanitize_text_field( $p->full_name ),
				'mobile'              => sanitize_text_field( $p->mobile ),
				'age'                 => class_exists( 'CAS_Patient' ) ? CAS_Patient::calculate_age( $p->date_of_birth ) : '',
				'gender'              => sanitize_text_field( $p->gender ),
				'blood_group'         => sanitize_text_field( $p->blood_group ),
				'email'               => sanitize_email( $p->email ),
			);
		}
		return $out;
	}



	private static function patient_to_array( $p ) {
		if ( ! $p ) { return null; }
		return array(
			'id'          => absint( $p->id ),
			'full_name'   => sanitize_text_field( $p->full_name ),
			'mobile'      => sanitize_text_field( $p->mobile ),
			'age'         => class_exists( 'CAS_Patient' ) ? CAS_Patient::calculate_age( $p->date_of_birth ) : '',
			'gender'      => sanitize_text_field( $p->gender ),
			'blood_group' => sanitize_text_field( $p->blood_group ),
			'city'        => sanitize_text_field( $p->city ),
			'email'       => sanitize_email( $p->email ),
		);
	}
}
