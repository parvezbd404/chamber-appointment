<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
global $wpdb;
foreach ( array( 'cas_messages','cas_otp_logs','cas_sms_logs','cas_waiting_list','cas_appointments','cas_schedules','cas_doctors','cas_patient_family_members','cas_patients' ) as $name ) { $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $name ); }
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'cas_' ) . '%', $wpdb->esc_like( '_transient_cas_' ) . '%', $wpdb->esc_like( '_transient_timeout_cas_' ) . '%' ) );
wp_clear_scheduled_hook( 'cas_cleanup_expired_otps' ); remove_role( 'chamber_manager' ); remove_role( 'cas_doctor' ); remove_role( 'chamber_attendant' ); remove_role( 'receptionist' );
$admin = get_role( 'administrator' ); if ( $admin ) { foreach ( array( 'manage_cas_settings','manage_cas_appointments','manage_cas_patients','manage_cas_reports','manage_cas_sms' ) as $cap ) { $admin->remove_cap( $cap ); } }
