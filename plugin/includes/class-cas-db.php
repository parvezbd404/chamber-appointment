<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAS_DB {
	const OPTION_SETTINGS = 'cas_settings';
	const OPTION_VERSION  = 'cas_version';

	public static function table( $name ) {
		global $wpdb;
		$tables = array(
			'patients'       => $wpdb->prefix . 'cas_patients',
			'family_members' => $wpdb->prefix . 'cas_patient_family_members',
			'doctors'        => $wpdb->prefix . 'cas_doctors',
			'schedules'      => $wpdb->prefix . 'cas_schedules',
			'appointments'      => $wpdb->prefix . 'cas_appointments',
			'appointment_slots' => $wpdb->prefix . 'cas_appointment_slots',
			'waiting_list'      => $wpdb->prefix . 'cas_waiting_list',
			'sms_logs'       => $wpdb->prefix . 'cas_sms_logs',
			'otp_logs'       => $wpdb->prefix . 'cas_otp_logs',
			'messages'       => $wpdb->prefix . 'cas_messages',
		);
		$name = sanitize_key( $name );
		return isset( $tables[ $name ] ) ? $tables[ $name ] : '';
	}

	public static function get_tables() {
		return array(
			'patients'       => self::table( 'patients' ),
			'family_members' => self::table( 'family_members' ),
			'doctors'        => self::table( 'doctors' ),
			'schedules'      => self::table( 'schedules' ),
			'appointments'      => self::table( 'appointments' ),
			'appointment_slots' => self::table( 'appointment_slots' ),
			'waiting_list'      => self::table( 'waiting_list' ),
			'sms_logs'       => self::table( 'sms_logs' ),
			'otp_logs'       => self::table( 'otp_logs' ),
			'messages'       => self::table( 'messages' ),
		);
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::get_tables();
		$sql = array();
		$sql[] = "CREATE TABLE {$t['patients']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, full_name VARCHAR(190) NOT NULL, mobile VARCHAR(20) NOT NULL, date_of_birth DATE NULL, gender ENUM('male','female','other') NULL, blood_group VARCHAR(10) DEFAULT '', address TEXT NULL, city VARCHAR(120) DEFAULT '', email VARCHAR(190) DEFAULT '', notes TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY mobile (mobile), KEY is_active (is_active)) $c;";
		$sql[] = "CREATE TABLE {$t['family_members']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, primary_id BIGINT UNSIGNED NOT NULL, full_name VARCHAR(190) NOT NULL, relation VARCHAR(100) NOT NULL, date_of_birth DATE NULL, gender ENUM('male','female','other') NULL, blood_group VARCHAR(10) DEFAULT '', notes TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY primary_id (primary_id)) $c;";
		$sql[] = "CREATE TABLE {$t['doctors']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, specialty VARCHAR(190) DEFAULT '', mobile VARCHAR(20) DEFAULT '', email VARCHAR(190) DEFAULT '', bio TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY is_active (is_active)) $c;";
		$sql[] = "CREATE TABLE {$t['schedules']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, doctor_id BIGINT UNSIGNED NOT NULL, daily_limit INT NOT NULL DEFAULT 40, start_time TIME NOT NULL DEFAULT '14:00:00', end_time TIME NOT NULL DEFAULT '18:00:00', batch_size INT NOT NULL DEFAULT 10, reporting_interval INT NOT NULL DEFAULT 60, active_days VARCHAR(50) NOT NULL DEFAULT '0,1,2,3,4,5,6', holidays LONGTEXT NULL, weekday_breaks LONGTEXT NULL, allow_manual_pick TINYINT(1) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY doctor_id (doctor_id)) $c;";
		$sql[] = "CREATE TABLE {$t['appointments']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, doctor_id BIGINT UNSIGNED NOT NULL, patient_id BIGINT UNSIGNED NOT NULL, appointment_date DATE NOT NULL, serial_number INT NOT NULL, reporting_time TIME NULL, is_vip TINYINT(1) NOT NULL DEFAULT 0, patient_modify_count TINYINT UNSIGNED NOT NULL DEFAULT 0, checked_in_at DATETIME NULL, reconfirmation_called_for_date DATE NULL, reconfirmation_called_at DATETIME NULL, reconfirmation_called_by BIGINT UNSIGNED NULL, status ENUM('pending','confirmed','reconfirmed','cancelled','waiting_list','moved_from_waiting','checked_in','completed','no_show') NOT NULL DEFAULT 'pending', booked_by BIGINT UNSIGNED NULL, source ENUM('frontend','admin','waiting_list') NOT NULL DEFAULT 'frontend', notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY doctor_date_serial_lookup (doctor_id, appointment_date, serial_number), KEY patient_id (patient_id), KEY status (status), KEY reconfirmation_called_for_date (reconfirmation_called_for_date)) $c;";
		// Active reservations live separately so a cancelled appointment keeps its audit row but immediately frees its serial.
		$sql[] = "CREATE TABLE {$t['appointment_slots']} (doctor_id BIGINT UNSIGNED NOT NULL, appointment_date DATE NOT NULL, serial_number INT NOT NULL, appointment_id BIGINT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (doctor_id, appointment_date, serial_number), KEY appointment_id (appointment_id)) ENGINE=InnoDB $c;";
		$sql[] = "CREATE TABLE {$t['waiting_list']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, doctor_id BIGINT UNSIGNED NOT NULL, patient_id BIGINT UNSIGNED NOT NULL, appointment_date DATE NOT NULL, queue_number INT NOT NULL, status ENUM('waiting','promoted','cancelled') NOT NULL DEFAULT 'waiting', promoted_appointment_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY doctor_date (doctor_id, appointment_date), KEY patient_id (patient_id), KEY status (status)) $c;";
		$sql[] = "CREATE TABLE {$t['sms_logs']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, recipient VARCHAR(20) NOT NULL, message TEXT NOT NULL, api_response_code VARCHAR(10) DEFAULT '', api_response_body TEXT NULL, appointment_id BIGINT UNSIGNED NULL, sent_by VARCHAR(50) NOT NULL DEFAULT 'system', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY recipient (recipient), KEY appointment_id (appointment_id)) $c;";
		$sql[] = "CREATE TABLE {$t['otp_logs']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, mobile VARCHAR(20) NOT NULL, otp_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempts INT NOT NULL DEFAULT 0, verified TINYINT(1) NOT NULL DEFAULT 0, ip_address VARCHAR(45) DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY mobile (mobile), KEY ip_address (ip_address), KEY expires_at (expires_at)) $c;";
		$sql[] = "CREATE TABLE {$t['messages']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, patient_id BIGINT UNSIGNED NOT NULL, direction ENUM('patient_to_chamber','chamber_to_patient') NOT NULL, message TEXT NOT NULL, attachment_url TEXT NULL, attachment_path TEXT NULL, attachment_name VARCHAR(255) DEFAULT '', attachment_mime VARCHAR(120) DEFAULT '', attachment_size BIGINT UNSIGNED NOT NULL DEFAULT 0, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY patient_id (patient_id), KEY is_read (is_read), KEY created_at (created_at)) $c;";
		foreach ( $sql as $statement ) { dbDelta( $statement ); }
	}

	public static function drop_tables() {
		global $wpdb;
		foreach ( array_reverse( self::get_tables() ) as $table ) { $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); }
		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_VERSION );
	}

	public static function get_portal_page_definitions() {
		return array(
			'portal_login_page_id'        => array( 'title' => __( 'Patient Login', 'cas' ),      'slug' => 'patient-login',      'shortcode' => '[cas_patient_login]' ),
			'portal_dashboard_page_id'    => array( 'title' => __( 'Patient Dashboard', 'cas' ),  'slug' => 'patient-dashboard',  'shortcode' => '[cas_patient_dashboard]' ),
			'portal_booking_page_id'      => array( 'title' => __( 'Book Appointment', 'cas' ),   'slug' => 'book-appointment',   'shortcode' => '[cas_book_appointment]' ),
			'portal_appointments_page_id' => array( 'title' => __( 'My Appointments', 'cas' ),    'slug' => 'my-appointments',    'shortcode' => '[cas_my_appointments]' ),
			'portal_messages_page_id'     => array( 'title' => __( 'Patient Messages', 'cas' ),   'slug' => 'patient-messages',   'shortcode' => '[cas_messages]' ),
		);
	}

	public static function get_default_options() {
		return array(
			'brand_name' => 'Chamber Appointment System', 'portal_login_page_id' => 0, 'portal_dashboard_page_id' => 0, 'portal_booking_page_id' => 0, 'portal_appointments_page_id' => 0, 'portal_messages_page_id' => 0, 'items_per_page' => 20, 'currency' => 'BDT',
			'sms_enabled' => 0, 'sms_api_url' => 'http://bulksmsbd.net/api/smsapi', 'sms_balance_url' => '', 'sms_api_key' => '', 'sms_senderid' => '',
			'sms_templates' => array(
				'otp_template' => 'Your {brand} OTP is {otp}',
				'booking_confirmation' => 'Dear {patient_name}, your appointment serial #{serial} with {doctor_name} on {date} at {reporting_time} is confirmed.',
				'reconfirmation' => 'Dear {patient_name}, your appointment on {date} serial #{serial} has been reconfirmed.',
				'cancellation' => 'Dear {patient_name}, your appointment on {date} has been cancelled.',
				'waiting_list_joined' => 'Dear {patient_name}, you are #{queue_number} on the waiting list for {date}.',
				'waiting_list_promoted' => 'Dear {patient_name}, great news! Your waiting list request for {date} has been confirmed. Serial #{serial} at {reporting_time}.',
			),
			'application_mode' => 'live', 'otp_digits' => 6, 'otp_expiry_minutes' => 10, 'otp_resend_cooldown_seconds' => 60, 'otp_max_attempts' => 5, 'otp_lockout_minutes' => 15, 'otp_ip_hourly_limit' => 10, 'email_otp_enabled' => 1,
			// Appointment setup: multiple doctors is the legacy/default behavior.
			// In single-doctor mode all public/mobile booking requests are safely
			// pinned to this configured doctor ID, even if a request is modified.
			'appointment_mode' => 'multiple',
			'single_doctor_id' => 0,
			'single_doctor_show_specialty' => 1,
			'frontend_default_language' => 'bn',
			'availability_poll_seconds'  => 15,
			'patient_booking_window_days' => 30,
			'patient_appointment_modify_limit' => 2,
			'chamber_attendant_phone' => '',
			'appointment_email_notifications_enabled' => 1,
			'after_new_patient_registration' => 'booking',
		);
	}

	public static function get_settings() { return wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), self::get_default_options() ); }
	public static function get_option( $key, $default = null ) { $s = self::get_settings(); return array_key_exists( $key, $s ) ? $s[ $key ] : $default; }
	public static function add_default_options() { update_option( self::OPTION_SETTINGS, self::get_settings(), false ); }

	/** True only when the portal is configured as a solo/single-doctor chamber. */
	public static function is_single_doctor_mode() {
		return 'single' === sanitize_key( (string) self::get_option( 'appointment_mode', 'multiple' ) );
	}

	/** Get the active doctor explicitly selected for public bookings in single-doctor mode. */
	public static function get_single_booking_doctor() {
		if ( ! self::is_single_doctor_mode() ) { return null; }
		$doctor_id = absint( self::get_option( 'single_doctor_id', 0 ) );
		if ( ! $doctor_id ) { return null; }
		global $wpdb;
		$table = self::table( 'doctors' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1", $doctor_id ) );
	}

	/**
	 * Resolve a booking doctor ID securely. In single-doctor mode the submitted
	 * value is intentionally ignored so patient/mobile requests cannot book a
	 * different doctor by editing HTML or API data.
	 */
	public static function resolve_booking_doctor_id( $requested_doctor_id = 0 ) {
		if ( ! self::is_single_doctor_mode() ) { return absint( $requested_doctor_id ); }
		$doctor = self::get_single_booking_doctor();
		return $doctor ? absint( $doctor->id ) : 0;
	}
	public static function now() { return current_time( 'mysql' ); }
	public static function mysql_datetime( $timestamp = null ) { return date( 'Y-m-d H:i:s', null === $timestamp ? current_time( 'timestamp' ) : absint( $timestamp ) ); }
	public static function start_transaction() { global $wpdb; $wpdb->query( 'START TRANSACTION' ); }
	public static function commit() { global $wpdb; $wpdb->query( 'COMMIT' ); }
	public static function rollback() { global $wpdb; $wpdb->query( 'ROLLBACK' ); }

	public static function portal_page_content( $title, $shortcode ) {
		return "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">\n<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->\n</div>\n<!-- /wp:group -->";
	}

	public static function ensure_portal_pages( $repair_existing = false ) {
		$settings = self::get_settings();
		$created  = array();
		foreach ( self::get_portal_page_definitions() as $option_key => $page ) {
			$page_id = absint( $settings[ $option_key ] ?? 0 );
			$post    = $page_id ? get_post( $page_id ) : null;
			if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
				$existing = get_page_by_path( $page['slug'] );
				if ( $existing && 'trash' !== $existing->post_status ) {
					$page_id = absint( $existing->ID );
					$post    = $existing;
				} else {
					$page_id = wp_insert_post( array(
						'post_title'   => wp_strip_all_tags( $page['title'] ),
						'post_name'    => sanitize_title( $page['slug'] ),
						'post_content' => self::portal_page_content( $page['title'], $page['shortcode'] ),
						'post_status'  => 'publish',
						'post_type'    => 'page',
					), true );
					if ( is_wp_error( $page_id ) ) { continue; }
					$created[] = $page['title'];
					$post = get_post( $page_id );
				}
			}
			if ( $repair_existing && $post && false === strpos( (string) $post->post_content, $page['shortcode'] ) ) {
				wp_update_post( array( 'ID' => $page_id, 'post_content' => self::portal_page_content( $page['title'], $page['shortcode'] ) ) );
			}
			$settings[ $option_key ] = absint( $page_id );
		}
		update_option( self::OPTION_SETTINGS, $settings, false );
		return $created;
	}

	public static function normalize_mobile( $mobile ) {
		$mobile = ltrim( preg_replace( '/[^0-9+]/', '', trim( (string) $mobile ) ), '+' );
		if ( preg_match( '/^01[3-9][0-9]{8}$/', $mobile ) ) { return '88' . $mobile; }
		if ( preg_match( '/^8801[3-9][0-9]{8}$/', $mobile ) ) { return $mobile; }
		return false;
	}
	public static function is_valid_mobile( $mobile ) { return false !== self::normalize_mobile( $mobile ); }
	public static function get_patients_by_mobile( $mobile ) { global $wpdb; $mobile = self::normalize_mobile( $mobile ); if ( ! $mobile ) { return array(); } $t = self::table( 'patients' ); return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE mobile=%s AND is_active=1 ORDER BY id ASC", $mobile ) ); }
	public static function insert_sms_log( $recipient, $message, $code = '', $body = '', $appointment_id = null, $sent_by = 'system' ) { global $wpdb; $wpdb->insert( self::table( 'sms_logs' ), array( 'recipient' => sanitize_text_field( $recipient ), 'message' => sanitize_textarea_field( $message ), 'api_response_code' => sanitize_text_field( $code ), 'api_response_body' => sanitize_textarea_field( $body ), 'appointment_id' => $appointment_id ? absint( $appointment_id ) : null, 'sent_by' => sanitize_text_field( $sent_by ), 'created_at' => self::now() ), array( '%s','%s','%s','%s','%d','%s','%s' ) ); return absint( $wpdb->insert_id ); }

	/**
	 * Allowed file types for patient/chamber message attachments.
	 * Keep this list conservative to avoid executable or unsafe uploads.
	 */
	public static function message_attachment_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'heic'         => 'image/heic',
			'heif'         => 'image/heif',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'txt'          => 'text/plain',
		);
	}

	public static function message_attachment_allowed_extensions() {
		return array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt' );
	}

	/**
	 * Upload a single message attachment into wp-content/uploads/cas-message-attachments/YYYY/MM.
	 * Returns an empty array when no file was selected, WP_Error on invalid upload, or attachment metadata on success.
	 */
	public static function handle_message_attachment_upload( $field = 'attachment' ) {
		if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
			return array();
		}

		$file = $_FILES[ $field ];
		if ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === absint( $file['error'] ) ) {
			return array();
		}

		$upload_error_messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'Attachment is larger than the server upload limit.', 'cas' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'Attachment is larger than the form upload limit.', 'cas' ),
			UPLOAD_ERR_PARTIAL    => __( 'Attachment uploaded only partially. Please try again.', 'cas' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Server temporary upload folder is missing.', 'cas' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Server could not write the uploaded file.', 'cas' ),
			UPLOAD_ERR_EXTENSION  => __( 'Server blocked this uploaded file type.', 'cas' ),
		);
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== absint( $file['error'] ) ) {
			$error_code = absint( $file['error'] );
			$message    = isset( $upload_error_messages[ $error_code ] ) ? $upload_error_messages[ $error_code ] : __( 'Attachment upload failed. Please try again.', 'cas' );
			return new WP_Error( 'cas_attachment_upload_error', $message );
		}

		$max_size = min( 5 * MB_IN_BYTES, (int) wp_max_upload_size() );
		if ( ! empty( $file['size'] ) && absint( $file['size'] ) > $max_size ) {
			return new WP_Error( 'cas_attachment_too_large', sprintf( __( 'Attachment is too large. Maximum allowed size is %s.', 'cas' ), size_format( $max_size ) ) );
		}

		$original_name = sanitize_file_name( wp_unslash( $file['name'] ) );
		$extension     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( ! $extension || ! in_array( $extension, self::message_attachment_allowed_extensions(), true ) ) {
			return new WP_Error( 'cas_attachment_bad_type', __( 'This file type is not allowed. Please upload image, PDF, Word, Excel, or text file.', 'cas' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir_filter = function( $dirs ) {
			$time_subdir    = '/' . gmdate( 'Y/m', current_time( 'timestamp' ) );
			$subdir         = '/cas-message-attachments' . $time_subdir;
			$dirs['subdir'] = $subdir;
			$dirs['path']   = $dirs['basedir'] . $subdir;
			$dirs['url']    = $dirs['baseurl'] . $subdir;
			return $dirs;
		};

		add_filter( 'upload_dir', $upload_dir_filter );
		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				/*
				 * We validate the extension above and keep the allowed list conservative.
				 * Some phones/browsers submit valid images with application/octet-stream,
				 * which made the previous version silently fail for attachments.
				 */
				'test_type' => false,
				'mimes'     => self::message_attachment_mimes(),
			)
		);
		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'cas_attachment_upload_error', sanitize_text_field( $uploaded['error'] ) );
		}

		$mime = '';
		if ( ! empty( $uploaded['file'] ) ) {
			$check = wp_check_filetype( $uploaded['file'], self::message_attachment_mimes() );
			$mime  = ! empty( $check['type'] ) ? $check['type'] : ( ! empty( $uploaded['type'] ) ? $uploaded['type'] : '' );
		}

		return array(
			'attachment_url'  => esc_url_raw( $uploaded['url'] ),
			'attachment_path' => sanitize_text_field( $uploaded['file'] ),
			'attachment_name' => $original_name,
			'attachment_mime' => sanitize_text_field( $mime ),
			'attachment_size' => absint( $file['size'] ),
		);
	}

	/**
	 * Convert DB attachment columns into a small safe array for JSON/UI rendering.
	 */
	public static function message_attachment_for_display( $message_row ) {
		if ( empty( $message_row->attachment_url ) ) {
			return null;
		}
		$mime = isset( $message_row->attachment_mime ) ? sanitize_text_field( $message_row->attachment_mime ) : '';
		$name = isset( $message_row->attachment_name ) && $message_row->attachment_name ? $message_row->attachment_name : __( 'Attachment', 'cas' );
		return array(
			'url'      => esc_url_raw( $message_row->attachment_url ),
			'name'     => sanitize_file_name( $name ),
			'mime'     => $mime,
			'size'     => isset( $message_row->attachment_size ) ? absint( $message_row->attachment_size ) : 0,
			'is_image' => 0 === strpos( $mime, 'image/' ),
		);
	}

	/**
	 * Delete stored attachment files older than the supplied number of days.
	 * Message text is kept; only attachment metadata is cleared.
	 */
	public static function clear_message_attachments_older_than( $days = 30 ) {
		global $wpdb;
		$days   = max( 1, absint( $days ) );
		$cutoff = self::mysql_datetime( current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
		$table  = self::table( 'messages' );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, attachment_path FROM {$table} WHERE attachment_path<>'' AND attachment_path IS NOT NULL AND created_at < %s", $cutoff ) );
		if ( empty( $rows ) ) {
			return 0;
		}

		$upload_dir = wp_get_upload_dir();
		$base_dir   = realpath( $upload_dir['basedir'] );
		$ids        = array();

		foreach ( $rows as $row ) {
			$ids[] = absint( $row->id );
			$path  = isset( $row->attachment_path ) ? (string) $row->attachment_path : '';
			$real  = $path ? realpath( $path ) : false;
			if ( $real && $base_dir && 0 === strpos( $real, $base_dir ) && file_exists( $real ) ) {
				wp_delete_file( $real );
			}
		}

		$id_list = implode( ',', array_map( 'absint', array_unique( $ids ) ) );
		if ( $id_list ) {
			$wpdb->query( "UPDATE {$table} SET attachment_url='', attachment_path='', attachment_name='', attachment_mime='', attachment_size=0 WHERE id IN ({$id_list})" );
		}

		return count( array_unique( $ids ) );
	}



	/**
	 * Ensure patient table has all columns used by newer versions.
	 * Existing installations may have been activated before city/email/address/age-based
	 * profile fields were added, so we repair missing columns on plugin load and before
	 * patient create/update operations.
	 */
	public static function ensure_patient_schema() {
		global $wpdb;
		$table = self::table( 'patients' );
		if ( empty( $table ) ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::create_tables();
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$wanted = array(
			'full_name'     => "ALTER TABLE {$table} ADD full_name VARCHAR(190) NOT NULL DEFAULT '' AFTER id",
			'mobile'        => "ALTER TABLE {$table} ADD mobile VARCHAR(20) NOT NULL DEFAULT '' AFTER full_name",
			'date_of_birth' => "ALTER TABLE {$table} ADD date_of_birth DATE NULL AFTER mobile",
			'gender'        => "ALTER TABLE {$table} ADD gender VARCHAR(20) DEFAULT '' AFTER date_of_birth",
			'blood_group'   => "ALTER TABLE {$table} ADD blood_group VARCHAR(10) DEFAULT '' AFTER gender",
			'address'       => "ALTER TABLE {$table} ADD address TEXT NULL AFTER blood_group",
			'city'          => "ALTER TABLE {$table} ADD city VARCHAR(120) DEFAULT '' AFTER address",
			'email'         => "ALTER TABLE {$table} ADD email VARCHAR(190) DEFAULT '' AFTER city",
			'notes'         => "ALTER TABLE {$table} ADD notes TEXT NULL AFTER email",
			'is_active'     => "ALTER TABLE {$table} ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes",
			'created_at'    => "ALTER TABLE {$table} ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active",
			'updated_at'    => "ALTER TABLE {$table} ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at",
		);

		foreach ( $wanted as $column => $sql ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$wpdb->query( $sql );
			}
		}

		return true;
	}


	/**
	 * Ensure reconfirmation call tracking columns exist on older appointment tables.
	 * The call marker is tied to the appointment date, so a rescheduled appointment
	 * automatically needs a fresh reconfirmation call for its new date.
	 */
	public static function ensure_appointment_schema() {
		global $wpdb;
		$table = self::table( 'appointments' );
		if ( empty( $table ) ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::create_tables();
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$wanted = array(
			'is_vip'                        => "ALTER TABLE {$table} ADD is_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER reporting_time",
			'patient_modify_count'          => "ALTER TABLE {$table} ADD patient_modify_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER reporting_time",
			'reconfirmation_called_for_date' => "ALTER TABLE {$table} ADD reconfirmation_called_for_date DATE NULL AFTER checked_in_at",
			'reconfirmation_called_at'       => "ALTER TABLE {$table} ADD reconfirmation_called_at DATETIME NULL AFTER reconfirmation_called_for_date",
			'reconfirmation_called_by'       => "ALTER TABLE {$table} ADD reconfirmation_called_by BIGINT UNSIGNED NULL AFTER reconfirmation_called_at",
		);
		foreach ( $wanted as $column => $sql ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$wpdb->query( $sql );
			}
		}

		// dbDelta does not reliably expand ENUM definitions on existing tables.
		// Waiting-list promotion uses moved_from_waiting, so explicitly repair the
		// legacy status/source columns before an appointment is inserted.
		$wpdb->query( "ALTER TABLE {$table} MODIFY status ENUM('pending','confirmed','reconfirmed','cancelled','waiting_list','moved_from_waiting','checked_in','completed','no_show') NOT NULL DEFAULT 'pending'" );
		$wpdb->query( "ALTER TABLE {$table} MODIFY source ENUM('frontend','admin','waiting_list') NOT NULL DEFAULT 'frontend'" );

		$indexes = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 );
		if ( is_array( $indexes ) && ! in_array( 'reconfirmation_called_for_date', $indexes, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY reconfirmation_called_for_date (reconfirmation_called_for_date)" );
		}

		self::ensure_appointment_slot_schema();
		return true;
	}


	/**
	 * Upgrade legacy serial uniqueness to a dedicated active-slot reservation table.
	 * A legacy UNIQUE (doctor, date, serial) makes a cancelled row block rebooking;
	 * the reservation table keeps the hard constraint only while the slot is active.
	 */
	public static function ensure_appointment_slot_schema() {
		global $wpdb;
		$appointments = self::table( 'appointments' );
		$slots        = self::table( 'appointment_slots' );
		if ( ! $appointments || ! $slots ) { return false; }

		$slot_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $slots ) );
		if ( $slot_exists !== $slots ) {
			self::create_tables();
		}

		$index_rows = $wpdb->get_results( "SHOW INDEX FROM {$appointments}", ARRAY_A );
		$legacy_unique = false;
		$has_lookup = false;
		foreach ( (array) $index_rows as $index ) {
			if ( 'doctor_date_serial' === ( $index['Key_name'] ?? '' ) ) { $legacy_unique = true; }
			if ( 'doctor_date_serial_lookup' === ( $index['Key_name'] ?? '' ) ) { $has_lookup = true; }
		}
		if ( $legacy_unique ) {
			// This exact old index is removed only after the reservation table exists.
			$wpdb->query( "ALTER TABLE {$appointments} DROP INDEX doctor_date_serial" );
		}
		if ( ! $has_lookup ) {
			$wpdb->query( "ALTER TABLE {$appointments} ADD KEY doctor_date_serial_lookup (doctor_id, appointment_date, serial_number)" );
		}
		return true;
	}

	/** Build / repair active reservations during an explicit plugin upgrade, not every request. */
	public static function sync_appointment_slots() {
		global $wpdb;
		$appointments = self::table( 'appointments' );
		$slots        = self::table( 'appointment_slots' );
		if ( ! $appointments || ! $slots ) { return false; }
		self::ensure_appointment_slot_schema();
		$wpdb->query( "DELETE s FROM {$slots} s LEFT JOIN {$appointments} a ON a.id=s.appointment_id WHERE a.id IS NULL OR a.status='cancelled'" );
		$wpdb->query( "INSERT IGNORE INTO {$slots} (doctor_id, appointment_date, serial_number, appointment_id, created_at, updated_at) SELECT doctor_id, appointment_date, serial_number, id, created_at, updated_at FROM {$appointments} WHERE status<>'cancelled'" );
		return true;
	}

	/** Atomically reserve one active doctor/date/serial slot. */
	public static function reserve_appointment_slot( $doctor_id, $date, $serial, $appointment_id = 0 ) {
		global $wpdb;
		$table = self::table( 'appointment_slots' );
		if ( ! $table || ! absint( $doctor_id ) || ! absint( $serial ) || ! $date ) { return false; }
		$now = self::now();
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (doctor_id, appointment_date, serial_number, appointment_id, created_at, updated_at) VALUES (%d,%s,%d,%d,%s,%s)",
			absint( $doctor_id ), $date, absint( $serial ), absint( $appointment_id ), $now, $now
		) );
		return 1 === (int) $result;
	}

	/** Attach a reservation to the newly created / updated appointment row. */
	public static function bind_appointment_slot( $doctor_id, $date, $serial, $appointment_id ) {
		global $wpdb;
		return false !== $wpdb->update(
			self::table( 'appointment_slots' ),
			array( 'appointment_id' => absint( $appointment_id ), 'updated_at' => self::now() ),
			array( 'doctor_id' => absint( $doctor_id ), 'appointment_date' => $date, 'serial_number' => absint( $serial ) ),
			array( '%d', '%s' ), array( '%d', '%s', '%d' )
		);
	}

	/** Release a slot only if it is held by the expected appointment (when supplied). */
	public static function release_appointment_slot( $doctor_id, $date, $serial, $appointment_id = 0 ) {
		global $wpdb;
		$table = self::table( 'appointment_slots' );
		$where = array( 'doctor_id' => absint( $doctor_id ), 'appointment_date' => $date, 'serial_number' => absint( $serial ) );
		$format = array( '%d', '%s', '%d' );
		if ( absint( $appointment_id ) ) { $where['appointment_id'] = absint( $appointment_id ); $format[] = '%d'; }
		return false !== $wpdb->delete( $table, $where, $format );
	}

	/** Guard against records made outside the reservation API during older releases. */
	public static function slot_has_active_appointment( $doctor_id, $date, $serial, $exclude_appointment_id = 0 ) {
		global $wpdb;
		$table = self::table( 'appointments' );
		$sql = "SELECT id FROM {$table} WHERE doctor_id=%d AND appointment_date=%s AND serial_number=%d AND status<>'cancelled'";
		$args = array( absint( $doctor_id ), $date, absint( $serial ) );
		if ( absint( $exclude_appointment_id ) ) { $sql .= ' AND id<>%d'; $args[] = absint( $exclude_appointment_id ); }
		$sql .= ' LIMIT 1';
		return absint( $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) );
	}

	/**
	 * Ensure the message table has all attachment columns, even on older installs where
	 * WordPress did not rerun activation. This is intentionally lightweight and safe to
	 * call before message insert/render operations.
	 */
	public static function ensure_message_schema() {
		global $wpdb;
		$table = self::table( 'messages' );
		if ( empty( $table ) ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::create_tables();
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$missing_sql = array();
		$wanted = array(
			'attachment_url'  => "ALTER TABLE {$table} ADD attachment_url TEXT NULL AFTER message",
			'attachment_path' => "ALTER TABLE {$table} ADD attachment_path TEXT NULL AFTER attachment_url",
			'attachment_name' => "ALTER TABLE {$table} ADD attachment_name VARCHAR(255) DEFAULT '' AFTER attachment_path",
			'attachment_mime' => "ALTER TABLE {$table} ADD attachment_mime VARCHAR(120) DEFAULT '' AFTER attachment_name",
			'attachment_size' => "ALTER TABLE {$table} ADD attachment_size BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER attachment_mime",
			'is_read'         => "ALTER TABLE {$table} ADD is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER attachment_size",
		);

		foreach ( $wanted as $column => $sql ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$missing_sql[] = $sql;
			}
		}

		foreach ( $missing_sql as $sql ) {
			$wpdb->query( $sql );
		}

		return true;
	}

	/**
	 * Insert a message from patient or chamber. Returns inserted ID or WP_Error.
	 */
	public static function insert_message( $patient_id, $direction, $message = '', $attachment = array(), $is_read = 0 ) {
		global $wpdb;
		self::ensure_message_schema();

		$patient_id = absint( $patient_id );
		$direction  = sanitize_key( $direction );
		$message    = sanitize_textarea_field( (string) $message );
		$attachment = is_array( $attachment ) ? $attachment : array();

		if ( ! $patient_id ) {
			return new WP_Error( 'cas_message_no_patient', __( 'Please select a patient.', 'cas' ) );
		}
		if ( ! in_array( $direction, array( 'patient_to_chamber', 'chamber_to_patient' ), true ) ) {
			return new WP_Error( 'cas_message_bad_direction', __( 'Invalid message direction.', 'cas' ) );
		}
		if ( '' === trim( $message ) && empty( $attachment ) ) {
			return new WP_Error( 'cas_message_empty', __( 'Please write a message or attach a file.', 'cas' ) );
		}

		$row = array(
			'patient_id'       => $patient_id,
			'direction'        => $direction,
			'message'          => $message,
			'attachment_url'   => ! empty( $attachment['attachment_url'] ) ? esc_url_raw( $attachment['attachment_url'] ) : '',
			'attachment_path'  => ! empty( $attachment['attachment_path'] ) ? sanitize_text_field( $attachment['attachment_path'] ) : '',
			'attachment_name'  => ! empty( $attachment['attachment_name'] ) ? sanitize_file_name( $attachment['attachment_name'] ) : '',
			'attachment_mime'  => ! empty( $attachment['attachment_mime'] ) ? sanitize_text_field( $attachment['attachment_mime'] ) : '',
			'attachment_size'  => ! empty( $attachment['attachment_size'] ) ? absint( $attachment['attachment_size'] ) : 0,
			'is_read'          => absint( $is_read ),
			'created_at'       => self::now(),
		);

		$inserted = $wpdb->insert(
			self::table( 'messages' ),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			$error = $wpdb->last_error ? $wpdb->last_error : __( 'Database insert failed.', 'cas' );
			return new WP_Error( 'cas_message_insert_failed', $error );
		}

		return absint( $wpdb->insert_id );
	}

	public static function seed_default_doctor_schedule() { global $wpdb; $d = self::table( 'doctors' ); $s = self::table( 'schedules' ); $doctor_id = absint( $wpdb->get_var( "SELECT id FROM {$d} ORDER BY id ASC LIMIT 1" ) ); if ( ! $doctor_id ) { $wpdb->insert( $d, array( 'name'=>'Default Doctor','specialty'=>'General Physician','is_active'=>1,'created_at'=>self::now(),'updated_at'=>self::now() ), array( '%s','%s','%d','%s','%s' ) ); $doctor_id = absint( $wpdb->insert_id ); } if ( $doctor_id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$s} WHERE doctor_id=%d", $doctor_id ) ) ) { $wpdb->insert( $s, array( 'doctor_id'=>$doctor_id,'holidays'=>wp_json_encode(array()),'updated_at'=>self::now() ), array( '%d','%s','%s' ) ); } }
}
