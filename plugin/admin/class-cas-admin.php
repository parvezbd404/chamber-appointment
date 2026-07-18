<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CAS_Appointments_List_Table extends WP_List_Table {
	public function __construct() {
		parent::__construct( array( 'singular' => 'appointment', 'plural' => 'appointments', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'appointment_date' => __( 'Date', 'cas' ),
			'serial_number'    => __( 'Serial', 'cas' ),
			'reporting_time'   => __( 'Reporting Time', 'cas' ),
			'patient_name'     => __( 'Patient', 'cas' ),
			'patient_mobile'   => __( 'Mobile', 'cas' ),
			'doctor_name'      => __( 'Doctor', 'cas' ),
			'status'           => __( 'Current Status', 'cas' ),
			'reconfirmation_call' => __( 'Reconfirm Call', 'cas' ),
			'source'           => __( 'Source', 'cas' ),
		);
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="appointment_ids[]" value="' . esc_attr( absint( $item->id ) ) . '">';
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->{$column_name} ) ? esc_html( $item->{$column_name} ) : '';
	}


	protected function column_serial_number( $item ) {
		return ! empty( $item->is_vip ) ? '<strong>' . esc_html__( 'VIP', 'cas' ) . '</strong>' : esc_html( absint( $item->serial_number ) );
	}

	protected function column_reporting_time( $item ) {
		$ts = strtotime( '2000-01-01 ' . $item->reporting_time );
		return $ts ? esc_html( date_i18n( get_option( 'time_format' ), $ts ) ) : esc_html( $item->reporting_time );
	}

	protected function column_status( $item ) {
		$status_label = ucwords( str_replace( '_', ' ', (string) $item->status ) );
		return '<span class="cas-status-badge cas-status-' . esc_attr( $item->status ) . '" title="' . esc_attr__( 'Current appointment status', 'cas' ) . '">' . esc_html( $status_label ) . '</span><small class="cas-current-status-help">' . esc_html__( 'Current', 'cas' ) . '</small>';
	}


	/**
	 * Extra date-specific operational marker for chamber managers.
	 * It does not alter appointment status or send SMS. A rescheduled row will
	 * automatically show as Not called for the new date because the marker is
	 * stored against the date on which the call was made.
	 */
	protected function column_reconfirmation_call( $item ) {
		$filters       = CAS_Admin::get_appointment_list_filters();
		$filtered_date = isset( $filters['cas_date'] ) ? $filters['cas_date'] : '';

		if ( ! $filtered_date || $filtered_date !== $item->appointment_date ) {
			return '<span class="description">' . esc_html__( 'Filter by date to use call tracking.', 'cas' ) . '</span>';
		}

		if ( ! empty( $item->reconfirmation_called_for_date ) && $item->reconfirmation_called_for_date === $item->appointment_date ) {
			$when = ! empty( $item->reconfirmation_called_at ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->reconfirmation_called_at ) : '';
			$meta = $when ? '<small class="cas-call-meta">' . esc_html( sprintf( __( 'Called: %s', 'cas' ), $when ) ) . '</small>' : '';
			return '<span class="cas-call-badge">' . esc_html__( 'Called', 'cas' ) . '</span>' . $meta;
		}

		$args = array_merge(
			array(
				'action'         => 'cas_mark_reconfirmation_called',
				'appointment_id' => absint( $item->id ),
			),
			$filters
		);
		$url = wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'cas_mark_reconfirmation_called_' . absint( $item->id )
		);

		return '<a class="button button-small cas-mark-called-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Mark Called', 'cas' ) . '</a>';
	}

	protected function column_appointment_date( $item ) {
		$filters = CAS_Admin::get_appointment_list_filters();
		$edit_args = array_merge(
			array( 'page' => 'cas-add-appointment', 'appointment_id' => absint( $item->id ) ),
			CAS_Admin::appointment_return_query_args( $filters )
		);
		$edit_url = add_query_arg( $edit_args, admin_url( 'admin.php' ) );
		$delete_args = array_merge(
			array( 'action' => 'cas_delete_appointment', 'appointment_id' => absint( $item->id ) ),
			$filters
		);
		$actions = array(
			'edit'      => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'cas' ) . '</a>',
			'reconfirm' => $this->action_link( $item->id, 'reconfirm', __( 'Reconfirm', 'cas' ) ),
			'called'    => $this->reconfirmation_call_link( $item ),
			'cancel'    => $this->action_link( $item->id, 'cancel', __( 'Cancel', 'cas' ) ),
			'check_in'  => $this->action_link( $item->id, 'check_in', __( 'Check In', 'cas' ) ),
			'complete'  => $this->action_link( $item->id, 'complete', __( 'Complete', 'cas' ) ),
			'no_show'   => $this->action_link( $item->id, 'no_show', __( 'No Show', 'cas' ) ),
			'delete'    => '<a class="cas-danger-link" href="' . esc_url( wp_nonce_url( add_query_arg( $delete_args, admin_url( 'admin-post.php' ) ), 'cas_delete_appointment_' . absint( $item->id ) ) ) . '">' . esc_html__( 'Delete', 'cas' ) . '</a>',
		);

		return '<strong>' . esc_html( $item->appointment_date ) . '</strong>' . $this->row_actions( $actions );
	}

	private function action_link( $id, $action, $label ) {
		$args = array_merge(
			array(
				'action'         => 'cas_appointment_action',
				'cas_action'     => sanitize_key( $action ),
				'appointment_id' => absint( $id ),
			),
			CAS_Admin::get_appointment_list_filters()
		);
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'cas_appointment_action_' . absint( $id ) );

		return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}


	private function reconfirmation_call_link( $item ) {
		$filters = CAS_Admin::get_appointment_list_filters();
		if ( empty( $filters['cas_date'] ) || $filters['cas_date'] !== $item->appointment_date ) {
			return '';
		}
		if ( ! empty( $item->reconfirmation_called_for_date ) && $item->reconfirmation_called_for_date === $item->appointment_date ) {
			return '<span class="cas-row-called">' . esc_html__( 'Called', 'cas' ) . '</span>';
		}
		$args = array_merge(
			array( 'action' => 'cas_mark_reconfirmation_called', 'appointment_id' => absint( $item->id ) ),
			$filters
		);
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'cas_mark_reconfirmation_called_' . absint( $item->id ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Mark Called', 'cas' ) . '</a>';
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$selected_doctor = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
		$args     = array(
			'date'       => isset( $_GET['cas_date'] ) ? CAS_Admin::sanitize_date( wp_unslash( $_GET['cas_date'] ) ) : '',
			'doctor_id'  => $selected_doctor,
			'doctor_ids' => array(),
			'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'limit'      => $per_page,
			'offset'     => ( $paged - 1 ) * $per_page,
		);
		if ( ! CAS_Admin::can_view_all_doctors() ) {
			$allowed = CAS_Admin::get_current_user_allowed_doctor_ids();
			if ( $selected_doctor && ! in_array( $selected_doctor, $allowed, true ) ) { $args['doctor_id'] = 999999999; }
			if ( ! $selected_doctor ) { $args['doctor_ids'] = $allowed ? $allowed : array( 999999999 ); }
		}

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = CAS_Appointment::search( $args );
	}
}

class CAS_Admin {
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_doctor_access_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_doctor_access_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_doctor_access_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_doctor_access_fields' ) );

		$handlers = array(
			'cas_add_appointment'          => 'handle_add_appointment',
			'cas_appointment_action'       => 'handle_appointment_action',
			'cas_mark_reconfirmation_called' => 'handle_mark_reconfirmation_called',
			'cas_delete_appointment'       => 'handle_delete_appointment',
			'cas_bulk_appointment_status'  => 'handle_bulk_appointment_status',
			'cas_print_appointments'       => 'handle_print_appointments',
			'cas_export_csv'               => 'handle_export_csv',
			'cas_export_data'              => 'handle_export_data',
			'cas_import_data'              => 'handle_import_data',
			'cas_save_patient'             => 'handle_save_patient',
			'cas_deactivate_patient'       => 'handle_deactivate_patient',
			'cas_delete_patient'           => 'handle_delete_patient',
			'cas_save_family_member'       => 'handle_save_family_member',
			'cas_deactivate_family_member'=> 'handle_deactivate_family_member',
			'cas_delete_family_member'     => 'handle_delete_family_member',
			'cas_save_doctor'              => 'handle_save_doctor',
			'cas_deactivate_doctor'        => 'handle_deactivate_doctor',
			'cas_delete_doctor'            => 'handle_delete_doctor',
			'cas_save_schedule'            => 'handle_save_schedule',
			'cas_save_sms_settings'        => 'handle_save_sms_settings',
			'cas_test_sms'                 => 'handle_test_sms',
			'cas_save_otp_settings'        => 'handle_save_otp_settings',
			'cas_save_plugin_settings'     => 'handle_save_plugin_settings',
			'cas_create_portal_pages'    => 'handle_create_portal_pages',
			'cas_admin_add_waiting'       => 'handle_admin_add_waiting',
			'cas_promote_waiting'          => 'handle_promote_waiting',
			'cas_cancel_waiting'           => 'handle_cancel_waiting',
			'cas_reply_message'            => 'handle_reply_message',
			'cas_clear_old_message_attachments' => 'handle_clear_old_message_attachments',
		);

		foreach ( $handlers as $action => $method ) {
			add_action( 'admin_post_' . $action, array( $this, $method ) );
		}

		add_action( 'wp_ajax_cas_admin_get_available_serials', array( $this, 'ajax_get_available_serials' ) );
		add_action( 'wp_ajax_cas_admin_test_sms', array( $this, 'ajax_test_sms' ) );
		add_action( 'wp_ajax_cas_admin_promote_waiting', array( $this, 'ajax_promote_waiting' ) );
		add_action( 'wp_ajax_cas_admin_check_balance', array( $this, 'ajax_check_balance' ) );
		add_action( 'wp_ajax_cas_admin_get_slot_map', array( $this, 'ajax_get_slot_map' ) );
	}

	public function register_menus() {
		add_menu_page( __( 'Chamber Appointments', 'cas' ), __( 'Chamber', 'cas' ), 'manage_cas_appointments', 'cas-dashboard', array( $this, 'render_dashboard' ), 'dashicons-calendar-alt', 26 );
		$pages = array(
			'dashboard'         => array( 'Dashboard', 'manage_cas_appointments' ),
			'appointments'      => array( 'Appointments', 'manage_cas_appointments' ),
			'booking-desk'       => array( 'Booking Desk', 'manage_cas_appointments' ),
			'add-appointment'   => array( 'Manual Booking', 'manage_cas_appointments' ),
			'waiting-list'      => array( 'Waiting List', 'manage_cas_appointments' ),
			'patients'          => array( 'Patients', 'manage_cas_patients' ),
			'family-members'    => array( 'Family Members', 'manage_cas_patients' ),
			'doctors'           => array( 'Doctors/Chambers', 'manage_cas_settings' ),
			'schedule-settings' => array( 'Schedule Settings', 'manage_cas_settings' ),
			'sms-settings'      => array( 'SMS Settings', 'manage_cas_sms' ),
			'otp-settings'      => array( 'OTP Settings', 'manage_cas_settings' ),
			'reports'           => array( 'Reports', 'manage_cas_reports' ),
			'message-center'    => array( 'Message Center', 'manage_cas_patients' ),
			'tools-export'      => array( 'Tools/Export', 'manage_cas_reports' ),
			'plugin-settings'   => array( 'Plugin Settings', 'manage_cas_settings' ),
		);
		foreach ( $pages as $slug => $data ) {
			add_submenu_page( 'cas-dashboard', __( $data[0], 'cas' ), __( $data[0], 'cas' ), $data[1], 'cas-' . $slug, array( $this, 'render_' . str_replace( '-', '_', $slug ) ) );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'cas-' ) ) { return; }
		wp_enqueue_style( 'cas-admin', CAS_PLUGIN_URL . 'assets/css/cas-admin.css', array(), CAS_VERSION );
		wp_enqueue_script( 'cas-admin', CAS_PLUGIN_URL . 'assets/js/cas-admin.js', array(), CAS_VERSION, true );
		wp_localize_script( 'cas-admin', 'CASAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cas_admin_nonce' ),
			'i18n'    => array(
				'confirmAction'      => __( 'Are you sure?', 'cas' ),
				'loading'            => __( 'Loading...', 'cas' ),
				'selectDoctor'       => __( 'Please select a doctor.', 'cas' ),
				'selectDate'         => __( 'Please select an appointment date.', 'cas' ),
				'selectSerial'       => __( 'Please select a serial number.', 'cas' ),
				'vipTimeRequired'    => __( 'Please enter the VIP reporting time.', 'cas' ),
				'selectPatient'      => __( 'Please select an existing patient.', 'cas' ),
				'newPatientName'     => __( 'Please enter the new patient name.', 'cas' ),
				'newPatientMobile'   => __( 'Please enter a valid Bangladeshi mobile number.', 'cas' ),
				'invalidAge'         => __( 'Patient age must be between 0 and 125.', 'cas' ),
				'invalidEmail'       => __( 'Please enter a valid email address.', 'cas' ),
				'checkingBooking'    => __( 'Checking appointment details...', 'cas' ),
				'validationFailed'   => __( 'Could not validate the appointment. Please try again.', 'cas' ),
			),
		) );
	}

	public function admin_notices() {
		if ( empty( $_GET['cas_message'] ) ) { return; }
		$type = ( isset( $_GET['cas_status'] ) && 'error' === sanitize_key( wp_unslash( $_GET['cas_status'] ) ) ) ? 'error' : 'success';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['cas_message'] ) ) ) . '</p></div>';
	}

	public static function sanitize_date( $date ) {
		$date = sanitize_text_field( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return ''; }
		$parts = explode( '-', $date );
		return checkdate( absint( $parts[1] ), absint( $parts[2] ), absint( $parts[0] ) ) ? $date : '';
	}


	/**
	 * Return the current appointment-list filters in a safe, reusable form.
	 *
	 * The optional prefix is used by the edit form, which stores filters as
	 * return_cas_date, return_doctor_id, etc. This keeps a chamber manager in
	 * the same daily reconfirmation worklist after an action or edit.
	 *
	 * @param array|null $source Request source. Defaults to $_GET.
	 * @param string     $prefix Optional field-name prefix, e.g. 'return_'.
	 * @return array
	 */
	public static function get_appointment_list_filters( $source = null, $prefix = '' ) {
		$source = is_array( $source ) ? $source : $_GET;
		$prefix = sanitize_key( (string) $prefix );
		$filters = array();

		$date_key = $prefix . 'cas_date';
		if ( isset( $source[ $date_key ] ) ) {
			$date = self::sanitize_date( wp_unslash( $source[ $date_key ] ) );
			if ( $date ) { $filters['cas_date'] = $date; }
		}

		$doctor_key = $prefix . 'doctor_id';
		if ( isset( $source[ $doctor_key ] ) ) {
			$doctor_id = absint( $source[ $doctor_key ] );
			if ( $doctor_id ) { $filters['doctor_id'] = $doctor_id; }
		}

		$status_key = $prefix . 'status';
		if ( isset( $source[ $status_key ] ) ) {
			$status = sanitize_key( wp_unslash( $source[ $status_key ] ) );
			if ( $status && in_array( $status, CAS_Appointment::$statuses, true ) ) { $filters['status'] = $status; }
		}

		$search_key = $prefix . 's';
		if ( isset( $source[ $search_key ] ) ) {
			$search = sanitize_text_field( wp_unslash( $source[ $search_key ] ) );
			if ( '' !== $search ) { $filters['s'] = $search; }
		}

		$paged_key = $prefix . 'paged';
		if ( isset( $source[ $paged_key ] ) ) {
			$paged = max( 1, absint( $source[ $paged_key ] ) );
			if ( $paged > 1 ) { $filters['paged'] = $paged; }
		}

		return $filters;
	}

	/** Convert standard appointment filters to return_* field/query names. */
	public static function appointment_return_query_args( $filters ) {
		$return = array();
		foreach ( (array) $filters as $key => $value ) {
			if ( in_array( $key, array( 'cas_date', 'doctor_id', 'status', 's', 'paged' ), true ) ) {
				$return[ 'return_' . $key ] = $value;
			}
		}
		return $return;
	}

	private function view( $view, $cap = 'manage_cas_appointments' ) {
		if ( ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Permission denied.', 'cas' ) ); }
		$view = sanitize_file_name( (string) $view );
		if ( '.php' !== substr( $view, -4 ) ) { $view .= '.php'; }
		$file = CAS_PLUGIN_DIR . 'admin/views/' . $view;
		if ( ! file_exists( $file ) ) { wp_die( esc_html__( 'Admin view file not found.', 'cas' ) ); }
		require $file;
	}

	public function render_dashboard(){ $this->view( 'dashboard.php' ); }
	public function render_appointments(){ $this->view( 'appointments.php' ); }
	public function render_booking_desk(){ $this->view( 'booking-desk.php' ); }
	public function render_add_appointment(){ $this->view( 'add-appointment.php' ); }
	public function render_waiting_list(){ $this->view( 'waiting-list.php' ); }
	public function render_patients(){ $this->view( 'patients.php', 'manage_cas_patients' ); }
	public function render_family_members(){ $this->view( 'family-members.php', 'manage_cas_patients' ); }
	public function render_doctors(){ $this->view( 'doctors.php', 'manage_cas_settings' ); }
	public function render_schedule_settings(){ $this->view( 'schedule-settings.php', 'manage_cas_settings' ); }
	public function render_sms_settings(){ $this->view( 'sms-settings.php', 'manage_cas_sms' ); }
	public function render_otp_settings(){ $this->view( 'otp-settings.php', 'manage_cas_settings' ); }
	public function render_reports(){ $this->view( 'reports.php', 'manage_cas_reports' ); }
	public function render_message_center(){ $this->view( 'message-center.php', 'manage_cas_patients' ); }
	public function render_tools_export(){ $this->view( 'tools-export.php', 'manage_cas_reports' ); }
	public function render_plugin_settings(){ $this->view( 'plugin-settings.php', 'manage_cas_settings' ); }

	public function get_admin_doctors( $active = false ) {
		global $wpdb;
		$t = CAS_DB::table( 'doctors' );
		$where = $active ? 'WHERE is_active=1' : '';
		$values = array();
		if ( ! self::can_view_all_doctors() ) {
			$allowed = self::get_current_user_allowed_doctor_ids();
			if ( empty( $allowed ) ) { return array(); }
			$where .= ( $where ? ' AND ' : 'WHERE ' ) . 'id IN (' . implode( ',', array_fill( 0, count( $allowed ), '%d' ) ) . ')';
			$values = $allowed;
		}
		$sql = "SELECT * FROM {$t} {$where} ORDER BY is_active DESC,name";
		return empty( $values ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	public static function can_view_all_doctors( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$user = get_userdata( $user_id );
		if ( ! $user ) { return false; }
		if ( in_array( 'administrator', (array) $user->roles, true ) || in_array( 'chamber_manager', (array) $user->roles, true ) ) { return true; }
		return user_can( $user_id, 'manage_cas_settings' );
	}

	public static function get_current_user_allowed_doctor_ids( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( self::can_view_all_doctors( $user_id ) ) { return array(); }
		$ids = get_user_meta( $user_id, 'cas_assigned_doctor_ids', true );
		if ( ! is_array( $ids ) ) { $ids = array_filter( array_map( 'absint', explode( ',', (string) $ids ) ) ); }
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	public static function user_can_access_doctor( $doctor_id, $user_id = 0 ) {
		$doctor_id = absint( $doctor_id );
		if ( self::can_view_all_doctors( $user_id ) ) { return true; }
		return $doctor_id > 0 && in_array( $doctor_id, self::get_current_user_allowed_doctor_ids( $user_id ), true );
	}

	public static function scope_args_for_current_user( $args ) {
		$args = (array) $args;
		if ( self::can_view_all_doctors() ) { return $args; }
		$allowed = self::get_current_user_allowed_doctor_ids();
		$doctor_id = absint( $args['doctor_id'] ?? 0 );
		if ( $doctor_id && ! in_array( $doctor_id, $allowed, true ) ) { $args['doctor_id'] = 999999999; return $args; }
		if ( ! $doctor_id ) { $args['doctor_ids'] = $allowed ? $allowed : array( 999999999 ); }
		return $args;
	}

	private function appointment_is_accessible( $appointment_id ) {
		$appointment = CAS_Appointment::get_by_id( absint( $appointment_id ) );
		return $appointment && self::user_can_access_doctor( absint( $appointment->doctor_id ) );
	}

	private function waiting_is_accessible( $waiting_id ) {
		global $wpdb;
		$t = CAS_DB::table( 'waiting_list' );
		$doctor_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT doctor_id FROM {$t} WHERE id=%d", absint( $waiting_id ) ) ) );
		return $doctor_id && self::user_can_access_doctor( $doctor_id );
	}

	public function render_user_doctor_access_fields( $user ) {
		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_cas_settings' ) ) { return; }
		$doctors  = $this->get_all_doctors_unscoped();
		$assigned = get_user_meta( $user->ID, 'cas_assigned_doctor_ids', true );
		$assigned = is_array( $assigned ) ? array_map( 'absint', $assigned ) : array_filter( array_map( 'absint', explode( ',', (string) $assigned ) ) );
		?>
		<h2><?php echo esc_html__( 'Chamber Appointment Access', 'cas' ); ?></h2>
		<table class="form-table" role="presentation"><tr>
			<th><label><?php echo esc_html__( 'Assigned Doctor(s)', 'cas' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'cas_save_user_doctor_access', 'cas_user_doctor_access_nonce' ); ?>
				<?php foreach ( $doctors as $doctor ) : ?>
					<label style="display:block;margin:0 0 6px;"><input type="checkbox" name="cas_assigned_doctor_ids[]" value="<?php echo esc_attr( $doctor->id ); ?>" <?php checked( in_array( absint( $doctor->id ), $assigned, true ) ); ?>> <?php echo esc_html( $doctor->name . ( $doctor->specialty ? ' — ' . $doctor->specialty : '' ) ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php echo esc_html__( 'Doctors, chamber attendants, and receptionists only see appointments, waiting lists, reports, and booking options for these assigned doctor(s). Administrators and chamber managers can see all doctors.', 'cas' ); ?></p>
			</td>
		</tr></table>
		<?php
	}

	public function save_user_doctor_access_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }
		if ( empty( $_POST['cas_user_doctor_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cas_user_doctor_access_nonce'] ) ), 'cas_save_user_doctor_access' ) ) { return; }
		$ids = isset( $_POST['cas_assigned_doctor_ids'] ) && is_array( $_POST['cas_assigned_doctor_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['cas_assigned_doctor_ids'] ) ) : array();
		update_user_meta( $user_id, 'cas_assigned_doctor_ids', array_values( array_unique( array_filter( $ids ) ) ) );
	}

	private function get_all_doctors_unscoped() {
		global $wpdb;
		$t = CAS_DB::table( 'doctors' );
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY is_active DESC,name" );
	}

	public function get_active_patients( $limit = 500 ) {
		return CAS_Patient::search( array( 'is_active' => 1, 'limit' => absint( $limit ) ) );
	}

	private function redirect( $page, $message, $error = false, $extra = array() ) {
		$args = array_merge( array( 'page' => $page, 'cas_message' => rawurlencode( $message ), 'cas_status' => $error ? 'error' : 'success' ), $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_add_appointment() {
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_die(); }
		check_admin_referer( 'cas_add_appointment' );
		$id             = absint( $_POST['appointment_id'] ?? 0 );
		$doctor_id      = absint( $_POST['doctor_id'] ?? 0 );
		$patient_id     = absint( $_POST['patient_id'] ?? 0 );
		$date           = self::sanitize_date( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		$serial         = absint( $_POST['serial_number'] ?? 0 );
		$is_vip         = ! empty( $_POST['is_vip'] );
		$vip_time       = sanitize_text_field( wp_unslash( $_POST['vip_reporting_time'] ?? '' ) );
		$status         = sanitize_key( wp_unslash( $_POST['status'] ?? 'confirmed' ) );
		$patient_mode   = isset( $_POST['patient_mode'] ) ? sanitize_key( wp_unslash( $_POST['patient_mode'] ) ) : 'existing';
		$notes          = sanitize_textarea_field( wp_unslash( ( 'new' === $patient_mode ? ( $_POST['new_notes'] ?? '' ) : ( $_POST['existing_notes'] ?? '' ) ) ) );
		$return_filters = self::get_appointment_list_filters( $_POST, 'return_' );
		$return_query   = self::appointment_return_query_args( $return_filters );

		if ( ! self::user_can_access_doctor( $doctor_id ) ) {
			$this->redirect( 'cas-add-appointment', __( 'You are not assigned to this doctor.', 'cas' ), true, array_merge( array( 'appointment_id' => $id ), $return_query ) );
		}

		if ( 'new' === $patient_mode ) {
			$patient_id = 0;
		}

		if ( ! $patient_id && ! empty( $_POST['new_patient_full_name'] ) ) {
			$patient_id = CAS_Patient::create( array(
				'full_name'   => sanitize_text_field( wp_unslash( $_POST['new_patient_full_name'] ) ),
				'mobile'      => sanitize_text_field( wp_unslash( $_POST['new_patient_mobile'] ?? '' ) ),
				'email'       => sanitize_email( wp_unslash( $_POST['new_patient_email'] ?? '' ) ),
				'age'         => absint( $_POST['new_patient_age'] ?? 0 ),
				'gender'      => sanitize_key( wp_unslash( $_POST['new_patient_gender'] ?? '' ) ),
				'blood_group' => sanitize_text_field( wp_unslash( $_POST['new_patient_blood_group'] ?? '' ) ),
				'address'     => sanitize_textarea_field( wp_unslash( $_POST['new_patient_address'] ?? '' ) ),
				'city'        => sanitize_text_field( wp_unslash( $_POST['new_patient_city'] ?? '' ) ),
			) );
			if ( is_wp_error( $patient_id ) ) {
				$this->redirect( 'cas-add-appointment', $patient_id->get_error_message(), true, array_merge( array( 'appointment_id' => $id ), $return_query ) );
			}
		}

		if ( $id > 0 ) {
			$result = $this->update_appointment_row( $id, $doctor_id, $patient_id, $date, $serial, $status, $notes, $is_vip, $vip_time );
			if ( is_wp_error( $result ) ) {
				$this->redirect( 'cas-add-appointment', $result->get_error_message(), true, array_merge( array( 'appointment_id' => $id ), $return_query ) );
			}
			// Keep the chamber manager on the same filtered date worklist after an edit.
			$this->redirect( 'cas-appointments', __( 'Appointment updated.', 'cas' ), false, $return_filters );
		}

		$result = CAS_Appointment::create( array( 'doctor_id' => $doctor_id, 'patient_id' => $patient_id, 'appointment_date' => $date, 'serial_number' => $serial, 'status' => $status, 'source' => 'admin', 'booked_by' => get_current_user_id(), 'notes' => $notes, 'is_vip' => $is_vip, 'reporting_time' => $vip_time ) );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'cas-add-appointment', $result->get_error_message(), true, $return_query );
		}
		// If the form came from a dated worklist, go back to that list; otherwise remain on Add Appointment.
		if ( ! empty( $return_filters ) ) {
			$this->redirect( 'cas-appointments', __( 'Appointment saved.', 'cas' ), false, $return_filters );
		}
		$this->redirect( 'cas-add-appointment', __( 'Appointment saved.', 'cas' ) );
	}

	private function update_appointment_row( $id, $doctor_id, $patient_id, $date, $serial, $status, $notes, $is_vip = false, $vip_time = '' ) {
		if ( ! self::user_can_access_doctor( $doctor_id ) ) { return new WP_Error( 'cas_doctor_forbidden', __( 'You are not assigned to this doctor.', 'cas' ) ); }
		return CAS_Appointment::update_admin_booking( $id, array(
			'doctor_id' => $doctor_id, 'patient_id' => $patient_id, 'appointment_date' => $date, 'serial_number' => $serial, 'status' => $status, 'notes' => $notes, 'is_vip' => $is_vip, 'reporting_time' => $vip_time,
		) );
	}

	public function handle_appointment_action() {
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_die(); }
		$id = absint( $_GET['appointment_id'] ?? 0 );
		check_admin_referer( 'cas_appointment_action_' . $id );
		if ( ! $this->appointment_is_accessible( $id ) ) { $this->redirect( 'cas-appointments', __( 'You cannot access this doctor appointment.', 'cas' ), true, self::get_appointment_list_filters( $_GET ) ); }
		$action = sanitize_key( wp_unslash( $_GET['cas_action'] ?? '' ) );
		$map    = array( 'reconfirm' => 'reconfirm', 'cancel' => 'cancel', 'check_in' => 'check_in', 'complete' => 'complete', 'no_show' => 'no_show' );
		$result = isset( $map[ $action ] ) ? call_user_func( array( 'CAS_Appointment', $map[ $action ] ), $id ) : new WP_Error( 'cas_bad_action', __( 'Invalid action.', 'cas' ) );
		$this->redirect( 'cas-appointments', is_wp_error( $result ) ? $result->get_error_message() : __( 'Appointment updated.', 'cas' ), is_wp_error( $result ), self::get_appointment_list_filters( $_GET ) );
	}


	/**
	 * Mark a patient as called for the currently filtered appointment date.
	 * The selected worklist date is required to avoid accidental marking from a
	 * broad, unfiltered list. The appointment status itself is not changed.
	 */
	public function handle_mark_reconfirmation_called() {
		if ( ! current_user_can( 'manage_cas_appointments' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cas' ) );
		}

		$id = absint( $_GET['appointment_id'] ?? 0 );
		check_admin_referer( 'cas_mark_reconfirmation_called_' . $id );
		$filters = self::get_appointment_list_filters( $_GET );

		if ( ! $this->appointment_is_accessible( $id ) ) {
			$this->redirect( 'cas-appointments', __( 'You cannot access this doctor appointment.', 'cas' ), true, $filters );
		}

		$appointment = CAS_Appointment::get_by_id( $id );
		if ( empty( $filters['cas_date'] ) || ! $appointment || $filters['cas_date'] !== $appointment->appointment_date ) {
			$this->redirect( 'cas-appointments', __( 'Choose the appointment date filter before marking a reconfirmation call.', 'cas' ), true, $filters );
		}

		$result = CAS_Appointment::mark_reconfirmation_called( $id, get_current_user_id() );
		$this->redirect(
			'cas-appointments',
			is_wp_error( $result ) ? $result->get_error_message() : __( 'Patient marked as called for reconfirmation.', 'cas' ),
			is_wp_error( $result ),
			$filters
		);
	}

	public function handle_delete_appointment() {
		global $wpdb;
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_die(); }
		$id = absint( $_GET['appointment_id'] ?? 0 );
		check_admin_referer( 'cas_delete_appointment_' . $id );
		if ( ! $this->appointment_is_accessible( $id ) ) { $this->redirect( 'cas-appointments', __( 'You cannot access this doctor appointment.', 'cas' ), true, self::get_appointment_list_filters( $_GET ) ); }
		$result = CAS_Appointment::delete( $id );
		$this->redirect( 'cas-appointments', $result ? __( 'Appointment deleted.', 'cas' ) : __( 'Could not delete appointment.', 'cas' ), ! $result, self::get_appointment_list_filters( $_GET ) );
	}

	public function handle_bulk_appointment_status() {
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_die(); }
		check_admin_referer( 'cas_bulk_appointment_status' );
		$status = sanitize_key( wp_unslash( $_POST['bulk_status'] ?? '' ) );
		foreach ( (array) ( $_POST['appointment_ids'] ?? array() ) as $id ) { $id = absint( $id ); if ( $this->appointment_is_accessible( $id ) ) { CAS_Appointment::update_status( $id, $status ); } }
		$this->redirect( 'cas-appointments', __( 'Bulk update complete.', 'cas' ), false, self::get_appointment_list_filters( $_POST, 'return_' ) );
	}

	public function handle_print_appointments(){
		if ( ! current_user_can( 'manage_cas_appointments' ) && ! current_user_can( 'manage_cas_reports' ) ) { wp_die( esc_html__( 'Permission denied.', 'cas' ) ); }
		check_admin_referer( 'cas_print_appointments' );
		$date      = self::sanitize_date( wp_unslash( $_GET['cas_date'] ?? '' ) );
		$doctor_id = absint( $_GET['doctor_id'] ?? 0 );
		$status    = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		$title     = sanitize_text_field( wp_unslash( $_GET['title'] ?? __( 'Appointment List', 'cas' ) ) );
		$auto      = ! empty( $_GET['auto_print'] );
		if ( ! self::can_view_all_doctors() && $doctor_id && ! self::user_can_access_doctor( $doctor_id ) ) { wp_die( esc_html__( 'You are not assigned to this doctor.', 'cas' ) ); }
		$doctor_ids = ( ! self::can_view_all_doctors() && ! $doctor_id ) ? self::get_current_user_allowed_doctor_ids() : array();
		$html = CAS_PDF::generate_printable_html( $date, $doctor_id, array( 'status' => $status, 'title' => $title, 'auto_print' => $auto, 'doctor_ids' => $doctor_ids ) );
		echo is_wp_error( $html ) ? esc_html( $html->get_error_message() ) : $html;
		exit;
	}
	public function handle_export_csv(){
		if ( ! current_user_can( 'manage_cas_reports' ) ) { wp_die( esc_html__( 'Permission denied.', 'cas' ) ); }
		check_admin_referer( 'cas_export_csv' );
		$data = CAS_Data_Tools::csv_data( 'appointments', self::scope_args_for_current_user( $_GET ) );
		$this->send_csv_download( $data );
	}

	private function send_csv_download( $data ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $data['filename'] ?? 'cas-export.csv' ) );
		$out = fopen( 'php://output', 'w' );
		fputs( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Bangla text in spreadsheet apps.
		fputcsv( $out, $data['headers'] );
		foreach ( $data['rows'] as $row ) { fputcsv( $out, $row ); }
		fclose( $out ); exit;
	}

	public function handle_export_data() {
		if ( ! current_user_can( 'manage_cas_reports' ) ) { wp_die( esc_html__( 'Permission denied.', 'cas' ) ); }
		check_admin_referer( 'cas_export_data' );
		$format = sanitize_key( wp_unslash( $_POST['format'] ?? 'csv' ) );
		$dataset = sanitize_key( wp_unslash( $_POST['dataset'] ?? 'appointments' ) );
		$scope = self::scope_args_for_current_user( $_POST );
		if ( 'json' === $format ) {
			nocache_headers(); header( 'X-Robots-Tag: noindex, nofollow', true ); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=cas-backup.json' );
			echo wp_json_encode( CAS_Data_Tools::json_backup( $scope ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); exit;
		}
		if ( ! in_array( $dataset, array( 'appointments', 'patients' ), true ) ) { $dataset = 'appointments'; }
		$this->send_csv_download( CAS_Data_Tools::csv_data( $dataset, $scope ) );
	}

	public function handle_import_data() {
		if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die( esc_html__( 'Permission denied.', 'cas' ) ); }
		check_admin_referer( 'cas_import_data' );
		if ( empty( $_POST['confirm_privacy'] ) ) { $this->redirect( 'cas-tools-export', __( 'Confirm that you are authorized to restore this protected patient data.', 'cas' ), true ); }
		$file = $_FILES['cas_import_file'] ?? array();
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== absint( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) ) { $this->redirect( 'cas-tools-export', __( 'Choose a valid CSV or JSON backup file.', 'cas' ), true ); }
		if ( absint( $file['size'] ?? 0 ) > CAS_Data_Tools::MAX_IMPORT_BYTES ) { $this->redirect( 'cas-tools-export', __( 'Import file is too large. The limit is 5 MB.', 'cas' ), true ); }
		$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) ); $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'json' ), true ) ) { $this->redirect( 'cas-tools-export', __( 'Only CSV and JSON backup files are accepted.', 'cas' ), true ); }
		$path = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $path ) || ! file_exists( $path ) || ! is_readable( $path ) ) { $this->redirect( 'cas-tools-export', __( 'Could not read the uploaded import file.', 'cas' ), true ); }
		if ( 'json' === $extension ) { $result = CAS_Data_Tools::import_json( (string) file_get_contents( $path ) ); }
		else { $type = sanitize_key( wp_unslash( $_POST['csv_type'] ?? '' ) ); $result = CAS_Data_Tools::import_csv( $path, $type ); }
		if ( is_wp_error( $result ) ) { $this->redirect( 'cas-tools-export', $result->get_error_message(), true ); }
		$message = sprintf( __( 'Import complete: %1$d patients created, %2$d patients updated, %3$d appointments restored, %4$d rows skipped.', 'cas' ), absint( $result['patients_created'] ?? 0 ), absint( $result['patients_updated'] ?? 0 ), absint( $result['appointments_restored'] ?? 0 ), absint( $result['skipped'] ?? 0 ) );
		$this->redirect( 'cas-tools-export', $message );
	}

	public function handle_save_patient(){ if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); } check_admin_referer( 'cas_save_patient' ); $id = absint( $_POST['patient_id'] ?? 0 ); $result = $id ? CAS_Patient::update( $id, $_POST ) : CAS_Patient::create( $_POST ); $this->redirect( 'cas-patients', is_wp_error( $result ) ? $result->get_error_message() : __( 'Patient saved.', 'cas' ), is_wp_error( $result ) ); }
	public function handle_deactivate_patient(){ global $wpdb; if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); } $id = absint( $_GET['patient_id'] ?? 0 ); check_admin_referer( 'cas_deactivate_patient_' . $id ); $result = $wpdb->update( CAS_DB::table( 'patients' ), array( 'is_active' => 0, 'updated_at' => CAS_DB::now() ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) ); $this->redirect( 'cas-patients', $result !== false ? __( 'Patient deactivated.', 'cas' ) : __( 'Could not deactivate patient.', 'cas' ), false === $result ); }
	public function handle_delete_patient(){ if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); } $id = absint( $_GET['patient_id'] ?? 0 ); check_admin_referer( 'cas_delete_patient_' . $id ); $result = CAS_Patient::delete( $id ); $this->redirect( 'cas-patients', $result ? __( 'Patient deleted.', 'cas' ) : __( 'Could not delete patient.', 'cas' ), ! $result ); }
	public function handle_save_family_member(){
		if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); }
		check_admin_referer( 'cas_save_family_member' );
		$id = absint( $_POST['family_member_id'] ?? 0 );
		$data = array(
			'primary_id'    => absint( $_POST['primary_id'] ?? 0 ),
			'full_name'     => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			'relation'      => sanitize_text_field( wp_unslash( $_POST['relation'] ?? '' ) ),
			'age'           => absint( $_POST['age'] ?? 0 ),
			'gender'        => sanitize_key( wp_unslash( $_POST['gender'] ?? '' ) ),
			'blood_group'   => sanitize_text_field( wp_unslash( $_POST['blood_group'] ?? '' ) ),
			'notes'         => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'is_active'     => isset( $_POST['is_active'] ) ? 1 : 0,
		);
		$result = $id > 0 ? CAS_Patient::update_family_member( $id, $data ) : CAS_Patient::add_family_member( $data['primary_id'], $data );
		$this->redirect( 'cas-family-members', is_wp_error( $result ) ? $result->get_error_message() : __( 'Family member saved.', 'cas' ), is_wp_error( $result ) || false === $result );
	}
	public function handle_delete_family_member(){ if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); } $id = absint( $_GET['family_member_id'] ?? 0 ); check_admin_referer( 'cas_delete_family_member_' . $id ); $result = CAS_Patient::delete_family_member( $id, true ); $this->redirect( 'cas-family-members', $result ? __( 'Family member deleted.', 'cas' ) : __( 'Could not delete family member.', 'cas' ), ! $result ); }

	public function handle_save_doctor(){ global $wpdb; if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die(); } check_admin_referer( 'cas_save_doctor' ); $id = absint( $_POST['doctor_id'] ?? 0 ); $mobile_raw = sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ); $mobile = $mobile_raw ? CAS_DB::normalize_mobile( $mobile_raw ) : ''; if ( false === $mobile ) { $this->redirect( 'cas-doctors', __( 'Invalid doctor mobile number.', 'cas' ), true ); } $row = array( 'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 'specialty' => sanitize_text_field( wp_unslash( $_POST['specialty'] ?? '' ) ), 'mobile' => $mobile, 'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), 'bio' => sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) ), 'is_active' => isset( $_POST['is_active'] ) ? 1 : 0, 'updated_at' => CAS_DB::now() ); if ( '' === $row['name'] ) { $this->redirect( 'cas-doctors', __( 'Doctor name is required.', 'cas' ), true ); } $result = $id ? $wpdb->update( CAS_DB::table( 'doctors' ), $row, array( 'id' => $id ) ) : $wpdb->insert( CAS_DB::table( 'doctors' ), array_merge( $row, array( 'created_at' => CAS_DB::now() ) ) ); $this->redirect( 'cas-doctors', false === $result ? __( 'Could not save doctor.', 'cas' ) : __( 'Doctor saved.', 'cas' ), false === $result ); }
	public function handle_deactivate_doctor(){ global $wpdb; if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die(); } $id = absint( $_GET['doctor_id'] ?? 0 ); check_admin_referer( 'cas_deactivate_doctor_' . $id ); $result = $wpdb->update( CAS_DB::table( 'doctors' ), array( 'is_active' => 0, 'updated_at' => CAS_DB::now() ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) ); $this->redirect( 'cas-doctors', $result !== false ? __( 'Doctor deactivated.', 'cas' ) : __( 'Could not deactivate doctor.', 'cas' ), false === $result ); }
	public function handle_delete_doctor(){ global $wpdb; if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die(); } $id = absint( $_GET['doctor_id'] ?? 0 ); check_admin_referer( 'cas_delete_doctor_' . $id ); $wpdb->delete( CAS_DB::table( 'schedules' ), array( 'doctor_id' => $id ), array( '%d' ) ); $result = $wpdb->delete( CAS_DB::table( 'doctors' ), array( 'id' => $id ), array( '%d' ) ); $this->redirect( 'cas-doctors', $result ? __( 'Doctor deleted.', 'cas' ) : __( 'Could not delete doctor.', 'cas' ), ! $result ); }

	public function handle_save_schedule(){ global $wpdb; if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die(); } check_admin_referer( 'cas_save_schedule' ); $doctor = absint( $_POST['doctor_id'] ?? 0 ); $days = array_map( 'absint', (array) ( $_POST['active_days'] ?? array() ) ); $holidays = array_filter( array_map( array( 'CAS_Admin', 'sanitize_date' ), (array) ( $_POST['holidays'] ?? array() ) ) ); $start = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '14:00' ) ); $end = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '18:00' ) ); $weekday_breaks = array(); foreach ( range( 0, 6 ) as $day ) { $enabled = ! empty( $_POST['break_enabled'][ $day ] ); $break_start = sanitize_text_field( wp_unslash( $_POST['break_start'][ $day ] ?? '' ) ); $break_end = sanitize_text_field( wp_unslash( $_POST['break_end'][ $day ] ?? '' ) ); if ( $enabled && preg_match( '/^\d{2}:\d{2}$/', $break_start ) && preg_match( '/^\d{2}:\d{2}$/', $break_end ) && $break_end > $break_start ) { $weekday_breaks[ (string) $day ] = array( 'enabled' => 1, 'start' => $break_start, 'end' => $break_end ); } } $row = array( 'doctor_id' => $doctor, 'daily_limit' => max( 1, absint( $_POST['daily_limit'] ?? 40 ) ), 'start_time' => preg_match( '/^\d{2}:\d{2}$/', $start ) ? $start . ':00' : $start, 'end_time' => preg_match( '/^\d{2}:\d{2}$/', $end ) ? $end . ':00' : $end, 'batch_size' => max( 1, absint( $_POST['batch_size'] ?? 10 ) ), 'reporting_interval' => max( 1, absint( $_POST['reporting_interval'] ?? 60 ) ), 'active_days' => implode( ',', array_values( array_intersect( $days, array( 0, 1, 2, 3, 4, 5, 6 ) ) ) ), 'holidays' => wp_json_encode( array_values( array_unique( $holidays ) ) ), 'weekday_breaks' => wp_json_encode( $weekday_breaks ), 'allow_manual_pick' => isset( $_POST['allow_manual_pick'] ) ? 1 : 0, 'is_active' => isset( $_POST['is_active'] ) ? 1 : 0, 'updated_at' => CAS_DB::now() ); $t = CAS_DB::table( 'schedules' ); $sid = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE doctor_id=%d", $doctor ) ) ); $result = $sid ? $wpdb->update( $t, $row, array( 'id' => $sid ) ) : $wpdb->insert( $t, $row ); $this->redirect( 'cas-schedule-settings', false === $result ? __( 'Could not save schedule.', 'cas' ) : __( 'Schedule saved.', 'cas' ), false === $result ); }
	public function handle_save_sms_settings(){ check_admin_referer( 'cas_save_sms_settings' ); $s = CAS_DB::get_settings(); $s['sms_enabled'] = isset( $_POST['sms_enabled'] ) ? 1 : 0; $s['sms_api_url'] = esc_url_raw( wp_unslash( $_POST['sms_api_url'] ?? '' ) ); $s['sms_balance_url'] = sanitize_text_field( wp_unslash( $_POST['sms_balance_url'] ?? '' ) ); $s['sms_senderid'] = sanitize_text_field( wp_unslash( $_POST['sms_senderid'] ?? '' ) ); if ( ! empty( $_POST['sms_api_key'] ) ) { $s['sms_api_key'] = sanitize_text_field( wp_unslash( $_POST['sms_api_key'] ) ); } $s['sms_templates'] = array_map( 'sanitize_textarea_field', (array) ( $_POST['sms_templates'] ?? array() ) ); update_option( CAS_DB::OPTION_SETTINGS, $s, false ); $this->redirect( 'cas-sms-settings', __( 'SMS settings saved.', 'cas' ) ); }
	public function handle_test_sms(){ check_admin_referer( 'cas_test_sms' ); $result = CAS_SMS::test_sms( sanitize_text_field( wp_unslash( $_POST['test_mobile'] ?? '' ) ), sanitize_textarea_field( wp_unslash( $_POST['test_message'] ?? '' ) ) ); $this->redirect( 'cas-sms-settings', is_wp_error( $result ) ? $result->get_error_message() : __( 'Test SMS sent.', 'cas' ), is_wp_error( $result ) ); }
	public function handle_save_otp_settings(){ check_admin_referer( 'cas_save_otp_settings' ); if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die( esc_html__( 'You do not have permission to update OTP settings.', 'cas' ) ); } $s = CAS_DB::get_settings(); $mode = sanitize_key( wp_unslash( $_POST['application_mode'] ?? 'live' ) ); $s['application_mode'] = in_array( $mode, array( 'live', 'development' ), true ) ? $mode : 'live'; $s['otp_digits'] = max( 4, min( 8, absint( $_POST['otp_digits'] ?? ( $s['otp_digits'] ?? 6 ) ) ) ); foreach ( array( 'otp_expiry_minutes', 'otp_resend_cooldown_seconds', 'otp_max_attempts', 'otp_lockout_minutes', 'otp_ip_hourly_limit' ) as $k ) { $s[ $k ] = absint( $_POST[ $k ] ?? $s[ $k ] ); } $s['email_otp_enabled'] = isset( $_POST['email_otp_enabled'] ) ? 1 : 0; update_option( CAS_DB::OPTION_SETTINGS, $s, false ); $message = 'development' === $s['application_mode'] ? __( 'Development Mode saved. SMS/email OTP delivery is suppressed and test OTPs are shown on login screens.', 'cas' ) : __( 'Live App Mode saved. Real SMS/email delivery follows your configured settings.', 'cas' ); $this->redirect( 'cas-otp-settings', $message ); }
	public function handle_save_plugin_settings(){
		if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die( esc_html__( 'You do not have permission to update plugin settings.', 'cas' ) ); }
		check_admin_referer( 'cas_save_plugin_settings' );
		$s = CAS_DB::get_settings();
		foreach ( array( 'brand_name', 'currency', 'chamber_attendant_phone' ) as $k ) { $s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ); }
		$language = sanitize_key( wp_unslash( $_POST['frontend_default_language'] ?? 'bn' ) );
		$s['frontend_default_language'] = in_array( $language, array( 'bn', 'en' ), true ) ? $language : 'bn';
		$s['availability_poll_seconds'] = max( 5, min( 60, absint( $_POST['availability_poll_seconds'] ?? 15 ) ) );
		$s['patient_booking_window_days'] = max( 1, min( 3650, absint( $_POST['patient_booking_window_days'] ?? 30 ) ) );
		$s['patient_appointment_modify_limit'] = max( 0, min( 20, absint( $_POST['patient_appointment_modify_limit'] ?? 2 ) ) );
		$s['appointment_email_notifications_enabled'] = isset( $_POST['appointment_email_notifications_enabled'] ) ? 1 : 0;
		$after_registration = sanitize_key( wp_unslash( $_POST['after_new_patient_registration'] ?? 'booking' ) );
		$s['after_new_patient_registration'] = in_array( $after_registration, array( 'booking', 'dashboard' ), true ) ? $after_registration : 'booking';
		foreach ( array( 'portal_login_page_id', 'portal_dashboard_page_id', 'portal_booking_page_id', 'portal_appointments_page_id', 'portal_messages_page_id', 'items_per_page' ) as $k ) { $s[ $k ] = absint( $_POST[ $k ] ?? 0 ); }

		$mode = sanitize_key( wp_unslash( $_POST['appointment_mode'] ?? 'multiple' ) );
		$s['appointment_mode'] = in_array( $mode, array( 'single', 'multiple' ), true ) ? $mode : 'multiple';
		$s['single_doctor_id'] = absint( $_POST['single_doctor_id'] ?? 0 );
		$s['single_doctor_show_specialty'] = isset( $_POST['single_doctor_show_specialty'] ) ? 1 : 0;

		if ( 'single' === $s['appointment_mode'] ) {
			global $wpdb;
			$doctor_id = absint( $s['single_doctor_id'] );
			$doctor = $doctor_id ? $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . CAS_DB::table( 'doctors' ) . ' WHERE id = %d AND is_active = 1', $doctor_id ) ) : 0;
			if ( ! $doctor ) {
				$this->redirect( 'cas-plugin-settings', __( 'Please select an active doctor before enabling Single Doctor mode.', 'cas' ), true );
			}
		}

		update_option( CAS_DB::OPTION_SETTINGS, $s, false );
		$message = 'single' === $s['appointment_mode'] ? __( 'Single Doctor mode saved. Patients will see the configured doctor without a doctor selection field.', 'cas' ) : __( 'Multiple Doctors mode saved. Patients can select a doctor while booking.', 'cas' );
		$this->redirect( 'cas-plugin-settings', $message );
	}

	/**
	 * Add a patient to the waiting list manually from wp-admin.
	 *
	 * Chamber staff may use this workflow in both Live and Development modes,
	 * including when normal appointment slots are still available. This is an
	 * intentional staff override and does not bypass nonce, capability, doctor
	 * access, patient existence, date, or duplicate-entry checks. Development
	 * Mode suppresses the outbound waiting-list SMS; Live Mode follows the
	 * configured waiting-list notification settings.
	 */
	public function handle_admin_add_waiting() {
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_die(); }
		check_admin_referer( 'cas_admin_add_waiting' );

		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );
		$patient_id = absint( $_POST['patient_id'] ?? 0 );
		$date = self::sanitize_date( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		$extra = array( 'cas_date' => $date, 'doctor_id' => $doctor_id );

		$development_mode = CAS_OTP::is_development_mode();
		if ( ! $doctor_id || ! self::user_can_access_doctor( $doctor_id ) ) {
			$this->redirect( 'cas-waiting-list', __( 'Please select a doctor you are allowed to manage.', 'cas' ), true, $extra );
		}
		if ( ! $date ) {
			$this->redirect( 'cas-waiting-list', __( 'Please select a valid appointment date.', 'cas' ), true, $extra );
		}
		$patient = $patient_id ? CAS_Patient::get_by_id( $patient_id ) : null;
		if ( ! $patient || empty( $patient->is_active ) ) {
			$this->redirect( 'cas-waiting-list', __( 'Please select an active patient.', 'cas' ), true, $extra );
		}

		$result = CAS_Waiting_List::add_to_list(
			array(
				'doctor_id'            => $doctor_id,
				'patient_id'           => $patient_id,
				'appointment_date'     => $date,
				// Never send real test messages in Development Mode. Live Mode uses the configured SMS workflow.
				'suppress_notification'=> $development_mode,
			)
		);
		$this->redirect(
			'cas-waiting-list',
			is_wp_error( $result ) ? $result->get_error_message() : __( 'Patient added to the waiting list.', 'cas' ),
			is_wp_error( $result ),
			$extra
		);
	}

	public function handle_promote_waiting(){ check_admin_referer( 'cas_promote_waiting' ); if ( ! $this->waiting_is_accessible( absint( $_POST['waiting_id'] ?? 0 ) ) ) { $this->redirect( 'cas-waiting-list', __( 'You cannot access this doctor waiting list.', 'cas' ), true ); } $result = CAS_Waiting_List::promote_to_appointment( absint( $_POST['waiting_id'] ?? 0 ), absint( $_POST['serial_number'] ?? 0 ), get_current_user_id(), self::sanitize_date( wp_unslash( $_POST['appointment_date'] ?? '' ) ) ); $this->redirect( 'cas-waiting-list', is_wp_error( $result ) ? $result->get_error_message() : __( 'Promoted.', 'cas' ), is_wp_error( $result ) ); }
	public function handle_cancel_waiting(){ $id = absint( $_GET['waiting_id'] ?? 0 ); check_admin_referer( 'cas_cancel_waiting_' . $id ); if ( ! $this->waiting_is_accessible( $id ) ) { $this->redirect( 'cas-waiting-list', __( 'You cannot access this doctor waiting list.', 'cas' ), true ); } CAS_Waiting_List::cancel_waiting( $id ); $this->redirect( 'cas-waiting-list', __( 'Cancelled.', 'cas' ) ); }
	public function handle_reply_message(){
		if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); }
		check_admin_referer( 'cas_reply_message' );

		$patient_id = absint( $_POST['patient_id'] ?? 0 );
		$message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$attachment = CAS_DB::handle_message_attachment_upload( 'attachment' );
		$redirect_args = array( 'page' => 'cas-message-center' );
		if ( $patient_id ) { $redirect_args['patient_id'] = $patient_id; }

		if ( is_wp_error( $attachment ) ) {
			$redirect_args['cas_status']  = 'error';
			$redirect_args['cas_message'] = rawurlencode( $attachment->get_error_message() );
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$inserted = CAS_DB::insert_message( $patient_id, 'chamber_to_patient', $message, $attachment, 1 );
		if ( is_wp_error( $inserted ) ) {
			$redirect_args['cas_status']  = 'error';
			$redirect_args['cas_message'] = rawurlencode( $inserted->get_error_message() );
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$redirect_args['cas_message'] = rawurlencode( __( 'Message sent to patient.', 'cas' ) );
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_clear_old_message_attachments(){
		if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); }
		check_admin_referer( 'cas_clear_old_message_attachments' );
		$count = CAS_DB::clear_message_attachments_older_than( 30 );
		$this->redirect( 'cas-message-center', sprintf( __( 'Cleared %d message attachment(s) older than 1 month. Message texts were kept.', 'cas' ), absint( $count ) ) );
	}
	public function ajax_get_available_serials(){ check_ajax_referer( 'cas_admin_nonce', 'nonce' ); $doctor_id = absint( $_POST['doctor_id'] ?? 0 ); if ( ! self::user_can_access_doctor( $doctor_id ) ) { wp_send_json_error( array( 'message' => __( 'You are not assigned to this doctor.', 'cas' ) ) ); } $serials = CAS_Appointment::get_available_serials( $doctor_id, self::sanitize_date( $_POST['appointment_date'] ?? '' ) ); is_wp_error( $serials ) ? wp_send_json_error( array( 'message' => $serials->get_error_message() ) ) : wp_send_json_success( $serials ); }
	public function ajax_test_sms(){ check_ajax_referer( 'cas_admin_nonce', 'nonce' ); $result = CAS_SMS::test_sms( sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ), sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) ); is_wp_error( $result ) ? wp_send_json_error( array( 'message' => $result->get_error_message() ) ) : wp_send_json_success( array( 'message' => __( 'Test SMS sent.', 'cas' ) ) ); }
	public function ajax_promote_waiting(){ check_ajax_referer( 'cas_admin_nonce', 'nonce' ); if ( ! $this->waiting_is_accessible( absint( $_POST['waiting_id'] ?? 0 ) ) ) { wp_send_json_error( array( 'message' => __( 'You cannot access this doctor waiting list.', 'cas' ) ) ); } $result = CAS_Waiting_List::promote_to_appointment( absint( $_POST['waiting_id'] ?? 0 ), absint( $_POST['serial_number'] ?? 0 ), get_current_user_id(), self::sanitize_date( wp_unslash( $_POST['appointment_date'] ?? '' ) ) ); is_wp_error( $result ) ? wp_send_json_error( array( 'message' => $result->get_error_message() ) ) : wp_send_json_success( array( 'message' => __( 'Promoted.', 'cas' ) ) ); }


	public function handle_create_portal_pages() {
		if ( ! current_user_can( 'manage_cas_settings' ) ) { wp_die(); }
		check_admin_referer( 'cas_create_portal_pages' );
		$created = CAS_DB::ensure_portal_pages( true );
		$message = empty( $created ) ? __( 'Patient portal pages checked and assigned successfully.', 'cas' ) : sprintf( __( 'Patient portal pages created/assigned: %s', 'cas' ), implode( ', ', $created ) );
		$this->redirect( 'cas-plugin-settings', $message );
	}

	public function handle_deactivate_family_member(){
		if ( ! current_user_can( 'manage_cas_patients' ) ) { wp_die(); }
		$id = absint( $_GET['family_member_id'] ?? 0 );
		check_admin_referer( 'cas_deactivate_family_member_' . $id );
		$result = CAS_Patient::deactivate_family_member( $id );
		$this->redirect( 'cas-family-members', $result ? __( 'Family member deactivated.', 'cas' ) : __( 'Could not deactivate family member.', 'cas' ), ! $result );
	}

	public function ajax_get_slot_map(){
		check_ajax_referer( 'cas_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_cas_appointments' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cas' ) ) ); }
		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );
		$date = self::sanitize_date( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		if ( ! self::user_can_access_doctor( $doctor_id ) ) { wp_send_json_error( array( 'message' => __( 'You are not assigned to this doctor.', 'cas' ) ) ); }
		$map = CAS_Appointment::get_slot_map( $doctor_id, $date );
		if ( is_wp_error( $map ) ) { wp_send_json_error( array( 'message' => $map->get_error_message() ) ); }
		wp_send_json_success( $map );
	}

	public function ajax_check_balance(){
		check_ajax_referer( 'cas_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_cas_sms' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cas' ) ) ); }
		$override_url = isset( $_POST['balance_url'] ) ? sanitize_text_field( wp_unslash( $_POST['balance_url'] ) ) : '';
		$result = CAS_SMS::check_balance( $override_url );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
		$body      = isset( $result['body'] ) ? trim( wp_strip_all_tags( (string) $result['body'] ) ) : '';
		$http_code = isset( $result['response_code'] ) ? absint( $result['response_code'] ) : 0;
		$decoded   = json_decode( $body, true );

		if ( is_array( $decoded ) && isset( $decoded['response_code'] ) && 202 !== absint( $decoded['response_code'] ) ) {
			$error_message = ! empty( $decoded['error_message'] ) ? sanitize_text_field( $decoded['error_message'] ) : __( 'Balance API returned an error.', 'cas' );
			wp_send_json_error( array( 'message' => sprintf( __( 'Balance check failed: %s', 'cas' ), $error_message ), 'response_code' => $http_code, 'body' => $body ) );
		}

		if ( is_array( $decoded ) && isset( $decoded['balance'] ) ) {
			$message = sprintf( __( 'SMS balance: %s', 'cas' ), number_format_i18n( (float) $decoded['balance'], 2 ) );
		} else {
			$message = $body ? sprintf( __( 'SMS balance: %s', 'cas' ), $body ) : __( 'Balance API returned no response body.', 'cas' );
		}

		wp_send_json_success( array( 'message' => $message, 'response_code' => $http_code, 'body' => $body ) );
	}
}
