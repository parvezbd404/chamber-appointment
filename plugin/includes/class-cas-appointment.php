<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_Appointment {
	public static $statuses = array( 'pending', 'confirmed', 'reconfirmed', 'cancelled', 'waiting_list', 'moved_from_waiting', 'checked_in', 'completed', 'no_show' );

	/** Statuses that still reserve an appointment for a patient. */
	public static $active_patient_statuses = array( 'pending', 'confirmed', 'reconfirmed', 'waiting_list', 'moved_from_waiting', 'checked_in' );

	private static function today() {
		return gmdate( 'Y-m-d', current_time( 'timestamp' ) );
	}

	public static function patient_booking_window_days() {
		return max( 1, min( 3650, absint( CAS_DB::get_option( 'patient_booking_window_days', 30 ) ) ) );
	}

	public static function patient_booking_max_date() {
		return gmdate( 'Y-m-d', strtotime( '+' . self::patient_booking_window_days() . ' days', current_time( 'timestamp' ) ) );
	}

	public static function patient_appointment_modify_limit() {
		return max( 0, min( 20, absint( CAS_DB::get_option( 'patient_appointment_modify_limit', 2 ) ) ) );
	}

	public static function validate_patient_booking_date( $date ) {
		$date = self::date( $date );
		if ( ! $date ) { return new WP_Error( 'cas_invalid_date', __( 'Please select a valid appointment date.', 'cas' ) ); }
		if ( $date < self::today() ) { return new WP_Error( 'cas_past_date', __( 'Past dates cannot be booked.', 'cas' ) ); }
		if ( $date > self::patient_booking_max_date() ) {
			return new WP_Error( 'cas_booking_window_exceeded', sprintf( __( 'Patients can book appointments only up to %d days in advance.', 'cas' ), self::patient_booking_window_days() ) );
		}
		return true;
	}

	/** An appointment stays active until its date has passed or reaches a terminal status. */
	public static function is_active_for_patient( $appointment ) {
		if ( ! $appointment || empty( $appointment->appointment_date ) || empty( $appointment->status ) ) { return false; }
		return $appointment->appointment_date >= self::today() && in_array( $appointment->status, self::$active_patient_statuses, true );
	}

	/**
	 * Patients may manage an active appointment only until the chamber has
	 * reconfirmed it. Reconfirmation means the chamber has completed the
	 * telephone confirmation workflow; after that, any date change or
	 * cancellation must be handled by a chamber manager.
	 */
	public static function is_patient_manageable( $appointment ) {
		$limit = self::patient_appointment_modify_limit();
		return $limit > 0 && self::is_active_for_patient( $appointment ) && ! in_array( $appointment->status, array( 'reconfirmed', 'checked_in' ), true ) && absint( $appointment->patient_modify_count ?? 0 ) < $limit;
	}

	/** Human-readable reason shown to patients when online changes are locked. */
	public static function patient_management_lock_message( $appointment ) {
		$limit = self::patient_appointment_modify_limit();
		$count = absint( $appointment->patient_modify_count ?? 0 );
		if ( 0 === $limit ) {
			$phone = sanitize_text_field( CAS_DB::get_option( 'chamber_attendant_phone', '' ) );
			return $phone ? sprintf( __( 'Online appointment changes are disabled. Please call the chamber attendant at %s for assistance.', 'cas' ), $phone ) : __( 'Online appointment changes are disabled. Please call the chamber attendant for assistance.', 'cas' );
		}
		if ( $appointment && $count >= $limit ) {
			$phone = sanitize_text_field( CAS_DB::get_option( 'chamber_attendant_phone', '' ) );
			return $phone ? sprintf( _n( 'You have already changed this appointment %1$d time. Please call the chamber attendant at %2$s for further changes.', 'You have already changed this appointment %1$d times. Please call the chamber attendant at %2$s for further changes.', $limit, 'cas' ), $limit, $phone ) : sprintf( _n( 'You have already changed this appointment %d time. Please call the chamber attendant for further changes.', 'You have already changed this appointment %d times. Please call the chamber attendant for further changes.', $limit, 'cas' ), $limit );
		}
		if ( $appointment && 'reconfirmed' === (string) $appointment->status ) {
			return __( 'This appointment has been reconfirmed by the chamber. To modify or cancel it, please call the chamber manager.', 'cas' );
		}
		return __( 'This appointment can no longer be changed online. Please contact the chamber.', 'cas' );
	}

	/** Get one active appointment for a patient; exclude its own row while editing. */
	public static function get_patient_active_appointment( $patient_id, $exclude_appointment_id = 0 ) {
		global $wpdb;
		$patient_id = absint( $patient_id );
		$exclude_appointment_id = absint( $exclude_appointment_id );
		if ( ! $patient_id ) { return null; }
		$table = CAS_DB::table( 'appointments' );
		$statuses = self::$active_patient_statuses;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql = "SELECT * FROM {$table} WHERE patient_id=%d AND appointment_date >= %s AND status IN ({$placeholders})";
		$args = array_merge( array( $patient_id, self::today() ), $statuses );
		if ( $exclude_appointment_id ) { $sql .= ' AND id <> %d'; $args[] = $exclude_appointment_id; }
		$sql .= ' ORDER BY appointment_date ASC, reporting_time ASC, id ASC LIMIT 1';
		return $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
	}

	private static function date( $date ) {
		$date = sanitize_text_field( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return ''; }
		$parts = explode( '-', $date );
		return checkdate( absint( $parts[1] ), absint( $parts[2] ), absint( $parts[0] ) ) ? $date : '';
	}

	public static function get_schedule( $doctor_id ) {
		global $wpdb;
		$table = CAS_DB::table( 'schedules' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d AND is_active=1 LIMIT 1", absint( $doctor_id ) ) );
	}

	/**
	 * Calculate a normal queue reporting time. When a date is supplied, the
	 * configured weekday break is inserted into the timeline, so normal serials
	 * after the break move later without losing their queue order. VIP bookings
	 * use an explicit reporting time and never call this method.
	 */
	public static function calculate_reporting_time( $serial, $schedule_or_doctor, $appointment_date = '' ) {
		$serial   = absint( $serial );
		$schedule = is_object( $schedule_or_doctor ) ? $schedule_or_doctor : self::get_schedule( $schedule_or_doctor );

		if ( ! $serial || ! $schedule ) {
			return new WP_Error( 'cas_reporting_error', __( 'Invalid reporting time data.', 'cas' ) );
		}

		$timestamp = strtotime( '2000-01-01 ' . $schedule->start_time ) + floor( ( $serial - 1 ) / max( 1, absint( $schedule->batch_size ) ) ) * max( 1, absint( $schedule->reporting_interval ) ) * MINUTE_IN_SECONDS;
		$date = self::date( $appointment_date );
		if ( $date && ! empty( $schedule->weekday_breaks ) ) {
			$breaks = json_decode( (string) $schedule->weekday_breaks, true );
			$day = (string) gmdate( 'w', strtotime( $date ) );
			$break = is_array( $breaks ) && isset( $breaks[ $day ] ) && is_array( $breaks[ $day ] ) ? $breaks[ $day ] : array();
			if ( ! empty( $break['enabled'] ) && preg_match( '/^\d{2}:\d{2}$/', (string) ( $break['start'] ?? '' ) ) && preg_match( '/^\d{2}:\d{2}$/', (string) ( $break['end'] ?? '' ) ) ) {
				$break_start = strtotime( '2000-01-01 ' . $break['start'] );
				$break_end   = strtotime( '2000-01-01 ' . $break['end'] );
				if ( $break_end > $break_start && $timestamp >= $break_start ) {
					$timestamp += ( $break_end - $break_start );
				}
			}
		}

		return gmdate( 'H:i:s', $timestamp );
	}

	private static function date_bookable( $schedule, $date ) {
		$dow      = gmdate( 'w', strtotime( $date ) );
		$days     = array_filter( array_map( 'trim', explode( ',', $schedule->active_days ) ) );
		$holidays = json_decode( (string) $schedule->holidays, true );
		$holidays = is_array( $holidays ) ? $holidays : array();

		if ( ! in_array( $dow, $days, true ) ) { return new WP_Error( 'cas_inactive_day', __( 'Selected date is not active.', 'cas' ) ); }
		if ( in_array( $date, $holidays, true ) ) { return new WP_Error( 'cas_holiday', __( 'Selected date is a holiday.', 'cas' ) ); }

		return true;
	}

	public static function get_available_serials( $doctor_id, $date, $exclude_appointment_id = 0 ) {
		global $wpdb;
		$date = self::date( $date );
		$schedule = self::get_schedule( $doctor_id );
		$exclude_appointment_id = absint( $exclude_appointment_id );
		if ( ! $schedule || ! $date ) { return new WP_Error( 'cas_serial_args', __( 'Doctor and date are required.', 'cas' ) ); }
		$check = self::date_bookable( $schedule, $date );
		if ( is_wp_error( $check ) ) { return $check; }

		$table = CAS_DB::table( 'appointment_slots' );
		$sql = "SELECT serial_number FROM {$table} WHERE doctor_id=%d AND appointment_date=%s";
		$args = array( absint( $doctor_id ), $date );
		if ( $exclude_appointment_id ) { $sql .= ' AND appointment_id<>%d'; $args[] = $exclude_appointment_id; }
		$booked = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
		return array_values( array_diff( range( 1, max( 1, absint( $schedule->daily_limit ) ) ), array_map( 'absint', $booked ) ) );
	}

	/**
	 * Admin slot map. Reservation rows are the source of truth for whether a slot
	 * is busy, so a cancelled appointment disappears from the map immediately.
	 */
	public static function get_slot_map( $doctor_id, $date ) {
		global $wpdb;
		$date     = self::date( $date );
		$schedule = self::get_schedule( $doctor_id );
		if ( ! $schedule || ! $date ) { return new WP_Error( 'cas_slot_args', __( 'Doctor and date are required.', 'cas' ) ); }
		$check = self::date_bookable( $schedule, $date );
		if ( is_wp_error( $check ) ) { return $check; }
		$slots = CAS_DB::table( 'appointment_slots' );
		$appointments = CAS_DB::table( 'appointments' );
		$patients = CAS_DB::table( 'patients' );
		$booked_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.serial_number,a.status,p.full_name patient_name,p.mobile patient_mobile FROM {$slots} s LEFT JOIN {$appointments} a ON a.id=s.appointment_id LEFT JOIN {$patients} p ON p.id=a.patient_id WHERE s.doctor_id=%d AND s.appointment_date=%s",
			absint( $doctor_id ), $date
		) );
		$booked = array();
		foreach ( $booked_rows as $row ) { $booked[ absint( $row->serial_number ) ] = $row; }
		$out = array();
		for ( $serial = 1; $serial <= max( 1, absint( $schedule->daily_limit ) ); $serial++ ) {
			$time = self::calculate_reporting_time( $serial, $schedule, $date );
			$is_booked = isset( $booked[ $serial ] );
			$out[] = array(
				'serial' => $serial,
				'reporting_time' => is_wp_error( $time ) ? '' : $time,
				'reporting_time_display' => is_wp_error( $time ) ? '' : self::format_time( $time ),
				'is_booked' => $is_booked,
				'status' => $is_booked ? $booked[ $serial ]->status : 'free',
				'patient_name' => $is_booked ? $booked[ $serial ]->patient_name : '',
				'patient_mobile' => $is_booked ? $booked[ $serial ]->patient_mobile : '',
			);
		}
		return array( 'date' => $date, 'doctor_id' => absint( $doctor_id ), 'slots' => $out );
	}

	public static function create( $data ) {
		global $wpdb;
		do_action( 'cas_before_booking', $data );

		$doctor   = absint( $data['doctor_id'] ?? 0 );
		$patient  = absint( $data['patient_id'] ?? 0 );
		$date     = self::date( $data['appointment_date'] ?? '' );
		$serial   = absint( $data['serial_number'] ?? 0 );
		$is_vip   = ! empty( $data['is_vip'] ) && 'admin' === sanitize_key( $data['source'] ?? 'frontend' );
		$status   = sanitize_key( $data['status'] ?? 'confirmed' );
		$source   = sanitize_key( $data['source'] ?? 'frontend' );
		$send_sms = array_key_exists( 'send_sms', (array) $data ) ? (bool) $data['send_sms'] : true;
		$allow_import_date = ! empty( $data['allow_import_date'] );
		$skip_patient_check = ! empty( $data['skip_patient_active_check'] );
		$status = in_array( $status, self::$statuses, true ) ? $status : 'confirmed';
		$is_cancelled = 'cancelled' === $status;

		if ( ! $doctor || ! $patient || ! $date || ( ! $is_vip && ! $serial ) ) { return new WP_Error( 'cas_booking_required', __( 'Doctor, patient, date, and serial are required.', 'cas' ) ); }
		$schedule = self::get_schedule( $doctor );
		if ( ! $allow_import_date && ! $schedule ) { return new WP_Error( 'cas_schedule_missing', __( 'No active schedule was found for this doctor.', 'cas' ) ); }
		if ( ! $allow_import_date ) {
			$check = self::date_bookable( $schedule, $date );
			if ( is_wp_error( $check ) ) { return $check; }
			if ( ! $is_vip && $serial > max( 1, absint( $schedule->daily_limit ) ) ) { return new WP_Error( 'cas_serial_unavailable', __( 'That serial is not available. Please refresh and choose another serial.', 'cas' ) ); }
		}

		CAS_DB::start_transaction();
		$reserved = false;
		if ( ! $skip_patient_check ) {
			$current = self::get_patient_active_appointment( $patient );
			if ( $current ) {
				CAS_DB::rollback();
				return new WP_Error( 'cas_patient_active_appointment', sprintf( __( '%1$s already has an active appointment on %2$s. Modify or cancel it before booking another one.', 'cas' ), ! empty( $current->patient_name ) ? $current->patient_name : __( 'This patient', 'cas' ), $current->appointment_date ), array( 'appointment_id' => absint( $current->id ) ) );
			}
		}

		if ( ! $is_cancelled && ! $is_vip ) {
			if ( ! $allow_import_date ) {
				$available = self::get_available_serials( $doctor, $date );
				if ( is_wp_error( $available ) || ! in_array( $serial, array_map( 'absint', (array) $available ), true ) ) {
					CAS_DB::rollback();
					return is_wp_error( $available ) ? $available : new WP_Error( 'cas_serial_unavailable', __( 'That serial was just booked by another user. Please refresh and choose another serial.', 'cas' ) );
				}
			}
			if ( CAS_DB::slot_has_active_appointment( $doctor, $date, $serial ) || ! CAS_DB::reserve_appointment_slot( $doctor, $date, $serial ) ) {
				CAS_DB::rollback();
				return new WP_Error( 'cas_serial_unavailable', __( 'That serial was just booked by another user. Please refresh and choose another serial.', 'cas' ) );
			}
			$reserved = true;
		}

		$time_from_data = sanitize_text_field( (string) ( $data['reporting_time'] ?? '' ) );
		$time = preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $time_from_data ) ? ( 5 === strlen( $time_from_data ) ? $time_from_data . ':00' : $time_from_data ) : ( $schedule && ! $is_vip ? self::calculate_reporting_time( $serial, $schedule, $date ) : null );
		if ( $is_vip && ! $time ) { CAS_DB::rollback(); return new WP_Error( 'cas_vip_time_required', __( 'Please enter a valid VIP appointment time.', 'cas' ) ); }
		$ok = $wpdb->insert(
			CAS_DB::table( 'appointments' ),
			array(
				'doctor_id'        => $doctor,
				'patient_id'       => $patient,
				'appointment_date' => $date,
				'serial_number'    => $is_vip ? 0 : $serial,
				'reporting_time'   => is_wp_error( $time ) ? null : $time,
				'is_vip'           => $is_vip ? 1 : 0,
				'status'           => $status,
				'booked_by'        => absint( $data['booked_by'] ?? 0 ),
				'source'           => in_array( $source, array( 'frontend', 'admin', 'waiting_list' ), true ) ? $source : 'frontend',
				'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
				'created_at'       => CAS_DB::now(),
				'updated_at'       => CAS_DB::now(),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			$first_error = $wpdb->last_error;

			// Older installations may still have a legacy ENUM definition that does
			// not accept moved_from_waiting. Repair the appointment schema and retry
			// once while the selected serial remains reserved by this transaction.
			CAS_DB::ensure_appointment_schema();
			$ok = $wpdb->insert(
				CAS_DB::table( 'appointments' ),
				array(
					'doctor_id'        => $doctor,
					'patient_id'       => $patient,
					'appointment_date' => $date,
					'serial_number'    => $is_vip ? 0 : $serial,
					'reporting_time'   => is_wp_error( $time ) ? null : $time,
					'is_vip'           => $is_vip ? 1 : 0,
					'status'           => $status,
					'booked_by'        => absint( $data['booked_by'] ?? 0 ),
					'source'           => in_array( $source, array( 'frontend', 'admin', 'waiting_list' ), true ) ? $source : 'frontend',
					'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
					'created_at'       => CAS_DB::now(),
					'updated_at'       => CAS_DB::now(),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( ! $ok ) {
				$retry_error = $wpdb->last_error;
				CAS_DB::rollback();
				if ( $reserved ) { CAS_DB::release_appointment_slot( $doctor, $date, $serial ); }
				$error_message = $retry_error ? $retry_error : ( $first_error ? $first_error : __( 'Could not create appointment.', 'cas' ) );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( 'CAS appointment insert failed: ' . $error_message ); }
				return new WP_Error( 'cas_booking_failed', $error_message );
			}
		}

		$id = absint( $wpdb->insert_id );
		if ( $reserved && ! CAS_DB::bind_appointment_slot( $doctor, $date, $serial, $id ) ) {
			CAS_DB::rollback();
			CAS_DB::release_appointment_slot( $doctor, $date, $serial );
			return new WP_Error( 'cas_booking_failed', __( 'Could not reserve the selected serial.', 'cas' ) );
		}
		CAS_DB::commit();

		do_action( 'cas_after_booking', $id, $data );
		if ( $send_sms && in_array( $status, array( 'pending', 'confirmed', 'moved_from_waiting' ), true ) ) { self::send_booking_sms( $id ); }
		return $id;
	}

	public static function update_status( $id, $status, $notes = '' ) {
		global $wpdb;
		$id     = absint( $id );
		$old    = self::get_by_id( $id );
		$status = sanitize_key( $status );
		if ( ! $old ) { return new WP_Error( 'cas_appointment_not_found', __( 'Appointment not found.', 'cas' ) ); }
		if ( ! in_array( $status, self::$statuses, true ) ) { return new WP_Error( 'cas_invalid_status', __( 'Invalid status.', 'cas' ) ); }

		$was_cancelled = 'cancelled' === (string) $old->status;
		$is_cancelled  = 'cancelled' === $status;
		$reserved = false;
		CAS_DB::start_transaction();
		if ( $was_cancelled && ! $is_cancelled && empty( $old->is_vip ) ) {
			if ( CAS_DB::slot_has_active_appointment( $old->doctor_id, $old->appointment_date, $old->serial_number, $id ) || ! CAS_DB::reserve_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $id ) ) {
				CAS_DB::rollback();
				return new WP_Error( 'cas_serial_unavailable', __( 'This serial has already been rebooked and cannot be restored.', 'cas' ) );
			}
			$reserved = true;
		}

		$data = array( 'status' => $status, 'updated_at' => CAS_DB::now() );
		$fmt  = array( '%s', '%s' );
		if ( 'checked_in' === $status && empty( $old->checked_in_at ) ) { $data['checked_in_at'] = CAS_DB::now(); $fmt[] = '%s'; }
		if ( '' !== trim( (string) $notes ) ) { $data['notes'] = sanitize_textarea_field( $notes ); $fmt[] = '%s'; }
		$ok = false !== $wpdb->update( CAS_DB::table( 'appointments' ), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
		if ( ! $ok ) {
			CAS_DB::rollback();
			if ( $reserved ) { CAS_DB::release_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $id ); }
			return new WP_Error( 'cas_status_update_failed', __( 'Could not update appointment status.', 'cas' ) );
		}
		if ( ! $was_cancelled && $is_cancelled && empty( $old->is_vip ) ) { CAS_DB::release_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $id ); }
		if ( $reserved ) { CAS_DB::bind_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $id ); }
		CAS_DB::commit();

		do_action( 'cas_status_changed', $id, $old->status, $status );
		$status_sms_result = self::send_status_sms( $id, $status );
		$status_sms_sent = false !== $status_sms_result && ! is_wp_error( $status_sms_result );
		self::send_change_notifications( $id, $old, self::get_by_id( $id ), $status_sms_sent );
		return true;
	}

	public static function cancel( $id, $notes = '' ) { return self::update_status( $id, 'cancelled', $notes ); }

	/** Modify the doctor/date/serial for a patient-owned active appointment. */
	public static function update_patient_booking( $appointment_id, $data = array() ) {
		global $wpdb;
		$appointment_id = absint( $appointment_id );
		$old = self::get_by_id( $appointment_id );
		if ( ! $old ) { return new WP_Error( 'cas_appointment_not_found', __( 'Appointment not found.', 'cas' ) ); }
		if ( ! self::is_patient_manageable( $old ) ) { return new WP_Error( 'cas_appointment_not_editable', self::patient_management_lock_message( $old ) ); }
		$doctor = absint( $data['doctor_id'] ?? $old->doctor_id );
		$date = self::date( $data['appointment_date'] ?? $old->appointment_date );
		$serial = absint( $data['serial_number'] ?? $old->serial_number );
		$notes = array_key_exists( 'notes', (array) $data ) ? sanitize_textarea_field( $data['notes'] ) : $old->notes;
		if ( ! $doctor || ! $date || ! $serial ) { return new WP_Error( 'cas_update_required', __( 'Doctor, date and serial are required.', 'cas' ) ); }
		$another = self::get_patient_active_appointment( absint( $old->patient_id ), $appointment_id );
		if ( $another ) { return new WP_Error( 'cas_patient_active_appointment', __( 'This patient already has another active appointment.', 'cas' ) ); }
		$schedule = self::get_schedule( $doctor );
		if ( ! $schedule ) { return new WP_Error( 'cas_schedule_missing', __( 'No active schedule was found for this doctor.', 'cas' ) ); }
		$available = self::get_available_serials( $doctor, $date, $appointment_id );
		if ( is_wp_error( $available ) || ! in_array( $serial, array_map( 'absint', (array) $available ), true ) ) {
			return is_wp_error( $available ) ? $available : new WP_Error( 'cas_serial_unavailable', __( 'That serial is no longer available. Please choose another serial.', 'cas' ) );
		}

		$same_slot = ! $is_vip && empty( $old->is_vip ) && absint( $old->doctor_id ) === $doctor && (string) $old->appointment_date === $date && absint( $old->serial_number ) === $serial;
		CAS_DB::start_transaction();
		$reserved = false;
		if ( ! $same_slot ) {
			if ( CAS_DB::slot_has_active_appointment( $doctor, $date, $serial, $appointment_id ) || ! CAS_DB::reserve_appointment_slot( $doctor, $date, $serial ) ) {
				CAS_DB::rollback();
				return new WP_Error( 'cas_serial_unavailable', __( 'That serial was just booked by another user. Please choose another serial.', 'cas' ) );
			}
			$reserved = true;
		}
		$time = self::calculate_reporting_time( $serial, $schedule, $date );
		$update_data = array( 'doctor_id' => $doctor, 'appointment_date' => $date, 'serial_number' => $serial, 'reporting_time' => is_wp_error( $time ) ? null : $time, 'patient_modify_count' => min( self::patient_appointment_modify_limit(), absint( $old->patient_modify_count ?? 0 ) + 1 ), 'notes' => $notes, 'updated_at' => CAS_DB::now() );
		$update_formats = array( '%d', '%s', '%d', '%s', '%d', '%s', '%s' );
		$updated = $wpdb->update( CAS_DB::table( 'appointments' ), $update_data, array( 'id' => $appointment_id ), $update_formats, array( '%d' ) );
		if ( false === $updated && false !== stripos( (string) $wpdb->last_error, 'patient_modify_count' ) ) {
			CAS_DB::ensure_appointment_schema();
			$updated = $wpdb->update( CAS_DB::table( 'appointments' ), $update_data, array( 'id' => $appointment_id ), $update_formats, array( '%d' ) );
		}
		if ( false === $updated ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( 'CAS appointment update failed: ' . $wpdb->last_error ); }
			CAS_DB::rollback();
			if ( $reserved ) { CAS_DB::release_appointment_slot( $doctor, $date, $serial ); }
			return new WP_Error( 'cas_update_failed', __( 'Could not update the appointment.', 'cas' ) );
		}
		if ( $reserved ) {
			CAS_DB::bind_appointment_slot( $doctor, $date, $serial, $appointment_id );
			if ( empty( $old->is_vip ) ) { CAS_DB::release_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $appointment_id ); }
		}
		CAS_DB::commit();
		$new_appointment = self::get_by_id( $appointment_id );
		do_action( 'cas_appointment_updated_by_patient', $appointment_id, $old, $new_appointment );
		self::send_change_notifications( $appointment_id, $old, $new_appointment );
		return $appointment_id;
	}

	/** Update a staff-made booking with the same atomic slot rules as frontend booking. */
	public static function update_admin_booking( $appointment_id, $data = array() ) {
		global $wpdb;
		$appointment_id = absint( $appointment_id );
		$old = self::get_by_id( $appointment_id );
		if ( ! $old ) { return new WP_Error( 'cas_appointment_not_found', __( 'Appointment not found.', 'cas' ) ); }
		$doctor = absint( $data['doctor_id'] ?? 0 );
		$patient = absint( $data['patient_id'] ?? 0 );
		$date = self::date( $data['appointment_date'] ?? '' );
		$serial = absint( $data['serial_number'] ?? 0 );
		$is_vip = ! empty( $data['is_vip'] );
		$vip_time = sanitize_text_field( (string) ( $data['reporting_time'] ?? '' ) );
		$status = sanitize_key( $data['status'] ?? $old->status );
		$notes = sanitize_textarea_field( $data['notes'] ?? '' );
		if ( ! $doctor || ! $patient || ! $date || ( ! $is_vip && ! $serial ) ) { return new WP_Error( 'cas_missing', __( 'Doctor, patient, date, and serial are required.', 'cas' ) ); }
		if ( ! in_array( $status, self::$statuses, true ) ) { return new WP_Error( 'cas_bad_status', __( 'Invalid status.', 'cas' ) ); }
		$target_active = 'cancelled' !== $status;
		$old_active = 'cancelled' !== (string) $old->status;
		$same_slot = ! $is_vip && empty( $old->is_vip ) && absint( $old->doctor_id ) === $doctor && (string) $old->appointment_date === $date && absint( $old->serial_number ) === $serial;
		$schedule = self::get_schedule( $doctor );
		if ( $target_active && ! $schedule ) { return new WP_Error( 'cas_schedule_missing', __( 'No active schedule was found for this doctor.', 'cas' ) ); }
		if ( $target_active && ! $is_vip && ( ! $same_slot || ! $old_active ) ) {
			$available = self::get_available_serials( $doctor, $date, $appointment_id );
			if ( is_wp_error( $available ) || ! in_array( $serial, array_map( 'absint', (array) $available ), true ) ) {
				return is_wp_error( $available ) ? $available : new WP_Error( 'cas_duplicate_serial', __( 'This serial is already booked for the selected doctor and date.', 'cas' ) );
			}
		}
		CAS_DB::start_transaction();
		$reserved = false;
		if ( $target_active && ! $is_vip && ( ! $same_slot || ! $old_active ) ) {
			if ( CAS_DB::slot_has_active_appointment( $doctor, $date, $serial, $appointment_id ) || ! CAS_DB::reserve_appointment_slot( $doctor, $date, $serial ) ) {
				CAS_DB::rollback();
				return new WP_Error( 'cas_duplicate_serial', __( 'This serial is already booked for the selected doctor and date.', 'cas' ) );
			}
			$reserved = true;
		}
		if ( $is_vip ) {
			if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $vip_time ) ) { CAS_DB::rollback(); return new WP_Error( 'cas_vip_time_required', __( 'Please enter a valid VIP appointment time.', 'cas' ) ); }
			$time = 5 === strlen( $vip_time ) ? $vip_time . ':00' : $vip_time;
		} else {
			$time = $schedule ? self::calculate_reporting_time( $serial, $schedule, $date ) : null;
		}
		$updated = $wpdb->update( CAS_DB::table( 'appointments' ), array( 'doctor_id' => $doctor, 'patient_id' => $patient, 'appointment_date' => $date, 'serial_number' => $is_vip ? 0 : $serial, 'reporting_time' => is_wp_error( $time ) ? null : $time, 'is_vip' => $is_vip ? 1 : 0, 'status' => $status, 'notes' => $notes, 'updated_at' => CAS_DB::now() ), array( 'id' => $appointment_id ), array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			CAS_DB::rollback();
			if ( $reserved ) { CAS_DB::release_appointment_slot( $doctor, $date, $serial ); }
			return new WP_Error( 'cas_update_failed', __( 'Could not update the appointment.', 'cas' ) );
		}
		if ( $reserved ) { CAS_DB::bind_appointment_slot( $doctor, $date, $serial, $appointment_id ); }
		if ( $old_active && empty( $old->is_vip ) && ( ! $target_active || ! $same_slot ) ) { CAS_DB::release_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $appointment_id ); }
		CAS_DB::commit();
		self::send_change_notifications( $appointment_id, $old, self::get_by_id( $appointment_id ) );
		return true;
	}

	/** Delete an appointment and release any active reservation. */
	public static function delete( $appointment_id ) {
		global $wpdb;
		$appointment_id = absint( $appointment_id );
		$old = self::get_by_id( $appointment_id );
		if ( ! $old ) { return false; }
		CAS_DB::start_transaction();
		$deleted = $wpdb->delete( CAS_DB::table( 'appointments' ), array( 'id' => $appointment_id ), array( '%d' ) );
		if ( false === $deleted ) { CAS_DB::rollback(); return false; }
		CAS_DB::release_appointment_slot( $old->doctor_id, $old->appointment_date, $old->serial_number, $appointment_id );
		CAS_DB::commit();
		return true;
	}


	

	/**
	 * Record that the chamber contacted

	/**
	 * Record that the chamber contacted this patient for the appointment's
	 * current date. The marker is intentionally date-bound: when the date is
	 * edited later, the old marker remains historical and does not count for the
	 * new date, so the next reconfirmation worklist asks for a fresh call.
	 */
	public static function mark_reconfirmation_called( $id, $called_by = 0 ) {
		global $wpdb;
		$id          = absint( $id );
		$called_by   = absint( $called_by );
		$appointment = self::get_by_id( $id );
		if ( ! $appointment ) {
			return new WP_Error( 'cas_appointment_not_found', __( 'Appointment not found.', 'cas' ) );
		}

		$ok = $wpdb->update(
			CAS_DB::table( 'appointments' ),
			array(
				'reconfirmation_called_for_date' => $appointment->appointment_date,
				'reconfirmation_called_at'       => CAS_DB::now(),
				'reconfirmation_called_by'       => $called_by ? $called_by : null,
				'updated_at'                     => CAS_DB::now(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'cas_call_status_failed', __( 'Could not save reconfirmation call status.', 'cas' ) );
		}

		return true;
	}

	public static function reconfirm( $id, $notes = '' ) { return self::update_status( $id, 'reconfirmed', $notes ); }
	public static function check_in( $id, $notes = '' ) { return self::update_status( $id, 'checked_in', $notes ); }
	public static function complete( $id, $notes = '' ) { return self::update_status( $id, 'completed', $notes ); }
	public static function no_show( $id, $notes = '' ) { return self::update_status( $id, 'no_show', $notes ); }

	public static function get_by_id( $id ) {
		global $wpdb;
		$a = CAS_DB::table( 'appointments' );
		$p = CAS_DB::table( 'patients' );
		$d = CAS_DB::table( 'doctors' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT a.*,p.full_name patient_name,p.mobile patient_mobile,p.email patient_email,d.name doctor_name,d.specialty doctor_specialty FROM {$a} a LEFT JOIN {$p} p ON p.id=a.patient_id LEFT JOIN {$d} d ON d.id=a.doctor_id WHERE a.id=%d", absint( $id ) ) );
	}

	public static function search( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'date' => '', 'date_from' => '', 'date_to' => '', 'doctor_id' => 0, 'doctor_ids' => array(), 'patient_id' => 0, 'status' => '', 'search' => '', 'serial' => 0, 'limit' => 50, 'offset' => 0, 'orderby' => 'appointment_date', 'order' => 'DESC' ) );
		$a    = CAS_DB::table( 'appointments' );
		$p    = CAS_DB::table( 'patients' );
		$d    = CAS_DB::table( 'doctors' );
		$w    = array( '1=1' );
		$v    = array();

		if ( absint( $args['doctor_id'] ) ) {
			$w[] = 'a.doctor_id=%d';
			$v[] = absint( $args['doctor_id'] );
		} else {
			$doctor_ids = array_values( array_filter( array_map( 'absint', (array) $args['doctor_ids'] ) ) );
			if ( ! empty( $doctor_ids ) ) {
				$w[] = 'a.doctor_id IN (' . implode( ',', array_fill( 0, count( $doctor_ids ), '%d' ) ) . ')';
				$v = array_merge( $v, $doctor_ids );
			}
		}
		if ( absint( $args['patient_id'] ) ) { $w[] = 'a.patient_id=%d'; $v[] = absint( $args['patient_id'] ); }
		if ( self::date( $args['date'] ) ) { $w[] = 'a.appointment_date=%s'; $v[] = self::date( $args['date'] ); }
		if ( self::date( $args['date_from'] ) ) { $w[] = 'a.appointment_date>=%s'; $v[] = self::date( $args['date_from'] ); }
		if ( self::date( $args['date_to'] ) ) { $w[] = 'a.appointment_date<=%s'; $v[] = self::date( $args['date_to'] ); }
		if ( $args['status'] ) { $w[] = 'a.status=%s'; $v[] = sanitize_key( $args['status'] ); }
		if ( absint( $args['serial'] ) ) { $w[] = 'a.serial_number=%d'; $v[] = absint( $args['serial'] ); }
		if ( $args['search'] ) { $like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%'; $w[] = '(p.full_name LIKE %s OR p.mobile LIKE %s OR d.name LIKE %s)'; array_push( $v, $like, $like, $like ); }

		$v[] = max( 1, min( 500, absint( $args['limit'] ) ) );
		$v[] = absint( $args['offset'] );

		return $wpdb->get_results( $wpdb->prepare( "SELECT a.*,p.full_name patient_name,p.mobile patient_mobile,p.email patient_email,d.name doctor_name,d.specialty doctor_specialty FROM {$a} a LEFT JOIN {$p} p ON p.id=a.patient_id LEFT JOIN {$d} d ON d.id=a.doctor_id WHERE " . implode( ' AND ', $w ) . " ORDER BY a.appointment_date DESC,a.serial_number ASC LIMIT %d OFFSET %d", $v ) );
	}

	public static function get_by_patient( $patient_id, $args = array() ) { $args['patient_id'] = absint( $patient_id ); return self::search( $args ); }
	public static function get_by_date_doctor( $date, $doctor_id, $args = array() ) { $args['date'] = self::date( $date ); $args['doctor_id'] = absint( $doctor_id ); return self::search( $args ); }

	public static function get_sms_placeholders( $appointment ) {
		return array(
			'patient_name'   => $appointment->patient_name ?? '',
			'serial'         => $appointment->serial_number ?? '',
			'date'           => $appointment->appointment_date ?? '',
			'reporting_time' => isset( $appointment->reporting_time ) ? self::format_time( $appointment->reporting_time ) : '',
			'doctor_name'    => $appointment->doctor_name ?? '',
			'queue_number'   => '',
		);
	}

	private static function send_booking_sms( $appointment_id ) {
		$appointment = self::get_by_id( $appointment_id );
		if ( ! $appointment || empty( $appointment->patient_mobile ) ) { return false; }
		return CAS_SMS::send_template( 'booking_confirmation', $appointment->patient_mobile, self::get_sms_placeholders( $appointment ), $appointment_id, 'appointment_booking' );
	}

	private static function send_status_sms( $appointment_id, $status ) {
		$appointment = self::get_by_id( $appointment_id );
		if ( ! $appointment || empty( $appointment->patient_mobile ) ) { return false; }

		$template = '';
		if ( 'confirmed' === $status ) { $template = 'booking_confirmation'; }
		if ( 'reconfirmed' === $status ) { $template = 'reconfirmation'; }
		if ( 'cancelled' === $status ) { $template = 'cancellation'; }
		if ( '' === $template ) { return false; }

		return CAS_SMS::send_template( $template, $appointment->patient_mobile, self::get_sms_placeholders( $appointment ), $appointment_id, 'appointment_status' );
	}


	/** Notify a patient when date, reporting time, serial, or status changes. */
	private static function send_change_notifications( $appointment_id, $old, $new, $status_sms_already_sent = false ) {
		if ( ! $old || ! $new ) { return false; }
		$changed = (string) $old->appointment_date !== (string) $new->appointment_date
			|| (string) $old->reporting_time !== (string) $new->reporting_time
			|| absint( $old->serial_number ) !== absint( $new->serial_number )
			|| (string) $old->status !== (string) $new->status;
		if ( ! $changed ) { return false; }

		$brand = sanitize_text_field( CAS_DB::get_option( 'brand_name', 'Chamber Appointment System' ) );
		$message = sprintf(
			__( '%1$s appointment update: %2$s with %3$s, serial #%4$d, reporting time %5$s. Status: %6$s.', 'cas' ),
			$brand, $new->appointment_date, $new->doctor_name, absint( $new->serial_number ), self::format_time( $new->reporting_time ), ucwords( str_replace( '_', ' ', (string) $new->status ) )
		);
		if ( ! $status_sms_already_sent && ! empty( $new->patient_mobile ) ) { CAS_SMS::send( $new->patient_mobile, $message, $appointment_id, 'appointment_change' ); }
		if ( absint( CAS_DB::get_option( 'appointment_email_notifications_enabled', 1 ) ) && ! empty( $new->patient_email ) && is_email( $new->patient_email ) ) {
			$subject = sprintf( __( '%s appointment updated', 'cas' ), $brand );
			wp_mail( sanitize_email( $new->patient_email ), $subject, $message );
		}
		return true;
	}

	private static function format_time( $time ) {
		$timestamp = strtotime( '2000-01-01 ' . $time );
		return $timestamp ? date_i18n( get_option( 'time_format' ), $timestamp ) : sanitize_text_field( $time );
	}
}
