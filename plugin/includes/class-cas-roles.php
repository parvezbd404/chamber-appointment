<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CAS_Roles {
	private static $caps = array( 'manage_cas_settings', 'manage_cas_appointments', 'manage_cas_patients', 'manage_cas_reports', 'manage_cas_sms' );

	public static function get_caps() { return self::$caps; }

	public static function add_roles() {
		$admin = get_role( 'administrator' );
		if ( $admin ) { foreach ( self::$caps as $cap ) { $admin->add_cap( $cap ); } }

		self::add_or_update_role( 'chamber_manager', __( 'Chamber Manager', 'cas' ), array( 'read', 'manage_cas_appointments', 'manage_cas_patients', 'manage_cas_reports', 'manage_cas_sms' ) );
		self::add_or_update_role( 'cas_doctor', __( 'Doctor', 'cas' ), array( 'read', 'manage_cas_appointments', 'manage_cas_patients', 'manage_cas_reports' ) );
		self::add_or_update_role( 'chamber_attendant', __( 'Chamber Attendant', 'cas' ), array( 'read', 'manage_cas_appointments', 'manage_cas_patients' ) );
		self::add_or_update_role( 'receptionist', __( 'Receptionist', 'cas' ), array( 'read', 'manage_cas_appointments', 'manage_cas_patients' ) );
	}

	public static function remove_roles() {
		remove_role( 'chamber_manager' );
		remove_role( 'cas_doctor' );
		remove_role( 'chamber_attendant' );
		remove_role( 'receptionist' );
		$admin = get_role( 'administrator' );
		if ( $admin ) { foreach ( self::$caps as $cap ) { $admin->remove_cap( $cap ); } }
	}

	private static function add_or_update_role( $role_key, $label, $caps ) {
		$role_caps = array();
		foreach ( $caps as $cap ) { $role_caps[ sanitize_key( $cap ) ] = true; }
		$role = get_role( $role_key );
		if ( ! $role ) { add_role( $role_key, $label, $role_caps ); return; }
		foreach ( $role_caps as $cap => $allowed ) { $role->add_cap( $cap ); }
		foreach ( self::$caps as $cap ) { if ( ! isset( $role_caps[ $cap ] ) ) { $role->remove_cap( $cap ); } }
	}
}
