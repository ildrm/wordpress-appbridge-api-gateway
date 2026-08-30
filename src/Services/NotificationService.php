<?php
namespace AppBridge\ApiGateway\Services;

defined( 'ABSPATH' ) || exit;
final class NotificationService {
	public static function create( int $user_id, string $type, string $title, string $body = '', array $data = array() ): int {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix.'appbridge_notifications', array(
			'user_id'=>$user_id,'type'=>sanitize_key($type),'title'=>sanitize_text_field($title),'body'=>sanitize_textarea_field($body),
			'data_json'=>wp_json_encode($data),'created_at'=>current_time('mysql',true)
		), array('%d','%s','%s','%s','%s','%s') );
		return (int)$wpdb->insert_id;
	}
}
