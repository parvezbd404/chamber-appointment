<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Lightweight Bangla/English frontend toggle. Uses a scoped gettext map so existing PHP views need no duplicate templates. */
class CAS_I18n {
	const COOKIE = 'cas_language';

	public static function register_hooks() {
		add_filter( 'gettext', array( __CLASS__, 'translate' ), 20, 3 );
	}

	public static function current_language() {
		$requested = isset( $_GET['cas_lang'] ) ? sanitize_key( wp_unslash( $_GET['cas_lang'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$language = in_array( $requested, array( 'bn', 'en' ), true ) ? $requested : $cookie;
		if ( ! in_array( $language, array( 'bn', 'en' ), true ) ) {
			$language = sanitize_key( (string) CAS_DB::get_option( 'frontend_default_language', 'bn' ) );
		}
		return in_array( $language, array( 'bn', 'en' ), true ) ? $language : 'bn';
	}

	public static function is_bangla() { return 'bn' === self::current_language(); }

	public static function translate( $translated, $text, $domain ) {
		if ( is_admin() && ! wp_doing_ajax() ) { return $translated; }
		if ( 'cas' !== $domain || ! self::is_bangla() ) { return $translated; }
		$map = self::map();
		return array_key_exists( $text, $map ) ? $map[ $text ] : $translated;
	}

	public static function toggle_html() {
		$language = self::current_language();
		ob_start();
		?>
		<div class="cas-language-toggle" role="group" aria-label="<?php echo esc_attr__( 'Language selection', 'cas' ); ?>">
			<button type="button" class="<?php echo 'bn' === $language ? 'is-active' : ''; ?>" data-cas-language="bn" aria-pressed="<?php echo 'bn' === $language ? 'true' : 'false'; ?>">বাংলা</button>
			<button type="button" class="<?php echo 'en' === $language ? 'is-active' : ''; ?>" data-cas-language="en" aria-pressed="<?php echo 'en' === $language ? 'true' : 'false'; ?>">English</button>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function map() {
		return array(
			'Language selection' => 'ভাষা নির্বাচন',
			'Patient portal menu' => 'রোগী পোর্টাল মেনু',
			'Dashboard' => 'ড্যাশবোর্ড',
			'Book Appointment' => 'অ্যাপয়েন্টমেন্ট বুক করুন',
			'My Appointments' => 'আমার অ্যাপয়েন্টমেন্ট',
			'Doctor' => 'চিকিৎসক',
			'Patient' => 'রোগী',
			'Messages' => 'বার্তা',
			'Diabetes Care Portal' => 'ডায়াবেটিস কেয়ার পোর্টাল',
			'Please log in first.' => 'অনুগ্রহ করে আগে লগ ইন করুন।',
			'To use this patient portal page, please log in with your mobile OTP. New patients can also create their account after OTP verification.' => 'এই রোগী পোর্টাল ব্যবহার করতে মোবাইল OTP দিয়ে লগ ইন করুন। নতুন রোগী OTP যাচাইয়ের পরে অ্যাকাউন্ট তৈরি করতে পারবেন।',
			'Login / Create Your Account' => 'লগ ইন / অ্যাকাউন্ট তৈরি করুন',
			'Chamber appointment' => 'চেম্বার অ্যাপয়েন্টমেন্ট',
			'Book an Appointment' => 'অ্যাপয়েন্টমেন্ট বুক করুন',
			'Modify Appointment' => 'অ্যাপয়েন্টমেন্ট পরিবর্তন করুন',
			'Update the date or serial, then review the booking before saving.' => 'তারিখ বা সিরিয়াল পরিবর্তন করে সংরক্ষণের আগে বুকিংটি পর্যালোচনা করুন।',
			'Choose the patient, select a date and serial, then confirm.' => 'রোগী নির্বাচন করুন, তারিখ ও সিরিয়াল বেছে নিয়ে নিশ্চিত করুন।',
			'Online change is unavailable' => 'অনলাইনে পরিবর্তন করা যাচ্ছে না',
			'Back to My Appointments' => 'আমার অ্যাপয়েন্টমেন্টে ফিরে যান',
			'Appointment booking steps' => 'অ্যাপয়েন্টমেন্ট বুকিংয়ের ধাপ',
			'Details' => 'বিবরণ',
			'Serial' => 'সিরিয়াল',
			'Confirm' => 'নিশ্চিত করুন',
			'Appointment details' => 'অ্যাপয়েন্টমেন্টের বিবরণ',
			'Select who will attend, then choose a suitable date. Available serials will open automatically.' => 'কে আসবেন তা নির্বাচন করুন, এরপর উপযুক্ত তারিখ বেছে নিন। খালি সিরিয়াল স্বয়ংক্রিয়ভাবে দেখা যাবে।',
			'Select your doctor, choose who will attend, then choose a suitable date. Serials will open automatically.' => 'ডাক্তার ও যিনি আসবেন তাকে নির্বাচন করুন, এরপর উপযুক্ত তারিখ বেছে নিন। সিরিয়াল স্বয়ংক্রিয়ভাবে দেখা যাবে।',
			'Before booking' => 'বুকিংয়ের আগে',
			'Each patient profile can keep only one active appointment at a time.' => 'প্রতিটি রোগীর প্রোফাইলে এক সময়ে একটি সক্রিয় অ্যাপয়েন্টমেন্ট রাখা যাবে।',
			'Your doctor' => 'আপনার ডাক্তার',
			'Choose doctor' => 'ডাক্তার নির্বাচন করুন',
			'Select Doctor' => 'ডাক্তার নির্বাচন করুন',
			'Booking for' => 'কার জন্য বুকিং',
			'Add a relative' => 'স্বজন যোগ করুন',
			'Booking for someone new? Add a relative now; the new profile will be selected for this appointment.' => 'নতুন কারও জন্য বুকিং? এখন স্বজন যোগ করুন; এই অ্যাপয়েন্টমেন্টের জন্য তাঁর প্রোফাইলটি নির্বাচন হবে।',
			'Add a new relative' => 'নতুন স্বজন যোগ করুন',
			'This relative will be added under your mobile account and selected for this appointment.' => 'এই স্বজনকে আপনার মোবাইল অ্যাকাউন্টের অধীনে যোগ করা হবে এবং এই অ্যাপয়েন্টমেন্টের জন্য নির্বাচন করা হবে।',
			'Relative name' => 'স্বজনের নাম',
			'Relation' => 'সম্পর্ক',
			'Select relation' => 'সম্পর্ক নির্বাচন করুন',
			'Age' => 'বয়স',
			'Gender' => 'লিঙ্গ',
			'Select' => 'নির্বাচন করুন',
			'Male' => 'পুরুষ',
			'Female' => 'নারী',
			'Other' => 'অন্যান্য',
			'Blood group' => 'রক্তের গ্রুপ',
			'Cancel' => 'বাতিল',
			'Add Relative & Use for This Appointment' => 'স্বজন যোগ করে এই অ্যাপয়েন্টমেন্টে ব্যবহার করুন',
			'Appointment date' => 'অ্যাপয়েন্টমেন্টের তারিখ',
			'After selecting a date, the available serials will appear automatically.' => 'তারিখ নির্বাচন করার পরে খালি সিরিয়াল স্বয়ংক্রিয়ভাবে দেখা যাবে।',
			'Show available serials' => 'খালি সিরিয়াল দেখুন',
			'Choose your serial' => 'আপনার সিরিয়াল নির্বাচন করুন',
			'Tap one serial to enable Review booking.' => 'বুকিং পর্যালোচনা চালু করতে একটি সিরিয়ালে ট্যাপ করুন।',
			'This date is fully booked.' => 'এই তারিখের সব সিরিয়াল বুক হয়ে গেছে।',
			'You can continue to choose a patient and join the waiting list.' => 'আপনি রোগী নির্বাচন করে অপেক্ষমাণ তালিকায় যুক্ত হতে পারেন।',
			'Expected queue number:' => 'সম্ভাব্য ক্রমিক নম্বর:',
			'Change date' => 'তারিখ পরিবর্তন করুন',
			'Review booking' => 'বুকিং পর্যালোচনা',
			'Confirm changes' => 'পরিবর্তন নিশ্চিত করুন',
			'Confirm your booking' => 'আপনার বুকিং নিশ্চিত করুন',
			'Review the date, serial and patient information before confirming.' => 'নিশ্চিত করার আগে তারিখ, সিরিয়াল ও রোগীর তথ্য যাচাই করুন।',
			'Date' => 'তারিখ',
			'Serial / reporting time' => 'সিরিয়াল / রিপোর্টিং সময়',
			'Patient information' => 'রোগীর তথ্য',
			'Change' => 'পরিবর্তন',
			'Notes for the chamber' => 'চেম্বারের জন্য নোট',
			'Optional note' => 'ঐচ্ছিক নোট',
			'I agree to the appointment and privacy policy.' => 'আমি অ্যাপয়েন্টমেন্ট ও গোপনীয়তা নীতিতে সম্মত।',
			'Back' => 'পেছনে',
			'Confirm Appointment' => 'অ্যাপয়েন্টমেন্ট নিশ্চিত করুন',
			'Save Appointment Changes' => 'পরিবর্তন সংরক্ষণ করুন',
			'Confirm this appointment?' => 'এই অ্যাপয়েন্টমেন্ট নিশ্চিত করবেন?',
			'Log out?' => 'লগ আউট করবেন?',
			'Please enter the relative name and select the relationship.' => 'স্বজনের নাম লিখে সম্পর্ক নির্বাচন করুন।',
			'Relative added and selected for this appointment.' => 'স্বজন যোগ করা হয়েছে এবং এই অ্যাপয়েন্টমেন্টের জন্য নির্বাচন করা হয়েছে।',
			'Could not add this relative. Please try again.' => 'স্বজন যোগ করা যায়নি। আবার চেষ্টা করুন।',
			'Please agree to the appointment and privacy policy before continuing.' => 'পরবর্তী ধাপে যাওয়ার আগে অ্যাপয়েন্টমেন্ট ও গোপনীয়তা নীতিতে সম্মতি দিন।',
			'Please select a serial first.' => 'প্রথমে একটি সিরিয়াল নির্বাচন করুন।',
			'Please choose the patient who will attend this appointment.' => 'এই অ্যাপয়েন্টমেন্টে যিনি আসবেন তাঁকে নির্বাচন করুন।',
			'This date is fully booked. You may join the waiting list after choosing a patient.' => 'এই তারিখের সব সিরিয়াল বুক হয়ে গেছে। রোগী নির্বাচন করে অপেক্ষমাণ তালিকায় যুক্ত হতে পারেন।',
			'Choose one available serial below.' => 'নিচের খালি সিরিয়াল থেকে একটি নির্বাচন করুন।',
			'Could not load serials. Tap “Show available serials” to try again.' => 'সিরিয়াল লোড করা যায়নি। আবার চেষ্টা করতে “খালি সিরিয়াল দেখুন”-এ ট্যাপ করুন।',
			'Could not load serials automatically. Tap “Show available serials” to try again.' => 'সিরিয়াল স্বয়ংক্রিয়ভাবে লোড করা যায়নি। আবার চেষ্টা করতে “খালি সিরিয়াল দেখুন”-এ ট্যাপ করুন।',
			'Could not save appointment.' => 'অ্যাপয়েন্টমেন্ট সংরক্ষণ করা যায়নি।',
			'Waiting list request saved.' => 'অপেক্ষমাণ তালিকার অনুরোধ সংরক্ষণ করা হয়েছে।',
			'Could not join the waiting list.' => 'অপেক্ষমাণ তালিকায় যুক্ত হওয়া যায়নি।',
			'This date is a holiday.' => 'এই তারিখটি ছুটির দিন।',
			'This is not an active chamber day.' => 'এটি চেম্বারের সক্রিয় দিন নয়।',
			'serial(s) available for this date.' => 'টি সিরিয়াল এই তারিখে খালি আছে।',
			'Reporting time' => 'রিপোর্টিং সময়',
			'Save these appointment changes?' => 'এই অ্যাপয়েন্টমেন্টের পরিবর্তন সংরক্ষণ করবেন?',
			'That serial was just booked by another user. Please refresh and choose another serial.' => 'এই সিরিয়ালটি এইমাত্র অন্য একজন বুক করেছেন। অনুগ্রহ করে রিফ্রেশ করে অন্য সিরিয়াল নির্বাচন করুন।',
			'That serial is not available. Please refresh and choose another serial.' => 'এই সিরিয়ালটি খালি নেই। অনুগ্রহ করে রিফ্রেশ করে অন্য সিরিয়াল নির্বাচন করুন।',
			'Provide Your Basic Information' => 'আপনার প্রাথমিক তথ্য দিন',
			'Provide the information below to book an appointment. Other profile details can be added later.' => 'অ্যাপয়েন্টমেন্ট বুক করতে নিচের তথ্যগুলো দিন। অন্যান্য তথ্য পরে যোগ করতে পারবেন।',
			'Verified' => 'যাচাইকৃত',
			'Create Profile & Continue' => 'প্রোফাইল তৈরি করে এগিয়ে যান',
			'Complete your profile' => 'আপনার প্রোফাইল সম্পূর্ণ করুন',
			'Your basic profile is ready. Add your email, blood group, city, or address to help the chamber provide better service.' => 'আপনার প্রাথমিক প্রোফাইল তৈরি হয়েছে। আরও ভালো সেবার জন্য ইমেইল, রক্তের গ্রুপ, শহর বা ঠিকানা যোগ করতে পারেন।',
			'Complete Profile' => 'প্রোফাইল সম্পূর্ণ করুন',
			'Later' => 'পরে করব',
			'Appointment booked successfully.' => 'অ্যাপয়েন্টমেন্ট সফলভাবে বুক হয়েছে।',
			'Appointment updated successfully.' => 'অ্যাপয়েন্টমেন্ট সফলভাবে পরিবর্তন করা হয়েছে।',
			'Appointment cancelled.' => 'অ্যাপয়েন্টমেন্ট বাতিল করা হয়েছে।',
			'Appointment cancelled. You can now book another appointment for this patient.' => 'অ্যাপয়েন্টমেন্ট বাতিল করা হয়েছে। এখন এই রোগীর জন্য নতুন অ্যাপয়েন্টমেন্ট বুক করতে পারবেন।',
			'Pending' => 'অপেক্ষমাণ', 'Confirmed' => 'নিশ্চিত', 'Reconfirmed' => 'পুনঃনিশ্চিত', 'Cancelled' => 'বাতিল', 'Checked In' => 'উপস্থিত', 'Completed' => 'সম্পন্ন', 'No Show' => 'অনুপস্থিত',
		);
	}
}
