<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Patient-facing portal shortcodes, secure sessions, and AJAX endpoints.
 */
class CAS_Public {
	const AJAX_NONCE_ACTION = 'cas_public_nonce';
	const SESSION_TRANSIENT_KEY = 'cas_patient_session_';

	public function register_hooks() {
		add_action( 'init', array( $this, 'start_session' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		foreach ( array( 'cas_patient_login' => 'login', 'cas_patient_dashboard' => 'dashboard', 'cas_book_appointment' => 'book_appointment', 'cas_my_appointments' => 'my_appointments', 'cas_messages' => 'messages' ) as $shortcode => $view ) {
			add_shortcode( $shortcode, array( $this, 'shortcode_' . $view ) );
		}

		$actions = array(
			'cas_send_otp'              => 'ajax_send_otp',
			'cas_verify_otp'            => 'ajax_verify_otp',
			'cas_select_profile'        => 'ajax_select_profile',
			'cas_update_profile'        => 'ajax_update_profile',
			'cas_add_family_member'     => 'ajax_add_family_member',
			'cas_update_family_member'  => 'ajax_update_family_member',
			'cas_deactivate_family_member'=> 'ajax_deactivate_family_member',
			'cas_delete_family_member'  => 'ajax_delete_family_member',
			'cas_get_available_dates'   => 'ajax_get_available_dates',
			'cas_get_available_serials' => 'ajax_get_available_serials',
			'cas_book_appointment'      => 'ajax_book_appointment',
			'cas_update_appointment'    => 'ajax_update_appointment',
			'cas_cancel_appointment'    => 'ajax_cancel_appointment',
			'cas_get_patient_active_appointment' => 'ajax_get_patient_active_appointment',
			'cas_join_waiting_list'     => 'ajax_join_waiting_list',
			'cas_send_message'          => 'ajax_send_message',
			'cas_get_messages'          => 'ajax_get_messages',
			'cas_logout'                => 'ajax_logout',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
		}
	}

	public function start_session() {
		if ( ! headers_sent() && PHP_SESSION_NONE === session_status() ) {
			session_start();
		}
	}

	public function enqueue_assets() {
		$settings = CAS_DB::get_settings();
		$is_bangla = 0 === strpos( determine_locale(), 'bn' );
		wp_enqueue_style( 'cas-public', CAS_PLUGIN_URL . 'assets/css/cas-public.css', array(), CAS_VERSION );
		wp_enqueue_script( 'cas-public', CAS_PLUGIN_URL . 'assets/js/cas-public.js', array(), CAS_VERSION, true );
		wp_localize_script(
			'cas-public',
			'CASPublic',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::AJAX_NONCE_ACTION ),
				'dashboardPageUrl'   => $this->page_url( absint( $settings['portal_dashboard_page_id'] ) ),
				'bookingPageUrl'     => $this->page_url( absint( $settings['portal_booking_page_id'] ) ),
				'appointmentsPageUrl'=> $this->page_url( absint( $settings['portal_appointments_page_id'] ) ),
				'availabilityPollSeconds' => max( 5, min( 60, absint( $settings['availability_poll_seconds'] ?? 15 ) ) ),
				'i18n'               => array(
					'confirmBooking' => __( 'Confirm this appointment?', 'cas' ),
					'confirmLogout'  => __( 'Log out?', 'cas' ),
					'invalidManualDate' => __( 'Please enter a valid date as DD/MM/YYYY or YYYY-MM-DD.', 'cas' ),
					'serialJustTaken' => __( 'Your selected serial was just booked by another user. Please choose another serial.', 'cas' ),
					'availabilityChanged' => __( 'Serial availability has been updated.', 'cas' ),
					'relativeRequired' => __( 'Please enter the relative name and select the relationship.', 'cas' ),
					'relativeAdded' => __( 'Relative added and selected for this appointment.', 'cas' ),
					'relativeAddFailed' => __( 'Could not add this relative. Please try again.', 'cas' ),
					'termsRequired' => __( 'Please agree to the appointment and privacy policy before continuing.', 'cas' ),
					'serialRequired' => __( 'Please select a serial first.', 'cas' ),
					'patientRequired' => __( 'Please choose the patient who will attend this appointment.', 'cas' ),
					'fullyBooked' => __( 'This date is fully booked. You may join the waiting list after choosing a patient.', 'cas' ),
					'chooseSerial' => __( 'Choose one available serial below.', 'cas' ),
					'serialLoadFailed' => __( 'Could not load serials. Tap “Show available serials” to try again.', 'cas' ),
					'serialAutoLoadFailed' => __( 'Could not load serials automatically. Tap “Show available serials” to try again.', 'cas' ),
					'saveFailed' => __( 'Could not save appointment.', 'cas' ),
					'appointmentUpdated' => __( 'Appointment updated successfully.', 'cas' ),
					'appointmentBooked' => __( 'Appointment booked successfully.', 'cas' ),
					'waitingSaved' => __( 'Waiting list request saved.', 'cas' ),
					'waitingFailed' => __( 'Could not join the waiting list.', 'cas' ),
					'dateHoliday' => __( 'This date is a holiday.', 'cas' ),
					'inactiveDay' => __( 'This is not an active chamber day.', 'cas' ),
					'serialLabel' => __( 'Serial', 'cas' ),
					'reportingTimeLabel' => __( 'Reporting time', 'cas' ),
					'confirmChanges' => __( 'Save these appointment changes?', 'cas' ),
					'serialConfirmTitle' => $is_bangla ? 'সিরিয়াল নিশ্চিত করুন' : __( 'Confirm serial selection', 'cas' ),
					'serialConfirmMessage' => $is_bangla ? 'আপনি সিরিয়াল %s নির্বাচন করেছেন। আপনি কি নিশ্চিত?' : __( 'You have selected serial %s. Are you sure?', 'cas' ),
					'serialConfirmYes' => $is_bangla ? 'হ্যাঁ, নির্বাচন করুন' : __( 'Yes, select it', 'cas' ),
					'serialConfirmNo' => $is_bangla ? 'না, অন্যটি বেছে নিন' : __( 'No, choose another', 'cas' ),
				),
			)
		);
	}

	private function page_url( $page_id ) {
		$url = $page_id ? get_permalink( $page_id ) : '';
		return $url ? esc_url_raw( $url ) : home_url( '/' );
	}

	private function view( $view ) {
		$file = CAS_PLUGIN_DIR . 'public/views/' . sanitize_file_name( $view ) . '.php';
		if ( ! file_exists( $file ) ) { return ''; }
		ob_start();
		require $file;
		return ob_get_clean();
	}

	/**
	 * Render responsive patient portal navigation.
	 *
	 * Desktop keeps the familiar top menu. Mobile uses a fixed, thumb-friendly
	 * bottom bar so elderly users never need to discover horizontal scrolling.
	 */
	private function patient_portal_menu( $active = '' ) {
		$patient_id = $this->get_current_patient_id();
		if ( ! $patient_id ) {
			return '';
		}

		$settings        = CAS_DB::get_settings();
		$dashboard_url   = $this->page_url( absint( $settings['portal_dashboard_page_id'] ?? 0 ) );
		$booking_url     = $this->page_url( absint( $settings['portal_booking_page_id'] ?? 0 ) );
		$appointments_url= $this->page_url( absint( $settings['portal_appointments_page_id'] ?? 0 ) );
		$messages_url    = $this->page_url( absint( $settings['portal_messages_page_id'] ?? 0 ) );
		$unread_messages = $this->get_unread_message_count();
		$active_count    = $this->get_active_appointment_count();
		$phone           = sanitize_text_field( $settings['chamber_attendant_phone'] ?? '' );
		$phone_href      = preg_replace( '/[^0-9+]/', '', $phone );

		$items = array(
			'dashboard'    => array( 'label' => __( 'Home', 'cas' ), 'desktop_label' => __( 'Dashboard', 'cas' ), 'icon' => '⌂', 'url' => $dashboard_url, 'badge' => 0 ),
			'book'         => array( 'label' => __( 'Book', 'cas' ), 'desktop_label' => __( 'Book Appointment', 'cas' ), 'icon' => '＋', 'url' => $booking_url, 'badge' => 0 ),
			'appointments' => array( 'label' => __( 'Appointments', 'cas' ), 'desktop_label' => __( 'My Appointments', 'cas' ), 'icon' => '▣', 'url' => $appointments_url, 'badge' => $active_count ),
			'messages'     => array( 'label' => __( 'Messages', 'cas' ), 'desktop_label' => __( 'Messages', 'cas' ), 'icon' => '✉', 'url' => $messages_url, 'badge' => $unread_messages ),
		);

		ob_start();
		?>
		<nav class="cas-public-wrap cas-patient-portal-menu cas-desktop-portal-menu" aria-label="<?php echo esc_attr__( 'Patient portal menu', 'cas' ); ?>">
			<?php foreach ( $items as $key => $item ) : ?>
				<a class="cas-patient-menu-link <?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $item['desktop_label'] ); ?>
					<?php if ( $item['badge'] ) : ?><span class="cas-nav-badge"><?php echo esc_html( min( 99, absint( $item['badge'] ) ) ); ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<nav class="cas-mobile-bottom-nav" aria-label="<?php echo esc_attr__( 'Mobile patient portal menu', 'cas' ); ?>">
			<?php foreach ( $items as $key => $item ) : ?>
				<a class="cas-mobile-nav-item <?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
					<span class="cas-mobile-nav-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
					<span class="cas-mobile-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					<?php if ( $item['badge'] ) : ?><span class="cas-mobile-nav-badge"><?php echo esc_html( min( 99, absint( $item['badge'] ) ) ); ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<button type="button" class="cas-mobile-nav-item <?php echo 'more' === $active ? 'is-active' : ''; ?>" data-cas-more-open aria-haspopup="dialog" aria-controls="cas-mobile-more-sheet" aria-expanded="false">
				<span class="cas-mobile-nav-icon" aria-hidden="true">☰</span><span class="cas-mobile-nav-label"><?php echo esc_html__( 'More', 'cas' ); ?></span>
			</button>
		</nav>

		<a class="cas-mobile-book-fab" href="<?php echo esc_url( $booking_url ); ?>" aria-label="<?php echo esc_attr__( 'Book Appointment', 'cas' ); ?>" title="<?php echo esc_attr__( 'Book Appointment', 'cas' ); ?>">＋</a>

		<div id="cas-mobile-more-sheet" class="cas-mobile-more-sheet" data-cas-more-sheet hidden>
			<button type="button" class="cas-more-backdrop" data-cas-more-close aria-label="<?php echo esc_attr__( 'Close menu', 'cas' ); ?>"></button>
			<section class="cas-more-panel" role="dialog" aria-modal="true" aria-labelledby="cas-more-title">
				<div class="cas-more-handle" aria-hidden="true"></div>
				<div class="cas-more-heading"><h2 id="cas-more-title"><?php echo esc_html__( 'More', 'cas' ); ?></h2><button type="button" class="cas-more-close" data-cas-more-close aria-label="<?php echo esc_attr__( 'Close menu', 'cas' ); ?>">×</button></div>
				<div class="cas-more-links">
					<a href="<?php echo esc_url( $dashboard_url . '#cas-profile-section' ); ?>"><span aria-hidden="true">●</span><?php echo esc_html__( 'My Profile', 'cas' ); ?></a>
					<a href="<?php echo esc_url( $dashboard_url . '#cas-family-section' ); ?>"><span aria-hidden="true">👪</span><?php echo esc_html__( 'Family Members', 'cas' ); ?></a>
					<a href="<?php echo esc_url( $messages_url ); ?>"><span aria-hidden="true">✉</span><?php echo esc_html__( 'Messages', 'cas' ); ?><?php if ( $unread_messages ) : ?><b class="cas-more-link-badge"><?php echo esc_html( min( 99, $unread_messages ) ); ?></b><?php endif; ?></a>
					<?php if ( $phone_href ) : ?><a href="tel:<?php echo esc_attr( $phone_href ); ?>"><span aria-hidden="true">☎</span><?php echo esc_html__( 'Call Chamber', 'cas' ); ?><small><?php echo esc_html( $phone ); ?></small></a><?php endif; ?>
					<button type="button" data-cas-logout><span aria-hidden="true">↪</span><?php echo esc_html__( 'Logout', 'cas' ); ?></button>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Count unread chamber replies for every profile owned by this mobile login. */
	private function get_unread_message_count() {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', wp_list_pluck( $this->get_accessible_patient_profiles(), 'id' ) ) ) );
		if ( empty( $ids ) ) { return 0; }
		$table = CAS_DB::table( 'messages' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT COUNT(*) FROM {$table} WHERE patient_id IN ({$placeholders}) AND direction='chamber_to_patient' AND is_read=0";
		return absint( $wpdb->get_var( $wpdb->prepare( $sql, $ids ) ) );
	}

	/** Count current appointments to provide a small useful mobile badge. */
	private function get_active_appointment_count() {
		$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		$count = 0;
		foreach ( $this->get_accessible_patient_profiles() as $profile ) {
			$list = CAS_Appointment::search( array( 'patient_id' => absint( $profile->id ), 'date_from' => $today, 'limit' => 20, 'order' => 'ASC' ) );
			foreach ( $list as $appointment ) {
				if ( ! in_array( (string) $appointment->status, array( 'cancelled', 'completed', 'no_show' ), true ) ) { $count++; }
			}
		}
		return $count;
	}

	private function language_toggle() { return class_exists( 'CAS_I18n' ) ? CAS_I18n::toggle_html() : ''; }

	public function shortcode_login() { return $this->language_toggle() . ( $this->get_current_patient_id() ? $this->patient_portal_menu( 'dashboard' ) . $this->view( 'dashboard' ) : $this->view( 'login' ) ); }
	public function shortcode_dashboard() { $required = $this->require_login(); return $this->language_toggle() . ( $required ?: $this->patient_portal_menu( 'dashboard' ) . $this->view( 'dashboard' ) ); }
	public function shortcode_book_appointment() { $required = $this->require_login(); return $this->language_toggle() . ( $required ?: $this->patient_portal_menu( 'book' ) . $this->view( 'book-appointment' ) ); }
	public function shortcode_my_appointments() { $required = $this->require_login(); return $this->language_toggle() . ( $required ?: $this->patient_portal_menu( 'appointments' ) . $this->view( 'my-appointments' ) ); }
	public function shortcode_messages() { $required = $this->require_login(); return $this->language_toggle() . ( $required ?: $this->patient_portal_menu( 'messages' ) . $this->view( 'messages' ) ); }


	private function require_login() {
		if ( $this->get_current_patient_id() ) { return ''; }

		$settings = CAS_DB::get_settings();
		$login_url = $this->page_url( absint( $settings['portal_login_page_id'] ?? 0 ) );

		ob_start();
		?>
		<div class="cas-public-wrap cas-login-required-wrap">
			<div class="cas-card cas-login-required-card">
				<div class="cas-notice cas-notice-error">
					<?php echo esc_html__( 'Please log in first.', 'cas' ); ?>
				</div>
				<p class="cas-login-required-text">
					<?php echo esc_html__( 'To use this patient portal page, please log in with your mobile OTP. New patients can also create their account after OTP verification.', 'cas' ); ?>
				</p>
				<a class="cas-button cas-button-primary cas-login-required-button" href="<?php echo esc_url( $login_url ); ?>">
					<?php echo esc_html__( 'Login / Create Your Account', 'cas' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function nonce() {
		if ( ! check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'cas' ) ), 403 );
		}
	}

	private function require_patient_ajax() {
		$patient_id = $this->get_current_patient_id();
		if ( ! $patient_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in first.', 'cas' ) ), 401 );
		}
		return $patient_id;
	}

	private function login( $patient_id ) {
		$token = wp_generate_password( 43, false, false );
		$_SESSION['cas_patient_id'] = absint( $patient_id );
		$_SESSION['cas_patient_token'] = $token;
		set_transient( self::SESSION_TRANSIENT_KEY . $token, absint( $patient_id ), DAY_IN_SECONDS );
	}

	public function get_current_patient_id() {
		if ( empty( $_SESSION['cas_patient_id'] ) || empty( $_SESSION['cas_patient_token'] ) ) { return 0; }
		$token = sanitize_text_field( wp_unslash( $_SESSION['cas_patient_token'] ) );
		$stored = absint( get_transient( self::SESSION_TRANSIENT_KEY . $token ) );
		return $stored === absint( $_SESSION['cas_patient_id'] ) ? absint( $_SESSION['cas_patient_id'] ) : 0;
	}

	public function get_current_patient() { return CAS_Patient::get_by_id( $this->get_current_patient_id() ); }

	public function get_active_doctors() {
		// In solo mode, return only the configured active doctor. This keeps the
		// same behavior for the booking page and other public displays.
		if ( CAS_DB::is_single_doctor_mode() ) {
			$doctor = CAS_DB::get_single_booking_doctor();
			return $doctor ? array( $doctor ) : array();
		}
		global $wpdb;
		$t = CAS_DB::table( 'doctors' );
		return $wpdb->get_results( "SELECT * FROM {$t} WHERE is_active=1 ORDER BY name" );
	}

	/**
	 * Returns all active patient profiles that share the logged-in mobile number.
	 * This allows booking for self or family profiles under the same mobile.
	 */
	public function get_accessible_patient_profiles() {
		$current = $this->get_current_patient();
		if ( ! $current || empty( $current->mobile ) ) { return array(); }
		return CAS_DB::get_patients_by_mobile( $current->mobile );
	}

	private function patient_profile_is_accessible( $patient_id ) {
		$patient_id = absint( $patient_id );
		foreach ( $this->get_accessible_patient_profiles() as $profile ) {
			if ( absint( $profile->id ) === $patient_id ) { return true; }
		}
		return false;
	}

	public function get_family_members() {
		return CAS_Patient::get_family_members( $this->get_current_patient_id(), true );
	}

	public function get_patient_appointments( $patient_id ) {
		$appointments = array();
		foreach ( $this->get_accessible_patient_profiles() as $profile ) {
			$appointments = array_merge( $appointments, CAS_Appointment::get_by_patient( $profile->id, array( 'limit' => 100 ) ) );
		}
		$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		$inactive_statuses = array( 'cancelled', 'completed', 'no_show' );

		usort(
			$appointments,
			function( $a, $b ) use ( $today, $inactive_statuses ) {
				$a_status = isset( $a->status ) ? (string) $a->status : '';
				$b_status = isset( $b->status ) ? (string) $b->status : '';
				$a_date   = isset( $a->appointment_date ) ? (string) $a->appointment_date : '';
				$b_date   = isset( $b->appointment_date ) ? (string) $b->appointment_date : '';

				// 0: upcoming active appointments, 1: previous appointments, 2: cancelled appointments.
				$a_group = 'cancelled' === $a_status ? 2 : ( $a_date >= $today && ! in_array( $a_status, $inactive_statuses, true ) ? 0 : 1 );
				$b_group = 'cancelled' === $b_status ? 2 : ( $b_date >= $today && ! in_array( $b_status, $inactive_statuses, true ) ? 0 : 1 );

				if ( $a_group !== $b_group ) {
					return $a_group <=> $b_group;
				}

				// Upcoming appointments: nearest date and earliest serial first.
				if ( 0 === $a_group ) {
					$date_compare = strcmp( $a_date, $b_date );
					if ( 0 !== $date_compare ) {
						return $date_compare;
					}
					return absint( $a->serial_number ?? 0 ) <=> absint( $b->serial_number ?? 0 );
				}

				// Previous and cancelled appointments: most recent date first.
				$date_compare = strcmp( $b_date, $a_date );
				if ( 0 !== $date_compare ) {
					return $date_compare;
				}
				return absint( $b->serial_number ?? 0 ) <=> absint( $a->serial_number ?? 0 );
			}
		);
		return $appointments;
	}

	public function get_upcoming_appointment( $patient_id ) {
		$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		foreach ( $this->get_accessible_patient_profiles() as $profile ) {
			$list = CAS_Appointment::search( array( 'patient_id' => $profile->id, 'date_from' => $today, 'limit' => 20, 'order' => 'ASC' ) );
			foreach ( $list as $a ) {
				if ( ! in_array( $a->status, array( 'cancelled', 'completed', 'no_show' ), true ) ) { return $a; }
			}
		}
		return null;
	}

	public function get_waiting_list_rows() {
		global $wpdb;
		$profiles = $this->get_accessible_patient_profiles();
		$ids = array_values( array_filter( array_map( 'absint', wp_list_pluck( $profiles, 'id' ) ) ) );
		if ( empty( $ids ) ) { return array(); }
		$waiting = CAS_DB::table( 'waiting_list' );
		$patients = CAS_DB::table( 'patients' );
		$doctors = CAS_DB::table( 'doctors' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT w.*,p.full_name patient_name,p.mobile patient_mobile,d.name doctor_name FROM {$waiting} w LEFT JOIN {$patients} p ON p.id=w.patient_id LEFT JOIN {$doctors} d ON d.id=w.doctor_id WHERE w.patient_id IN ({$placeholders}) ORDER BY w.appointment_date DESC,w.queue_number ASC", $ids ) );
	}

	public function get_patient_messages( $patient_id ) {
		global $wpdb;
		$t = CAS_DB::table( 'messages' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE patient_id=%d ORDER BY created_at ASC", absint( $patient_id ) ) );
	}

	public function format_date( $date ) { $ts = strtotime( $date ); return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : sanitize_text_field( $date ); }
	public function format_time( $time ) { $ts = strtotime( '2000-01-01 ' . $time ); return $ts ? date_i18n( get_option( 'time_format' ), $ts ) : sanitize_text_field( $time ); }


	/**
	 * Determine whether a patient has the minimum identity required to book.
	 * Mobile is verified by the OTP session and is never trusted from form data.
	 */
	public function is_minimum_profile_complete( $patient ) {
		return $patient && ! empty( $patient->mobile ) && ! empty( $patient->full_name ) && absint( CAS_Patient::calculate_age( $patient->date_of_birth ) ) >= 0 && ! empty( $patient->gender );
	}

	/** Return optional fields that may be completed later without blocking booking. */
	public function get_missing_optional_profile_fields( $patient ) {
		$missing = array();
		foreach ( array( 'email', 'blood_group', 'city', 'address' ) as $field ) {
			if ( ! $patient || empty( $patient->{$field} ) ) { $missing[] = $field; }
		}
		return $missing;
	}

	/** Whitelisted destination used only after creating a new patient profile. */
	private function get_post_registration_destination() {
		$settings = CAS_DB::get_settings();
		$key = 'dashboard' === sanitize_key( (string) ( $settings['after_new_patient_registration'] ?? 'booking' ) ) ? 'portal_dashboard_page_id' : 'portal_booking_page_id';
		return $this->page_url( absint( $settings[ $key ] ?? 0 ) );
	}

	/** Render a non-blocking reminder when optional profile information is missing. */
	public function render_profile_completion_notice( $patient, $context = 'dashboard' ) {
		if ( empty( $this->get_missing_optional_profile_fields( $patient ) ) ) { return ''; }
		$settings = CAS_DB::get_settings();
		$dashboard_url = $this->page_url( absint( $settings['portal_dashboard_page_id'] ?? 0 ) );
		ob_start(); ?>
		<div class="cas-notice cas-profile-completion-notice" data-cas-profile-completion-notice>
			<strong><?php echo esc_html__( 'Complete your profile', 'cas' ); ?></strong>
			<p><?php echo esc_html__( 'Your basic profile is ready. Add your email, blood group, city, or address to help the chamber provide better service.', 'cas' ); ?></p>
			<div class="cas-profile-reminder-actions">
				<a class="cas-button cas-button-primary" href="<?php echo esc_url( $dashboard_url . '#cas-profile-section' ); ?>"><?php echo esc_html__( 'Complete Profile', 'cas' ); ?></a>
				<button type="button" class="cas-button cas-button-link" data-cas-dismiss-profile-reminder><?php echo esc_html__( 'Later', 'cas' ); ?></button>
			</div>
		</div>
		<?php return ob_get_clean();
	}

	public function ajax_send_otp() {
		$this->nonce();
		$result = CAS_OTP::send( sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ) );
		is_wp_error( $result ) ? wp_send_json_error( array( 'message' => $result->get_error_message() ) ) : wp_send_json_success( $result );
	}

	public function ajax_verify_otp() {
		$this->nonce();
		$result = CAS_OTP::verify( sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['otp'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
		$_SESSION['cas_verified_mobile'] = $result['mobile'];
		if ( 'auto_login' === $result['action'] && ! empty( $result['patients'][0]->id ) ) {
			$this->login( $result['patients'][0]->id );
			wp_send_json_success( array( 'action' => 'auto_login', 'redirect' => $this->page_url( absint( CAS_DB::get_option( 'portal_dashboard_page_id', 0 ) ) ) ) );
		}
		wp_send_json_success( array( 'action' => $result['action'], 'profiles' => $result['patients'] ) );
	}

	public function ajax_select_profile() {
		$this->nonce();
		$mobile = CAS_DB::normalize_mobile( $_SESSION['cas_verified_mobile'] ?? '' );
		if ( ! $mobile ) { wp_send_json_error( array( 'message' => __( 'Verified mobile session expired. Please request OTP again.', 'cas' ) ) ); }
		$patient_id = absint( $_POST['profile_id'] ?? 0 );
		if ( $patient_id ) {
			$patient = CAS_Patient::get_by_id( $patient_id );
			if ( ! $patient || $patient->mobile !== $mobile ) { wp_send_json_error( array( 'message' => __( 'Invalid profile for this mobile.', 'cas' ) ) ); }
		} else {
			$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
			$age       = absint( $_POST['age'] ?? 0 );
			$gender    = sanitize_key( wp_unslash( $_POST['gender'] ?? '' ) );
			if ( '' === $full_name || $age < 1 || $age > 125 || ! in_array( $gender, array( 'male', 'female', 'other' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Please provide the patient name, a valid age, and gender.', 'cas' ) ) );
			}
			$patient_id = CAS_Patient::create( array(
				'full_name' => $full_name,
				'mobile' => $mobile,
				'age' => $age,
				'gender' => $gender,
				'address' => '', 'city' => '', 'blood_group' => '', 'email' => '',
				'require_demographics' => 1,
			) );
		}
		if ( is_wp_error( $patient_id ) ) { wp_send_json_error( array( 'message' => $patient_id->get_error_message() ) ); }
		$this->login( $patient_id );
		$redirect = empty( $_POST['profile_id'] ) ? $this->get_post_registration_destination() : $this->page_url( absint( CAS_DB::get_option( 'portal_dashboard_page_id', 0 ) ) );
		wp_send_json_success( array( 'redirect' => $redirect, 'new_patient' => empty( $_POST['profile_id'] ) ) );
	}

	public function ajax_update_profile() {
		$this->nonce();
		$patient_id = $this->require_patient_ajax();
		$current = CAS_Patient::get_by_id( $patient_id );
		$result = CAS_Patient::update( $patient_id, array(
			'full_name'     => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			'mobile'        => $current ? $current->mobile : '',
			'email'         => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'age'           => absint( $_POST['age'] ?? 0 ),
			'gender'        => sanitize_key( wp_unslash( $_POST['gender'] ?? '' ) ),
			'blood_group'   => sanitize_text_field( wp_unslash( $_POST['blood_group'] ?? '' ) ),
			'address'       => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'notes'         => $current ? $current->notes : '',
			'is_active'     => 1,
		) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not update profile. Please check required fields and try again.', 'cas' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'cas' ) ) );
	}

	public function ajax_add_family_member() {
		$this->nonce();
		$primary_id = $this->require_patient_ajax();
		$primary = CAS_Patient::get_by_id( $primary_id );
		if ( ! $primary ) { wp_send_json_error( array( 'message' => __( 'Primary patient not found.', 'cas' ) ) ); }
		$name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$relation = sanitize_text_field( wp_unslash( $_POST['relation'] ?? '' ) );
		if ( '' === $name || '' === $relation ) { wp_send_json_error( array( 'message' => __( 'Family member name and relation are required.', 'cas' ) ) ); }
		$gender = sanitize_key( wp_unslash( $_POST['gender'] ?? '' ) );
		$age = absint( $_POST['age'] ?? 0 );
		$blood = sanitize_text_field( wp_unslash( $_POST['blood_group'] ?? '' ) );
		$linked_patient_id = CAS_Patient::create( array( 'full_name' => $name, 'mobile' => $primary->mobile, 'age' => $age, 'gender' => $gender, 'blood_group' => $blood, 'is_active' => 1 ) );
		if ( is_wp_error( $linked_patient_id ) ) { wp_send_json_error( array( 'message' => $linked_patient_id->get_error_message() ) ); }
		$family_id = CAS_Patient::add_family_member( $primary_id, array( 'full_name' => $name, 'relation' => $relation, 'age' => $age, 'gender' => $gender, 'blood_group' => $blood, 'notes' => 'Linked patient profile ID: ' . absint( $linked_patient_id ), 'is_active' => 1 ) );
		if ( is_wp_error( $family_id ) ) { wp_send_json_error( array( 'message' => $family_id->get_error_message() ) ); }
		$linked_patient = CAS_Patient::get_by_id( $linked_patient_id );
		wp_send_json_success( array(
			'message' => __( 'Relative added and selected for this appointment.', 'cas' ),
			'profile' => array(
				'id'        => absint( $linked_patient_id ),
				'full_name' => $linked_patient ? (string) $linked_patient->full_name : $name,
				'mobile'    => $linked_patient ? (string) $linked_patient->mobile : (string) $primary->mobile,
				'gender'    => $linked_patient ? (string) $linked_patient->gender : $gender,
				'relation'  => $relation,
			),
		) );
	}

	public function ajax_get_available_dates() {
		$this->nonce();
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $_POST['doctor_id'] ?? 0 ) );
		$month = sanitize_text_field( wp_unslash( $_POST['month'] ?? gmdate( 'Y-m' ) ) );
		$schedule = CAS_Appointment::get_schedule( $doctor_id );
		if ( ! $doctor_id || ! $schedule || ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) { wp_send_json_error( array( 'message' => __( 'Doctor and month are required.', 'cas' ) ) ); }
		$active_days = array_filter( array_map( 'trim', explode( ',', $schedule->active_days ) ), 'strlen' );
		$holidays = json_decode( (string) $schedule->holidays, true );
		$holidays = is_array( $holidays ) ? $holidays : array();
		$out = array();
		$days = absint( gmdate( 't', strtotime( $month . '-01' ) ) );
		for ( $i = 1; $i <= $days; $i++ ) {
			$date = sprintf( '%s-%02d', $month, $i );
			$dow = gmdate( 'w', strtotime( $date ) );
			$serials = ( in_array( $dow, $active_days, true ) && ! in_array( $date, $holidays, true ) ) ? CAS_Appointment::get_available_serials( $doctor_id, $date ) : array();
			$out[] = array( 'date' => $date, 'is_active_day' => in_array( $dow, $active_days, true ), 'is_holiday' => in_array( $date, $holidays, true ), 'available_count' => is_wp_error( $serials ) ? 0 : count( $serials ) );
		}
		wp_send_json_success( array( 'dates' => $out ) );
	}

	public function ajax_get_available_serials() {
		$this->nonce();
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $_POST['doctor_id'] ?? 0 ) );
		$date = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		$date_check = CAS_Appointment::validate_patient_booking_date( $date );
		if ( is_wp_error( $date_check ) ) { wp_send_json_error( array( 'message' => $date_check->get_error_message(), 'code' => $date_check->get_error_code() ) ); }
		$editing_id = absint( $_POST['appointment_id'] ?? 0 );
		if ( $editing_id ) {
			$editing = CAS_Appointment::get_by_id( $editing_id );
			if ( ! $editing || ! $this->patient_profile_is_accessible( absint( $editing->patient_id ) ) ) { wp_send_json_error( array( 'message' => __( 'Appointment was not found for this login.', 'cas' ) ) ); }
		}
		$serials = CAS_Appointment::get_available_serials( $doctor_id, $date, $editing_id );
		if ( is_wp_error( $serials ) ) { wp_send_json_error( array( 'message' => $serials->get_error_message() ) ); }
		$out = array();
		foreach ( $serials as $serial ) {
			$time = CAS_Appointment::calculate_reporting_time( $serial, $doctor_id, $date );
			$out[] = array( 'serial' => absint( $serial ), 'reporting_time' => is_wp_error( $time ) ? '' : $time, 'reporting_time_display' => is_wp_error( $time ) ? '' : $this->format_time( $time ) );
		}
		$queue = empty( $out ) ? CAS_Waiting_List::get_queue_number( $doctor_id, $date ) : 0;
		wp_send_json_success( array( 'serials' => $out, 'is_fully_booked' => empty( $out ), 'queue_number' => is_wp_error( $queue ) ? 0 : absint( $queue ) ) );
	}

	public function ajax_book_appointment() {
		$this->nonce();
		$current_id = $this->require_patient_ajax();
		$patient_id = absint( $_POST['patient_id'] ?? $current_id );
		if ( ! $this->patient_profile_is_accessible( $patient_id ) ) { wp_send_json_error( array( 'message' => __( 'Selected patient profile is not allowed for this login.', 'cas' ) ) ); }
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $_POST['doctor_id'] ?? 0 ) );
		if ( ! $doctor_id ) { wp_send_json_error( array( 'message' => __( 'The appointment doctor is not configured. Please contact the chamber.', 'cas' ) ) ); }
		$date_check = CAS_Appointment::validate_patient_booking_date( sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ) );
		if ( is_wp_error( $date_check ) ) { wp_send_json_error( array( 'message' => $date_check->get_error_message(), 'code' => $date_check->get_error_code() ) ); }
		$result = CAS_Appointment::create( array( 'doctor_id' => $doctor_id, 'patient_id' => $patient_id, 'appointment_date' => sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ), 'serial_number' => absint( $_POST['serial_number'] ?? 0 ), 'status' => 'confirmed', 'source' => 'frontend', 'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ) ) );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) ); }
		$profile = CAS_Patient::get_by_id( $current_id );
		wp_send_json_success( array(
			'message' => __( 'Appointment booked successfully.', 'cas' ),
			'profile_incomplete' => ! empty( $this->get_missing_optional_profile_fields( $profile ) ),
			'profile_url' => $this->page_url( absint( CAS_DB::get_option( 'portal_dashboard_page_id', 0 ) ) ) . '#cas-profile-section',
		) );
	}

	/** Check whether a selected self/family profile already has an active appointment. */
	public function ajax_get_patient_active_appointment() {
		$this->nonce();
		$current_id = $this->require_patient_ajax();
		$patient_id = absint( $_POST['patient_id'] ?? $current_id );
		$exclude_id = absint( $_POST['exclude_appointment_id'] ?? 0 );
		if ( ! $this->patient_profile_is_accessible( $patient_id ) ) { wp_send_json_error( array( 'message' => __( 'Selected patient profile is not allowed for this login.', 'cas' ) ) ); }
		$appointment = CAS_Appointment::get_patient_active_appointment( $patient_id, $exclude_id );
		if ( ! $appointment ) { wp_send_json_success( array( 'has_active' => false ) ); }
		wp_send_json_success( array( 'has_active' => true, 'appointment_id' => absint( $appointment->id ), 'date' => sanitize_text_field( $appointment->appointment_date ), 'doctor_name' => sanitize_text_field( $appointment->doctor_name ?? '' ), 'serial_number' => absint( $appointment->serial_number ) ) );
	}

	/** Update a patient-owned appointment from the frontend booking workflow. */
	public function ajax_update_appointment() {
		$this->nonce();
		$this->require_patient_ajax();
		$appointment_id = absint( $_POST['appointment_id'] ?? 0 );
		$appointment = CAS_Appointment::get_by_id( $appointment_id );
		if ( ! $appointment || ! $this->patient_profile_is_accessible( absint( $appointment->patient_id ) ) ) { wp_send_json_error( array( 'message' => __( 'Appointment was not found for this login.', 'cas' ) ) ); }
		$doctor_id = CAS_DB::resolve_booking_doctor_id( absint( $_POST['doctor_id'] ?? 0 ) );
		if ( ! $doctor_id ) { wp_send_json_error( array( 'message' => __( 'The appointment doctor is not configured. Please contact the chamber.', 'cas' ) ) ); }
		$result = CAS_Appointment::update_patient_booking( $appointment_id, array( 'doctor_id' => $doctor_id, 'appointment_date' => sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ), 'serial_number' => absint( $_POST['serial_number'] ?? 0 ), 'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ) ) );
		is_wp_error( $result ) ? wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) ) : wp_send_json_success( array( 'message' => __( 'Appointment updated successfully.', 'cas' ) ) );
	}

	/** Cancel one manageable appointment belonging to a profile under this mobile account. */
	public function ajax_cancel_appointment() {
		$this->nonce();
		$this->require_patient_ajax();
		$appointment_id = absint( $_POST['appointment_id'] ?? 0 );
		$appointment = CAS_Appointment::get_by_id( $appointment_id );
		if ( ! $appointment || ! $this->patient_profile_is_accessible( absint( $appointment->patient_id ) ) ) { wp_send_json_error( array( 'message' => __( 'Appointment was not found for this login.', 'cas' ) ) ); }
		if ( ! CAS_Appointment::is_patient_manageable( $appointment ) ) { wp_send_json_error( array( 'message' => CAS_Appointment::patient_management_lock_message( $appointment ) ) ); }
		$result = CAS_Appointment::cancel( $appointment_id, __( 'Cancelled by patient from patient portal.', 'cas' ) );
		is_wp_error( $result ) || ! $result ? wp_send_json_error( array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not cancel appointment.', 'cas' ) ) ) : wp_send_json_success( array( 'message' => __( 'Appointment cancelled. You can now book another appointment for this patient.', 'cas' ) ) );
	}

	public function ajax_join_waiting_list() {
		$this->nonce();
		$current_id = $this->require_patient_ajax();
		$patient_id = absint( $_POST['patient_id'] ?? $current_id );
		if ( ! $this->patient_profile_is_accessible( $patient_id ) ) { wp_send_json_error( array( 'message' => __( 'Selected patient profile is not allowed for this login.', 'cas' ) ) ); }
		$date_check = CAS_Appointment::validate_patient_booking_date( sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ) );
		if ( is_wp_error( $date_check ) ) { wp_send_json_error( array( 'message' => $date_check->get_error_message(), 'code' => $date_check->get_error_code() ) ); }
		$result = CAS_Waiting_List::add_to_list( array( 'doctor_id' => CAS_DB::resolve_booking_doctor_id( absint( $_POST['doctor_id'] ?? 0 ) ), 'patient_id' => $patient_id, 'appointment_date' => sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ) ) );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
		$row = CAS_Waiting_List::get_by_id( $result );
		$q = $row ? absint( $row->queue_number ) : 0;
		$date_label = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		wp_send_json_success( array( 'message' => sprintf( __( 'You have Joined Waiting List with Queue number %1$d for Date %2$s. You will receive message or call if any slots are free for appointment. Kindly take note: Waiting list does not confirm any appointment. Appointment will be given if any appointment slot becomes free. Thank you.', 'cas' ), $q, $date_label ), 'queue_number' => $q, 'appointment_date' => $date_label ) );
	}

	public function ajax_send_message() {
		$this->nonce();
		$patient_id  = $this->require_patient_ajax();
		$message     = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$attachment  = CAS_DB::handle_message_attachment_upload( 'attachment' );

		if ( is_wp_error( $attachment ) ) {
			wp_send_json_error( array( 'message' => $attachment->get_error_message() ) );
		}

		$inserted = CAS_DB::insert_message( $patient_id, 'patient_to_chamber', $message, $attachment, 0 );
		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( array( 'message' => $inserted->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Message sent.', 'cas' ),
				'message_id' => absint( $inserted ),
			)
		);
	}


	public function ajax_get_messages() {
		$this->nonce();
		$patient_id = $this->require_patient_ajax();
		$messages = $this->get_patient_messages( $patient_id );
		$out = array();
		foreach ( $messages as $m ) {
			$out[] = array(
				'id'         => absint( $m->id ),
				'direction'  => sanitize_key( $m->direction ),
				'message'    => wp_kses_post( $m->message ),
				'attachment' => CAS_DB::message_attachment_for_display( $m ),
				'created_at' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $m->created_at ),
			);
		}
		wp_send_json_success( array( 'messages' => $out ) );
	}

	public function ajax_logout() {
		$this->nonce();
		if ( ! empty( $_SESSION['cas_patient_token'] ) ) { delete_transient( self::SESSION_TRANSIENT_KEY . sanitize_text_field( wp_unslash( $_SESSION['cas_patient_token'] ) ) ); }
		$_SESSION = array();
		if ( PHP_SESSION_ACTIVE === session_status() ) { session_destroy(); }
		wp_send_json_success( array( 'redirect' => home_url( '/' ) ) );
	}

}
