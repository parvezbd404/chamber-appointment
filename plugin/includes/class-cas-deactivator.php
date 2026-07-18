<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class CAS_Deactivator {
	public static function deactivate() { CAS_Roles::remove_roles(); wp_clear_scheduled_hook( 'cas_cleanup_expired_otps' ); flush_rewrite_rules(); }
}
