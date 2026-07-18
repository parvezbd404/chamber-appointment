<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class CAS_Activator {
	public static function activate() {
		if ( version_compare( PHP_VERSION, CAS_MINIMUM_PHP_VERSION, '<' ) ) { deactivate_plugins( CAS_PLUGIN_BASENAME ); wp_die( esc_html__( 'Chamber Appointment System requires a newer PHP version.', 'cas' ) ); }
		CAS_DB::create_tables(); CAS_DB::add_default_options(); CAS_Roles::add_roles(); CAS_DB::seed_default_doctor_schedule(); update_option( CAS_DB::OPTION_VERSION, CAS_VERSION, false );
		if ( ! wp_next_scheduled( 'cas_cleanup_expired_otps' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cas_cleanup_expired_otps' ); }
		flush_rewrite_rules();
	}
}
