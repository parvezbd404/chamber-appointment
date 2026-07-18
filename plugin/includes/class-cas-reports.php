<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class CAS_Reports {
	private static function date($d){$d=sanitize_text_field((string)$d);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)?$d:'';}
	private static function doctor_scope_sql( $doctor, $doctor_ids, &$v, $prefix = '' ) {
		$doctor = absint( $doctor );
		if ( $doctor ) { $v[] = $doctor; return " AND {$prefix}doctor_id=%d"; }
		$doctor_ids = array_values( array_filter( array_map( 'absint', (array) $doctor_ids ) ) );
		if ( ! empty( $doctor_ids ) ) { $v = array_merge( $v, $doctor_ids ); return " AND {$prefix}doctor_id IN (" . implode( ',', array_fill( 0, count( $doctor_ids ), '%d' ) ) . ')'; }
		return '';
	}
	public static function get_summary($args=array()){
		global $wpdb;
		$date=self::date($args['date']??''); $from=self::date($args['date_from']??'')?:($date?:gmdate('Y-m-d',current_time('timestamp'))); $to=self::date($args['date_to']??'')?:$from; $doctor=absint($args['doctor_id']??0); $doctor_ids=(array)($args['doctor_ids']??array());
		$t=CAS_DB::table('appointments'); $where='appointment_date >= %s AND appointment_date <= %s'; $v=array($from,$to); $where .= self::doctor_scope_sql( $doctor, $doctor_ids, $v, '' );
		$rows=$wpdb->get_results($wpdb->prepare("SELECT status,COUNT(*) total FROM {$t} WHERE {$where} GROUP BY status",$v));
		$counts=array_fill_keys(CAS_Appointment::$statuses,0); foreach($rows as $r){$counts[$r->status]=absint($r->total);} return array('date_from'=>$from,'date_to'=>$to,'doctor_id'=>$doctor,'total_appointments'=>array_sum($counts),'counts'=>$counts,'waiting_list_count'=>self::waiting_count($from,$to,$doctor,$doctor_ids),'sms_sent_count'=>self::sms_sent_count($from,$to),'no_shows'=>$counts['no_show']);
	}
	private static function waiting_count($from,$to,$doctor=0,$doctor_ids=array()){
		global $wpdb; $t=CAS_DB::table('waiting_list'); $w='appointment_date >= %s AND appointment_date <= %s AND status=%s'; $v=array($from,$to,'waiting'); $w .= self::doctor_scope_sql( $doctor, $doctor_ids, $v, '' ); return absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE {$w}",$v)));
	}
	public static function get_by_date_range($from,$to,$doctor=0){ return CAS_Appointment::search(array('date_from'=>self::date($from),'date_to'=>self::date($to),'doctor_id'=>absint($doctor),'limit'=>500)); }
	public static function get_by_doctor($doctor,$from='',$to=''){ return self::get_summary(array('doctor_id'=>absint($doctor),'date_from'=>$from,'date_to'=>$to)); }
	public static function sms_sent_count($from='',$to=''){ global $wpdb; $from=self::date($from)?:gmdate('Y-m-d',current_time('timestamp')); $to=self::date($to)?:$from; $t=CAS_DB::table('sms_logs'); return absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE created_at BETWEEN %s AND %s",$from.' 00:00:00',$to.' 23:59:59'))); }
	public static function export_csv_data($args=array()){ $rows=array(); foreach(CAS_Appointment::search(array_merge($args,array('limit'=>500))) as $a){ $rows[]=array($a->id,$a->appointment_date,$a->doctor_name,$a->serial_number,$a->reporting_time,$a->patient_name,$a->patient_mobile,$a->status,$a->source,$a->created_at); } return array('headers'=>array('ID','Date','Doctor','Serial','Reporting Time','Patient','Mobile','Status','Source','Created'),'rows'=>$rows); }
}
