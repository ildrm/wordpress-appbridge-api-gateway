<?php
namespace AppBridge\ApiGateway\Api;

use AppBridge\ApiGateway\Auth\TokenService;
use AppBridge\ApiGateway\Integrations\Registry;
use AppBridge\ApiGateway\Security\RateLimiter;
use AppBridge\ApiGateway\Services\AuditService;
use AppBridge\ApiGateway\Services\DeviceService;
use AppBridge\ApiGateway\Support\Response;
use AppBridge\ApiGateway\Support\Settings;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Routes {
	public function __construct(private Registry $integrations) {}
	public function register(): void { add_action('rest_api_init',array($this,'routes')); }
	public function routes(): void {
		register_rest_route('appbridge/v1','/bootstrap',array('methods'=>'GET','callback'=>array($this,'bootstrap'),'permission_callback'=>'__return_true'));
		register_rest_route('appbridge/v1','/capabilities',array('methods'=>'GET','callback'=>array($this,'capabilities'),'permission_callback'=>'__return_true'));
		register_rest_route('appbridge/v1','/auth/register',array('methods'=>'POST','callback'=>array($this,'register_user'),'permission_callback'=>'__return_true'));
		register_rest_route('appbridge/v1','/auth/login',array('methods'=>'POST','callback'=>array($this,'login'),'permission_callback'=>'__return_true'));
		register_rest_route('appbridge/v1','/auth/refresh',array('methods'=>'POST','callback'=>array($this,'refresh'),'permission_callback'=>'__return_true'));
		register_rest_route('appbridge/v1','/auth/logout',array('methods'=>'POST','callback'=>array($this,'logout'),'permission_callback'=>static fn()=>is_user_logged_in()));
		register_rest_route('appbridge/v1','/me',array(
			array('methods'=>'GET','callback'=>array($this,'me'),'permission_callback'=>static fn()=>is_user_logged_in()),
			array('methods'=>'PATCH','callback'=>array($this,'update_me'),'permission_callback'=>static fn()=>is_user_logged_in()),
		));
		register_rest_route('appbridge/v1','/devices',array(
			array('methods'=>'GET','callback'=>array($this,'devices'),'permission_callback'=>static fn()=>is_user_logged_in()),
			array('methods'=>'POST','callback'=>array($this,'device_upsert'),'permission_callback'=>static fn()=>is_user_logged_in()),
		));
		register_rest_route('appbridge/v1','/notifications',array('methods'=>'GET','callback'=>array($this,'notifications'),'permission_callback'=>static fn()=>is_user_logged_in()));
		register_rest_route('appbridge/v1','/notifications/(?P<id>\d+)/read',array('methods'=>'POST','callback'=>array($this,'notification_read'),'permission_callback'=>static fn()=>is_user_logged_in()));
		register_rest_route('appbridge/v1','/admin/health',array('methods'=>'GET','callback'=>array($this,'health'),'permission_callback'=>static fn()=>current_user_can('manage_options')));
		$this->integrations->register_routes();
	}
	private function limited(string $bucket,int $limit=0){return RateLimiter::check($bucket,$limit?:null);}
	public function bootstrap(){ if(is_wp_error($l=$this->limited('bootstrap',180)))return $l; $s=Settings::all(); return Response::success(array('api_version'=>'1.0','plugin_version'=>APPBRIDGE_VERSION,'site'=>array('name'=>get_bloginfo('name'),'description'=>get_bloginfo('description'),'url'=>home_url('/'),'timezone'=>wp_timezone_string(),'locale'=>get_locale(),'rtl'=>is_rtl()),'user'=>is_user_logged_in()?$this->user_payload(wp_get_current_user()):null,'integrations'=>$this->integrations->status(),'app'=>array('maintenance'=>(bool)($s['maintenance_mode']??false),'minimum_version'=>$s['min_app_version']??'','latest_version'=>$s['latest_app_version']??'','force_update'=>(bool)($s['force_update']??false)))); }
	public function capabilities(){return Response::success(array('integrations'=>$this->integrations->status(),'authentication'=>array('bearer_tokens'=>true,'wordpress_application_passwords'=>true),'features'=>array('notifications'=>true,'devices'=>true,'webhooks'=>true,'audit'=>true,'cors'=>true,'rate_limiting'=>true)));}

	public function register_user(WP_REST_Request $r){
		if(!Settings::get('enable_registration',false))return new \WP_Error('appbridge_registration_disabled',__('Registration is disabled.','appbridge-api-gateway'),array('status'=>403));
		if(is_wp_error($l=$this->limited('register',3)))return $l;$p=$r->get_json_params();$username=sanitize_user((string)($p['username']??''),true);$email=sanitize_email((string)($p['email']??''));$password=(string)($p['password']??'');
		if(strlen($username)<3||!is_email($email)||strlen($password)<10)return new \WP_Error('appbridge_registration_invalid',__('A valid username, email and password of at least 10 characters are required.','appbridge-api-gateway'),array('status'=>400));
		if(username_exists($username)||email_exists($email))return new \WP_Error('appbridge_registration_exists',__('That username or email is already registered.','appbridge-api-gateway'),array('status'=>409));
		$id=wp_create_user($username,$password,$email);if(is_wp_error($id))return $id;AuditService::log('user_registered','user:'.$id,array(),(int)$id);$user=get_user_by('id',$id);$tokens=(new TokenService())->issue_pair((int)$id,array('content:read','profile:read','profile:write','orders:read:self','forms:submit'),sanitize_text_field($p['client_id']??'default'));return is_wp_error($tokens)?$tokens:Response::success(array('tokens'=>$tokens,'user'=>$this->user_payload($user)),201);
	}
	public function login(WP_REST_Request $r){if(is_wp_error($l=$this->limited('login',5)))return $l; $p=$r->get_json_params(); $login=sanitize_text_field($p['login']??''); $password=(string)($p['password']??''); if(''===$login||''===$password)return new \WP_Error('appbridge_missing_credentials',__('Login and password are required.','appbridge-api-gateway'),array('status'=>400)); $user=wp_authenticate($login,$password); if(is_wp_error($user)){AuditService::log('login_failed',null,array('login'=>$login));return new \WP_Error('appbridge_invalid_credentials',__('Invalid credentials.','appbridge-api-gateway'),array('status'=>401));} $scopes=array('content:read','profile:read','profile:write','orders:read:self','forms:submit'); $tokens=(new TokenService())->issue_pair((int)$user->ID,$scopes,sanitize_text_field($p['client_id']??'default')); return is_wp_error($tokens)?$tokens:Response::success(array('tokens'=>$tokens,'user'=>$this->user_payload($user)),200);}
	public function refresh(WP_REST_Request $r){if(is_wp_error($l=$this->limited('refresh',20)))return $l; $p=$r->get_json_params(); $token=(string)($p['refresh_token']??''); if(''===$token)return new \WP_Error('appbridge_missing_refresh',__('Refresh token is required.','appbridge-api-gateway'),array('status'=>400)); $result=(new TokenService())->rotate_refresh($token); return is_wp_error($result)?$result:Response::success($result);}
	public function logout(){ $ctx=$GLOBALS['appbridge_token_context']??null; if($ctx){(new TokenService())->revoke_family((string)$ctx['family_id']);} AuditService::log('logout'); return Response::success(array('logged_out'=>true)); }
	public function me(){return Response::success($this->user_payload(wp_get_current_user()));}
	public function update_me(WP_REST_Request $r){$p=$r->get_json_params();$id=get_current_user_id();$data=array('ID'=>$id);foreach(array('first_name','last_name','display_name','description') as $k){if(array_key_exists($k,$p))$data[$k]=sanitize_text_field((string)$p[$k]);}$res=wp_update_user($data);if(is_wp_error($res))return $res;AuditService::log('profile_updated','user:'.$id);return $this->me();}
	public function devices(){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT id,device_uuid,platform,app_version,device_name,locale,last_seen_at,created_at FROM {$wpdb->prefix}appbridge_devices WHERE user_id=%d ORDER BY last_seen_at DESC",get_current_user_id()),ARRAY_A);return Response::success($rows);}
	public function device_upsert(WP_REST_Request $r){$p=$r->get_json_params();if(empty($p['device_uuid'])||empty($p['platform']))return new \WP_Error('appbridge_device_invalid',__('device_uuid and platform are required.','appbridge-api-gateway'),array('status'=>400));return Response::success((new DeviceService())->upsert(get_current_user_id(),$p),201);}
	public function notifications(WP_REST_Request $r){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT id,type,title,body,data_json,read_at,created_at FROM {$wpdb->prefix}appbridge_notifications WHERE user_id=%d ORDER BY created_at DESC LIMIT 100",get_current_user_id()),ARRAY_A);foreach($rows as &$row){$row['data']=json_decode((string)$row['data_json'],true);unset($row['data_json']);}return Response::success($rows);}
	public function notification_read(WP_REST_Request $r){global $wpdb;$wpdb->update($wpdb->prefix.'appbridge_notifications',array('read_at'=>current_time('mysql',true)),array('id'=>(int)$r['id'],'user_id'=>get_current_user_id()),array('%s'),array('%d','%d'));return Response::success(array('read'=>true));}
	public function health(){global $wpdb;$checks=array('wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'https'=>is_ssl(),'permalinks'=>(bool)get_option('permalink_structure'),'database'=>$wpdb->db_version(),'integrations'=>array_map(static fn($i)=>$i->health(),$this->integrations->all()));return Response::success($checks);}
	private function user_payload(\WP_User $u):array{return array('id'=>(int)$u->ID,'display_name'=>$u->display_name,'first_name'=>$u->first_name,'last_name'=>$u->last_name,'avatar_url'=>get_avatar_url($u->ID,array('size'=>192)),'locale'=>get_user_locale($u),'roles'=>array_values($u->roles),'capabilities'=>array_keys(array_filter($u->allcaps)));}
}
