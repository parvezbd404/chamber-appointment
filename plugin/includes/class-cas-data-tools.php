<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Secure export/import helpers. No files are retained on the server after an import request. */
class CAS_Data_Tools {
	const MAX_IMPORT_BYTES = 5242880; // 5 MB.

	private static function scope_sql( $scope, $column, &$values ) {
		$doctor_id = absint( $scope['doctor_id'] ?? 0 );
		$doctor_ids = array_values( array_filter( array_map( 'absint', (array) ( $scope['doctor_ids'] ?? array() ) ) ) );
		if ( $doctor_id ) { $values[] = $doctor_id; return " AND {$column}=%d"; }
		if ( $doctor_ids ) { $values = array_merge( $values, $doctor_ids ); return " AND {$column} IN (" . implode( ',', array_fill( 0, count( $doctor_ids ), '%d' ) ) . ')'; }
		return '';
	}

	public static function appointment_records( $scope = array() ) {
		global $wpdb;
		$a = CAS_DB::table( 'appointments' ); $p = CAS_DB::table( 'patients' ); $d = CAS_DB::table( 'doctors' );
		$values = array(); $where = '1=1' . self::scope_sql( $scope, 'a.doctor_id', $values );
		$sql = "SELECT a.*,p.full_name patient_name,p.mobile patient_mobile,d.name doctor_name FROM {$a} a LEFT JOIN {$p} p ON p.id=a.patient_id LEFT JOIN {$d} d ON d.id=a.doctor_id WHERE {$where} ORDER BY a.appointment_date ASC,a.serial_number ASC,a.id ASC";
		return $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function patient_records( $scope = array(), $include_all = false ) {
		global $wpdb;
		$p = CAS_DB::table( 'patients' ); $a = CAS_DB::table( 'appointments' );
		if ( $include_all && CAS_Admin::can_view_all_doctors() ) {
			return $wpdb->get_results( "SELECT * FROM {$p} ORDER BY full_name ASC,id ASC", ARRAY_A );
		}
		$values = array(); $scope_sql = self::scope_sql( $scope, 'a.doctor_id', $values );
		$sql = "SELECT DISTINCT p.* FROM {$p} p INNER JOIN {$a} a ON a.patient_id=p.id WHERE 1=1{$scope_sql} ORDER BY p.full_name ASC,p.id ASC";
		return $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function csv_data( $dataset, $scope = array() ) {
		$dataset = sanitize_key( $dataset );
		if ( 'patients' === $dataset ) {
			$records = self::patient_records( $scope, true );
			$headers = array( 'ID', 'Full Name', 'Mobile', 'Date of Birth', 'Gender', 'Blood Group', 'Address', 'City', 'Email', 'Notes', 'Active', 'Created', 'Updated' );
			$rows = array();
			foreach ( $records as $r ) { $rows[] = array( $r['id'], $r['full_name'], $r['mobile'], $r['date_of_birth'], $r['gender'], $r['blood_group'], $r['address'], $r['city'], $r['email'], $r['notes'], $r['is_active'], $r['created_at'], $r['updated_at'] ); }
			return array( 'headers' => $headers, 'rows' => $rows, 'filename' => 'cas-patients.csv' );
		}
		$records = self::appointment_records( $scope );
		$headers = array( 'ID', 'Doctor ID', 'Doctor', 'Patient ID', 'Patient', 'Mobile', 'Date', 'Serial', 'Reporting Time', 'Status', 'Source', 'Notes', 'Created', 'Updated' );
		$rows = array();
		foreach ( $records as $r ) { $rows[] = array( $r['id'], $r['doctor_id'], $r['doctor_name'], $r['patient_id'], $r['patient_name'], $r['patient_mobile'], $r['appointment_date'], $r['serial_number'], $r['reporting_time'], $r['status'], $r['source'], $r['notes'], $r['created_at'], $r['updated_at'] ); }
		return array( 'headers' => $headers, 'rows' => $rows, 'filename' => 'cas-appointments.csv' );
	}

	public static function json_backup( $scope = array() ) {
		return array(
			'format' => 'cas-backup', 'schema_version' => 1, 'plugin_version' => defined( 'CAS_VERSION' ) ? CAS_VERSION : '', 'exported_at' => CAS_DB::now(),
			'patients' => self::patient_records( $scope, true ),
			'appointments' => self::appointment_records( $scope ),
		);
	}

	private static function valid_date( $date ) {
		$date = sanitize_text_field( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return ''; }
		$p = explode( '-', $date ); return checkdate( absint( $p[1] ), absint( $p[2] ), absint( $p[0] ) ) ? $date : '';
	}

	private static function value( $row, $keys, $default = '' ) {
		foreach ( (array) $keys as $key ) { if ( array_key_exists( $key, $row ) ) { return $row[ $key ]; } }
		return $default;
	}

	private static function upsert_patient( $row, &$patient_map, &$summary ) {
		$legacy_id = absint( self::value( $row, array( 'id', 'patient_id', 'legacy_patient_id' ) ) );
		$name = sanitize_text_field( self::value( $row, array( 'full_name', 'patient', 'patient_name' ) ) );
		$mobile = CAS_Patient::normalize_mobile( self::value( $row, array( 'mobile', 'patient_mobile' ) ) );
		if ( ! $name || ! $mobile ) { $summary['skipped']++; return 0; }
		$matches = CAS_Patient::get_by_mobile( $mobile );
		$local_id = ! empty( $matches ) ? absint( $matches[0]->id ) : 0;
		$data = array(
			'full_name' => $name, 'mobile' => $mobile, 'date_of_birth' => self::valid_date( self::value( $row, array( 'date_of_birth' ) ) ),
			'gender' => sanitize_key( self::value( $row, array( 'gender' ) ) ), 'blood_group' => sanitize_text_field( self::value( $row, array( 'blood_group' ) ) ),
			'address' => sanitize_textarea_field( self::value( $row, array( 'address' ) ) ), 'city' => sanitize_text_field( self::value( $row, array( 'city' ) ) ),
			'email' => sanitize_email( self::value( $row, array( 'email' ) ) ), 'notes' => sanitize_textarea_field( self::value( $row, array( 'notes' ) ) ),
			'is_active' => absint( self::value( $row, array( 'is_active', 'active' ), 1 ) ) ? 1 : 0,
		);
		$result = $local_id ? CAS_Patient::update( $local_id, $data ) : CAS_Patient::create( $data );
		if ( is_wp_error( $result ) || false === $result ) { $summary['skipped']++; return 0; }
		if ( ! $local_id ) { $local_id = absint( $result ); $summary['patients_created']++; } else { $summary['patients_updated']++; }
		if ( $legacy_id ) { $patient_map[ $legacy_id ] = $local_id; }
		$patient_map[ 'mobile:' . $mobile ] = $local_id;
		return $local_id;
	}

	private static function resolve_doctor( $row ) {
		global $wpdb;
		$doctors = CAS_DB::table( 'doctors' );
		$id = absint( self::value( $row, array( 'doctor_id' ) ) );
		if ( $id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$doctors} WHERE id=%d", $id ) ) ) { return $id; }
		$name = sanitize_text_field( self::value( $row, array( 'doctor', 'doctor_name' ) ) );
		if ( ! $name ) { return 0; }
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$doctors} WHERE name=%s LIMIT 1", $name ) ) );
	}

	private static function import_appointment( $row, &$patient_map, &$summary ) {
		$doctor_id = self::resolve_doctor( $row );
		$legacy_patient = absint( self::value( $row, array( 'patient_id', 'legacy_patient_id' ) ) );
		$mobile = CAS_Patient::normalize_mobile( self::value( $row, array( 'mobile', 'patient_mobile' ) ) );
		$patient_id = $legacy_patient && ! empty( $patient_map[ $legacy_patient ] ) ? absint( $patient_map[ $legacy_patient ] ) : ( $mobile && ! empty( $patient_map[ 'mobile:' . $mobile ] ) ? absint( $patient_map[ 'mobile:' . $mobile ] ) : 0 );
		if ( ! $patient_id && $mobile ) { $matches = CAS_Patient::get_by_mobile( $mobile ); if ( $matches ) { $patient_id = absint( $matches[0]->id ); } }
		$date = self::valid_date( self::value( $row, array( 'appointment_date', 'date' ) ) );
		$serial = absint( self::value( $row, array( 'serial_number', 'serial' ) ) );
		$status = sanitize_key( self::value( $row, array( 'status' ), 'confirmed' ) );
		if ( ! $doctor_id || ! $patient_id || ! $date || ! $serial || ! in_array( $status, CAS_Appointment::$statuses, true ) ) { $summary['skipped']++; return; }
		$result = CAS_Appointment::create( array(
			'doctor_id' => $doctor_id, 'patient_id' => $patient_id, 'appointment_date' => $date, 'serial_number' => $serial,
			'reporting_time' => sanitize_text_field( self::value( $row, array( 'reporting_time' ) ) ), 'status' => $status,
			'source' => 'admin', 'notes' => sanitize_textarea_field( self::value( $row, array( 'notes' ) ) ), 'send_sms' => false,
			'allow_import_date' => true, 'skip_patient_active_check' => true,
		) );
		if ( is_wp_error( $result ) ) { $summary['skipped']++; return; }
		$summary['appointments_restored']++;
	}

	public static function import_json( $raw ) {
		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) { return new WP_Error( 'cas_import_json', __( 'The JSON backup file is invalid.', 'cas' ) ); }
		$patients = isset( $payload['patients'] ) && is_array( $payload['patients'] ) ? $payload['patients'] : array();
		$appointments = isset( $payload['appointments'] ) && is_array( $payload['appointments'] ) ? $payload['appointments'] : array();
		if ( ! $patients && ! $appointments ) { return new WP_Error( 'cas_import_empty', __( 'The backup does not contain patients or appointments.', 'cas' ) ); }
		$summary = array( 'patients_created' => 0, 'patients_updated' => 0, 'appointments_restored' => 0, 'skipped' => 0 );
		$patient_map = array();
		foreach ( $patients as $row ) { if ( is_array( $row ) ) { self::upsert_patient( $row, $patient_map, $summary ); } }
		foreach ( $appointments as $row ) { if ( is_array( $row ) ) { self::import_appointment( $row, $patient_map, $summary ); } }
		return $summary;
	}

	public static function import_csv( $path, $type ) {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, array( 'patients', 'appointments' ), true ) ) { return new WP_Error( 'cas_import_type', __( 'Choose a valid CSV data set.', 'cas' ) ); }
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) { return new WP_Error( 'cas_import_open', __( 'Could not read the import file.', 'cas' ) ); }
		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) { fclose( $handle ); return new WP_Error( 'cas_import_headers', __( 'The CSV file does not contain a header row.', 'cas' ) ); }
		$keys = array(); foreach ( $headers as $header ) { $keys[] = sanitize_key( str_replace( ' ', '_', trim( (string) $header ) ) ); }
		$summary = array( 'patients_created' => 0, 'patients_updated' => 0, 'appointments_restored' => 0, 'skipped' => 0 );
		$patient_map = array();
		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			if ( ! array_filter( $values, static function( $v ) { return '' !== trim( (string) $v ); } ) ) { continue; }
			$row = array(); foreach ( $keys as $i => $key ) { $row[ $key ] = isset( $values[ $i ] ) ? $values[ $i ] : ''; }
			if ( 'patients' === $type ) { self::upsert_patient( $row, $patient_map, $summary ); }
			elseif ( 'appointments' === $type ) { self::import_appointment( $row, $patient_map, $summary ); }
		}
		fclose( $handle );
		return $summary;
	}
}
