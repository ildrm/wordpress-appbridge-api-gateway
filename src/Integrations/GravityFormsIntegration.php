<?php
namespace AppBridge\ApiGateway\Integrations;

use AppBridge\ApiGateway\Support\Response;
use AppBridge\ApiGateway\Services\IdempotencyService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;
final class GravityFormsIntegration implements IntegrationInterface {
	public function key(): string { return 'gravity_forms'; }
	public function name(): string { return 'Gravity Forms'; }
	public function available(): bool { return class_exists( 'GFAPI' ) && class_exists( 'GFFormDisplay' ); }
	public function capabilities(): array { return array( 'forms', 'submission', 'validation' ); }
	public function health(): array { return array( 'ok'=>$this->available(), 'version'=>defined('GF_VERSION')?GF_VERSION:null ); }
	public function register_routes(): void {
		register_rest_route( 'appbridge/v1', '/forms/gravity/(?P<id>\d+)', array( 'methods'=>'GET', 'callback'=>array($this,'form'), 'permission_callback'=>'__return_true' ) );
		register_rest_route( 'appbridge/v1', '/forms/gravity/(?P<id>\d+)/submit', array( 'methods'=>'POST', 'callback'=>array($this,'submit'), 'permission_callback'=>'__return_true' ) );
	}
	public function form( WP_REST_Request $r ) {
		$form = \GFAPI::get_form( (int)$r['id'] );
		if ( ! $form || empty($form['is_active']) ) return new \WP_Error('appbridge_form_not_found',__('Form not found.','appbridge-api-gateway'),array('status'=>404));
		$fields=array(); foreach($form['fields'] as $field){ $fields[]=array('id'=>$field->id,'type'=>$field->type,'label'=>$field->label,'required'=>(bool)$field->isRequired,'choices'=>$field->choices); }
		return Response::success(array('id'=>(int)$form['id'],'title'=>$form['title'],'description'=>$form['description'],'fields'=>$fields));
	}
	public function submit( WP_REST_Request $r ) {
		$replay = IdempotencyService::replay( $r ); if ( $replay ) { return $replay; }
		$form_id=(int)$r['id']; $form=\GFAPI::get_form($form_id);
		if(!$form || empty($form['is_active'])) return new \WP_Error('appbridge_form_not_found',__('Form not found.','appbridge-api-gateway'),array('status'=>404));
		$params=$r->get_json_params(); if(!is_array($params)) $params=$r->get_body_params();
		$input=array(); foreach($params as $k=>$v){ if(preg_match('/^input_\d+(?:_\d+)?$/',(string)$k)) $input[$k]=is_scalar($v)?sanitize_text_field((string)$v):$v; }
		$result=\GFAPI::submit_form($form_id,$input);
		if(is_wp_error($result)) return $result;
		$response = Response::success($result, !empty($result['is_valid'])?201:422); IdempotencyService::store($r,$response); return $response;
	}
}
