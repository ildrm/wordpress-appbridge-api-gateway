<?php
namespace AppBridge\ApiGateway\Services;
use AppBridge\ApiGateway\Support\Request;
defined( 'ABSPATH' ) || exit;
final class DeviceService {
	public function upsert( int $user_id, array $data ): array {
		global $wpdb; $table=$wpdb->prefix.'appbridge_devices';
		$uuid=sanitize_text_field($data['device_uuid']??'');
		$existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND device_uuid=%s",$user_id,$uuid));
		$row=array('user_id'=>$user_id,'device_uuid'=>$uuid,'platform'=>sanitize_key($data['platform']??'unknown'),'push_token'=>isset($data['push_token'])?sanitize_text_field($data['push_token']):null,'app_version'=>isset($data['app_version'])?sanitize_text_field($data['app_version']):null,'device_name'=>isset($data['device_name'])?sanitize_text_field($data['device_name']):null,'locale'=>isset($data['locale'])?sanitize_text_field($data['locale']):null,'last_ip'=>Request::ip(),'last_seen_at'=>current_time('mysql',true));
		if($existing){$wpdb->update($table,$row,array('id'=>(int)$existing));$id=(int)$existing;}else{$row['created_at']=current_time('mysql',true);$wpdb->insert($table,$row);$id=(int)$wpdb->insert_id;}
		return array('id'=>$id,'device_uuid'=>$uuid,'platform'=>$row['platform']);
	}
}
