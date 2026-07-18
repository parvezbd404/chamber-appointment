<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_Patient {
	public static function normalize_mobile( $mobile ) { return CAS_DB::normalize_mobile( $mobile ); }
	public static function is_valid_mobile( $mobile ) { return CAS_DB::is_valid_mobile( $mobile ); }

	public static function relation_options() { return array( 'Father', 'Mother', 'Child', 'Spouse', 'Other' ); }
	public static function blood_group_options() { return array( 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Others' ); }

	private static function date( $d ) {
		$d = sanitize_text_field( (string) $d );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { return null; }
		$p = explode( '-', $d );
		return checkdate( absint( $p[1] ), absint( $p[2] ), absint( $p[0] ) ) ? $d : null;
	}

	public static function calculate_dob_from_age( $age ) {
		$age = absint( $age );
		if ( $age < 0 || $age > 125 ) { return null; }
		return gmdate( 'Y-m-d', strtotime( '-' . $age . ' years', current_time( 'timestamp' ) ) );
	}

	public static function calculate_age( $date_of_birth ) {
		$date_of_birth = self::date( $date_of_birth );
		if ( ! $date_of_birth ) { return ''; }
		try {
			$dob = new DateTime( $date_of_birth );
			$now = new DateTime( current_time( 'Y-m-d' ) );
			return max( 0, (int) $dob->diff( $now )->y );
		} catch ( Exception $e ) {
			return '';
		}
	}

	private static function clean_gender( $gender ) {
		$gender = sanitize_key( $gender );
		return in_array( $gender, array( 'male', 'female', 'other' ), true ) ? $gender : null;
	}

	private static function clean_blood_group( $blood_group ) {
		$blood_group = sanitize_text_field( $blood_group );
		return in_array( $blood_group, self::blood_group_options(), true ) ? $blood_group : '';
	}

	private static function clean_relation( $relation ) {
		$relation = sanitize_text_field( $relation );
		return in_array( $relation, self::relation_options(), true ) ? $relation : '';
	}

	private static function dob_from_data( $data ) {
		if ( isset( $data['age'] ) && '' !== trim( (string) $data['age'] ) ) {
			return self::calculate_dob_from_age( $data['age'] );
		}
		return self::date( $data['date_of_birth'] ?? '' );
	}

	public static function create( $data ) {
		global $wpdb;
		CAS_DB::ensure_patient_schema();
		$name   = sanitize_text_field( $data['full_name'] ?? '' );
		$mobile = self::normalize_mobile( $data['mobile'] ?? '' );
		$dob    = self::dob_from_data( $data );

		if ( ! $name || ! $mobile ) { return new WP_Error( 'cas_patient_required', __( 'Patient name and valid mobile are required.', 'cas' ) ); }
		if ( isset( $data['require_demographics'] ) && $data['require_demographics'] ) {
			if ( ! $dob ) { return new WP_Error( 'cas_age_required', __( 'Age is required.', 'cas' ) ); }
			if ( ! self::clean_gender( $data['gender'] ?? '' ) ) { return new WP_Error( 'cas_gender_required', __( 'Gender is required.', 'cas' ) ); }
		}

		$row = array(
			'full_name'     => $name,
			'mobile'        => $mobile,
			'date_of_birth' => $dob,
			'gender'        => self::clean_gender( $data['gender'] ?? '' ),
			'blood_group'   => self::clean_blood_group( $data['blood_group'] ?? '' ),
			'address'       => sanitize_textarea_field( $data['address'] ?? '' ),
			'city'          => sanitize_text_field( $data['city'] ?? '' ),
			'email'         => sanitize_email( $data['email'] ?? '' ),
			'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
			'is_active'     => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			'created_at'    => CAS_DB::now(),
			'updated_at'    => CAS_DB::now(),
		);
		$ok = $wpdb->insert( CAS_DB::table( 'patients' ), $row, array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' ) );
		return $ok ? absint( $wpdb->insert_id ) : new WP_Error( 'cas_patient_create_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Could not create patient.', 'cas' ) );
	}

	public static function update( $id, $data ) {
		global $wpdb;
		CAS_DB::ensure_patient_schema();
		$id = absint( $id );
		if ( ! $id ) { return new WP_Error( 'cas_patient_invalid_id', __( 'Invalid patient ID.', 'cas' ) ); }

		$existing = self::get_by_id( $id );
		if ( ! $existing ) { return new WP_Error( 'cas_patient_not_found', __( 'Patient profile not found.', 'cas' ) ); }

		/*
		 * Age is entered by the user, but the plugin stores an internally calculated DOB.
		 * If age is left unchanged/blank, preserve the existing stored DOB instead of
		 * overwriting it with NULL. This prevents profile update failures on older rows.
		 */
		if ( isset( $data['age'] ) && '' !== trim( (string) $data['age'] ) ) {
			$dob = self::calculate_dob_from_age( $data['age'] );
		} elseif ( isset( $data['date_of_birth'] ) && '' !== trim( (string) $data['date_of_birth'] ) ) {
			$dob = self::date( $data['date_of_birth'] );
		} else {
			$dob = self::date( $existing->date_of_birth );
		}

		$name   = sanitize_text_field( $data['full_name'] ?? $existing->full_name );
		$mobile = self::normalize_mobile( $data['mobile'] ?? $existing->mobile );
		$gender = self::clean_gender( $data['gender'] ?? $existing->gender );

		$row = array(
			'full_name'     => $name,
			'mobile'        => $mobile,
			'date_of_birth' => $dob,
			'gender'        => $gender,
			'blood_group'   => self::clean_blood_group( $data['blood_group'] ?? $existing->blood_group ),
			'address'       => sanitize_textarea_field( $data['address'] ?? $existing->address ),
			'city'          => sanitize_text_field( $data['city'] ?? $existing->city ),
			'email'         => sanitize_email( $data['email'] ?? $existing->email ),
			'notes'         => sanitize_textarea_field( $data['notes'] ?? $existing->notes ),
			'is_active'     => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : absint( $existing->is_active ),
			'updated_at'    => CAS_DB::now(),
		);
		if ( ! $row['full_name'] || ! $row['mobile'] ) { return new WP_Error( 'cas_patient_required', __( 'Patient name and valid mobile are required.', 'cas' ) ); }
		if ( isset( $data['require_demographics'] ) && $data['require_demographics'] ) {
			if ( ! $row['date_of_birth'] ) { return new WP_Error( 'cas_age_required', __( 'Age is required.', 'cas' ) ); }
			if ( ! $row['gender'] ) { return new WP_Error( 'cas_gender_required', __( 'Gender is required.', 'cas' ) ); }
		}

		$updated = $wpdb->update( CAS_DB::table( 'patients' ), $row, array( 'id' => $id ), array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s' ), array( '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'cas_patient_update_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Could not update profile.', 'cas' ) );
		}
		return true;
	}

	public static function deactivate( $id ) {
		global $wpdb;
		return false !== $wpdb->update( CAS_DB::table( 'patients' ), array( 'is_active' => 0, 'updated_at' => CAS_DB::now() ), array( 'id' => absint( $id ) ), array( '%d', '%s' ), array( '%d' ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( CAS_DB::table( 'patients' ), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function get_by_id( $id ) { global $wpdb; CAS_DB::ensure_patient_schema(); $t = CAS_DB::table( 'patients' ); return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", absint( $id ) ) ); }
	public static function get_by_mobile( $mobile ) { return CAS_DB::get_patients_by_mobile( $mobile ); }

	public static function search( $args = array() ) {
		global $wpdb;
		CAS_DB::ensure_patient_schema();
		$args = wp_parse_args( $args, array( 'search' => '', 'is_active' => null, 'limit' => 20, 'offset' => 0, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$t=CAS_DB::table('patients'); $where='1=1'; $v=array();
		if ( $args['search'] !== '' ) { $like='%'.$wpdb->esc_like( sanitize_text_field( $args['search'] ) ).'%'; $where.=' AND (full_name LIKE %s OR mobile LIKE %s OR email LIKE %s)'; $v=array($like,$like,$like); }
		if ( $args['is_active'] !== null && $args['is_active'] !== '' ) { $where.=' AND is_active=%d'; $v[]=absint($args['is_active']); }
		$limit=max(1,min(500,absint($args['limit']))); $offset=absint($args['offset']); $v[]=$limit; $v[]=$offset;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$where} ORDER BY full_name ASC LIMIT %d OFFSET %d", $v ) );
	}

	public static function get_family_members( $primary_id, $include_inactive = false ) { global $wpdb; $t=CAS_DB::table('family_members'); return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE primary_id=%d" . ( $include_inactive ? '' : ' AND is_active=1' ) . " ORDER BY full_name ASC", absint($primary_id))); }
	public static function get_family_member( $id ) { global $wpdb; $t = CAS_DB::table( 'family_members' ); return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", absint( $id ) ) ); }

	public static function add_family_member( $primary_id, $data ) {
		global $wpdb;
		$row=array(
			'primary_id'=>absint($primary_id),
			'full_name'=>sanitize_text_field($data['full_name']??''),
			'relation'=>self::clean_relation($data['relation']??''),
			'date_of_birth'=>self::dob_from_data($data),
			'gender'=>self::clean_gender($data['gender']??''),
			'blood_group'=>self::clean_blood_group($data['blood_group']??''),
			'notes'=>sanitize_textarea_field($data['notes']??''),
			'is_active'=>isset($data['is_active'])?absint($data['is_active']):1,
			'created_at'=>CAS_DB::now(),
			'updated_at'=>CAS_DB::now(),
		);
		if(!$row['primary_id']||!$row['full_name']||!$row['relation']){ return new WP_Error('cas_family_required',__('Primary patient, name, and relation are required.','cas')); }
		$ok=$wpdb->insert(CAS_DB::table('family_members'),$row,array('%d','%s','%s','%s','%s','%s','%s','%d','%s','%s'));
		return $ok?absint($wpdb->insert_id):new WP_Error('cas_family_create_failed',__('Could not add family member.','cas'));
	}

	public static function update_family_member( $id, $data ) {
		global $wpdb;
		$id = absint( $id );
		$row = array(
			'primary_id'     => absint( $data['primary_id'] ?? 0 ),
			'full_name'      => sanitize_text_field( $data['full_name'] ?? '' ),
			'relation'       => self::clean_relation( $data['relation'] ?? '' ),
			'date_of_birth'  => self::dob_from_data( $data ),
			'gender'         => self::clean_gender( $data['gender'] ?? '' ),
			'blood_group'    => self::clean_blood_group( $data['blood_group'] ?? '' ),
			'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
			'is_active'      => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			'updated_at'     => CAS_DB::now(),
		);
		if ( ! $id || ! $row['primary_id'] || ! $row['full_name'] || ! $row['relation'] ) { return new WP_Error( 'cas_family_required', __( 'Primary patient, name, and relation are required.', 'cas' ) ); }
		return false !== $wpdb->update( CAS_DB::table( 'family_members' ), $row, array( 'id' => $id ), array( '%d','%s','%s','%s','%s','%s','%s','%d','%s' ), array( '%d' ) );
	}

	public static function get_linked_patient_id_from_family_note( $member ) {
		if ( ! $member || empty( $member->notes ) ) { return 0; }
		return preg_match( '/Linked patient profile ID:\s*(\d+)/', (string) $member->notes, $m ) ? absint( $m[1] ) : 0;
	}

	public static function deactivate_family_member( $id ) {
		global $wpdb;
		$member = self::get_family_member( $id );
		if ( ! $member ) { return false; }
		$linked = self::get_linked_patient_id_from_family_note( $member );
		if ( $linked ) { self::deactivate( $linked ); }
		return false !== $wpdb->update( CAS_DB::table( 'family_members' ), array( 'is_active' => 0, 'updated_at' => CAS_DB::now() ), array( 'id' => absint( $id ) ), array( '%d', '%s' ), array( '%d' ) );
	}

	public static function delete_family_member( $id, $delete_linked_patient = false ) {
		global $wpdb;
		$member = self::get_family_member( $id );
		if ( ! $member ) { return false; }
		$linked = self::get_linked_patient_id_from_family_note( $member );
		if ( $delete_linked_patient && $linked ) { self::delete( $linked ); }
		return false !== $wpdb->delete( CAS_DB::table( 'family_members' ), array( 'id' => absint( $id ) ), array( '%d' ) );
	}
}
