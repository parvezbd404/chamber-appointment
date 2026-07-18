<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin Name: Chamber Appointment System
 * Description: Doctor chamber appointment management system with OTP login, SMS, waiting list, reports, and patient portal.
 * Version: 2.2.0
 * Author: Chamber Appointment System
 * Text Domain: cas
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

define( 'CAS_VERSION', '2.2.0' );
define( 'CAS_PLUGIN_FILE', __FILE__ );
define( 'CAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CAS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CAS_TEXT_DOMAIN', 'cas' );
define( 'CAS_MINIMUM_PHP_VERSION', '7.4' );

function cas_require_file( $relative_path ) {
	$file = CAS_PLUGIN_DIR . ltrim( $relative_path, '/' );
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

cas_require_file( 'includes/class-cas-db.php' );
cas_require_file( 'includes/class-cas-i18n.php' );
cas_require_file( 'includes/class-cas-roles.php' );

// Appointment-only build: diabetes-care components are intentionally excluded.
cas_require_file( 'includes/class-cas-mobile-api.php' );

cas_require_file( 'includes/class-cas-activator.php' );
cas_require_file( 'includes/class-cas-deactivator.php' );
cas_require_file( 'includes/class-cas-sms.php' );
cas_require_file( 'includes/class-cas-otp.php' );
cas_require_file( 'includes/class-cas-patient.php' );
cas_require_file( 'includes/class-cas-appointment.php' );
cas_require_file( 'includes/class-cas-waiting-list.php' );
cas_require_file( 'includes/class-cas-reports.php' );
cas_require_file( 'includes/class-cas-data-tools.php' );
cas_require_file( 'includes/class-cas-pdf.php' );
cas_require_file( 'admin/class-cas-admin.php' );
cas_require_file( 'public/class-cas-public.php' );

register_activation_hook( __FILE__, array( 'CAS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CAS_Deactivator', 'deactivate' ) );

function cas_load_textdomain() {
	load_plugin_textdomain( 'cas', false, dirname( CAS_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'cas_load_textdomain', 5 );

function cas_cleanup_expired_otps() {
	if ( class_exists( 'CAS_OTP' ) ) {
		CAS_OTP::cleanup_expired();
	}
}
add_action( 'cas_cleanup_expired_otps', 'cas_cleanup_expired_otps' );

function cas_maybe_upgrade() {
	if ( class_exists( 'CAS_Roles' ) ) {
		CAS_Roles::add_roles();
	}

	if ( class_exists( 'CAS_DB' ) ) {
		CAS_DB::ensure_patient_schema();
		CAS_DB::ensure_message_schema();
		CAS_DB::ensure_appointment_schema();
	}


	if ( class_exists( 'CAS_DB' ) && version_compare( (string) get_option( CAS_DB::OPTION_VERSION, '0' ), CAS_VERSION, '<' ) ) {
		CAS_DB::create_tables();
		CAS_DB::ensure_appointment_slot_schema();
		CAS_DB::sync_appointment_slots();
		// Portal pages are not auto-created during update to avoid frontend/admin fatal errors on restricted hosts.
		update_option( CAS_DB::OPTION_VERSION, CAS_VERSION, false );
	}
}
add_action( 'plugins_loaded', 'cas_maybe_upgrade', 12 );

function cas_force_register_roles() {
	if ( class_exists( 'CAS_Roles' ) ) {
		CAS_Roles::add_roles();
	}
}
add_action( 'admin_init', 'cas_force_register_roles', 1 );

function cas_run() {
	if ( class_exists( 'CAS_I18n' ) ) {
		CAS_I18n::register_hooks();
	}

	if ( is_admin() && class_exists( 'CAS_Admin' ) ) {
		$admin = new CAS_Admin();
		$admin->register_hooks();
	}
	if ( class_exists( 'CAS_Mobile_API' ) ) { CAS_Mobile_API::register_hooks(); }

	if ( class_exists( 'CAS_Public' ) ) {
		$public = new CAS_Public();
		$public->register_hooks();
	}
}
add_action( 'plugins_loaded', 'cas_run', 20 );
