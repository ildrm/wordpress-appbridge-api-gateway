<?php
namespace AppBridge\ApiGateway\Services;
use WP_REST_Request;
defined( 'ABSPATH' ) || exit;
final class IdempotencyService {
	public static function replay(WP_REST_Request $request): ?\WP_REST_Response {
		$key=trim((string)$request->get_header('Idempotency-Key')); if(''===$key)return null;
		if(strlen($key)>191||!preg_match('/^[A-Za-z0-9._:-]{8,191}$/',$key))return new \WP_REST_Response(array('code'=>'appbridge_invalid_idempotency_key','message'=>__('Invalid Idempotency-Key.','appbridge-api-gateway')),400);
		global $wpdb;$route=$request->get_route();$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}appbridge_idempotency WHERE route=%s AND idempotency_key=%s AND expires_at>%s LIMIT 1",$route,$key,current_time('mysql',true)),ARRAY_A);
		if(!$row)return null;$hash=hash('sha256',(string)$request->get_body());if(!hash_equals($row['request_hash'],$hash))return new \WP_REST_Response(array('code'=>'appbridge_idempotency_conflict','message'=>__('The idempotency key was already used with a different request.','appbridge-api-gateway')),409);
		return new \WP_REST_Response(json_decode((string)$row['response_json'],true),(int)$row['status_code']);
	}
	public static function store(WP_REST_Request $request,\WP_REST_Response $response):void{$key=trim((string)$request->get_header('Idempotency-Key'));if(''===$key||strlen($key)>191)return;global $wpdb;$wpdb->replace($wpdb->prefix.'appbridge_idempotency',array('idempotency_key'=>$key,'user_id'=>get_current_user_id()?:null,'route'=>$request->get_route(),'request_hash'=>hash('sha256',(string)$request->get_body()),'status_code'=>$response->get_status(),'response_json'=>wp_json_encode($response->get_data()),'expires_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS),'created_at'=>current_time('mysql',true)));}
}
