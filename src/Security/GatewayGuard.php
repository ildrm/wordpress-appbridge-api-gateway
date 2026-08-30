<?php
namespace AppBridge\ApiGateway\Security;
use AppBridge\ApiGateway\Support\Settings;
defined( 'ABSPATH' ) || exit;
final class GatewayGuard {
	public function register():void{add_filter('rest_pre_dispatch',array($this,'guard'),5,3);}
	public function guard($result,$server,$request){$route=$request->get_route();if(0!==strpos($route,'/appbridge/v1/'))return $result;$always=array('/appbridge/v1/bootstrap','/appbridge/v1/capabilities');if(!Settings::get('enabled',true)&&!current_user_can('manage_options')&&!in_array($route,$always,true))return new \WP_Error('appbridge_disabled',__('The AppBridge API is disabled.','appbridge-api-gateway'),array('status'=>503));if(Settings::get('maintenance_mode',false)&&!current_user_can('manage_options')&&!in_array($route,$always,true))return new \WP_Error('appbridge_maintenance',__('The application API is temporarily in maintenance mode.','appbridge-api-gateway'),array('status'=>503));return $result;}
}
