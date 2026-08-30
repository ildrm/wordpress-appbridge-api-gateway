<?php
namespace AppBridge\ApiGateway\Admin;

use AppBridge\ApiGateway\Integrations\Registry;
use AppBridge\ApiGateway\Services\WebhookService;
use AppBridge\ApiGateway\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public function __construct(private Registry $integrations) {}
	public function register(): void {
		add_action('admin_menu',array($this,'menu'));
		add_action('admin_init',array($this,'settings'));
		add_action('admin_enqueue_scripts',array($this,'assets'));
		add_action('admin_post_appbridge_add_webhook',array($this,'add_webhook'));
		add_action('admin_post_appbridge_delete_webhook',array($this,'delete_webhook'));
	}
	public function menu(): void { add_menu_page(__('AppBridge','appbridge-api-gateway'),__('AppBridge','appbridge-api-gateway'),'manage_options','appbridge',array($this,'page'),'dashicons-rest-api',58); }
	public function assets(string $hook): void { if('toplevel_page_appbridge'!==$hook)return; wp_enqueue_style('appbridge-admin',APPBRIDGE_URL.'assets/css/admin.css',array(),APPBRIDGE_VERSION); wp_enqueue_script('appbridge-admin',APPBRIDGE_URL.'assets/js/admin.js',array(),APPBRIDGE_VERSION,true); }
	public function settings(): void {
		register_setting('appbridge','appbridge_settings',array('type'=>'array','sanitize_callback'=>array($this,'sanitize_settings'),'default'=>array()));
	}
	public function sanitize_settings($input): array {
		$input=is_array($input)?$input:array(); $orig=Settings::all();
		$orig['enabled']=!empty($input['enabled']);
		$orig['enable_registration']=!empty($input['enable_registration']);
		$orig['enable_comments']=!empty($input['enable_comments']);
		$orig['maintenance_mode']=!empty($input['maintenance_mode']);
		$orig['force_update']=!empty($input['force_update']);
		$orig['rate_limit_per_min']=max(10,min(10000,(int)($input['rate_limit_per_min']??120)));
		$orig['access_token_ttl']=max(300,min(DAY_IN_SECONDS,(int)($input['access_token_ttl']??3600)));
		$orig['refresh_token_ttl']=max(HOUR_IN_SECONDS,min(180*DAY_IN_SECONDS,(int)($input['refresh_token_ttl']??30*DAY_IN_SECONDS)));
		$orig['min_app_version']=sanitize_text_field($input['min_app_version']??'');$orig['latest_app_version']=sanitize_text_field($input['latest_app_version']??'');
		$orig['cors_origins']=array_values(array_filter(array_map(static function($v){$v=esc_url_raw(trim($v));return $v&&preg_match('#^https?://#i',$v)?untrailingslashit($v):'';},preg_split('/\R/',(string)($input['cors_origins']??'')))));
		return $orig;
	}
	public function page(): void {
		if(!current_user_can('manage_options'))return; $s=Settings::all(); global $wpdb;
		$webhooks=$wpdb->get_results("SELECT id,name,url,events,enabled,created_at FROM {$wpdb->prefix}appbridge_webhooks ORDER BY id DESC",ARRAY_A); // phpcs:ignore
		?>
		<div class="wrap appbridge-wrap">
			<div class="appbridge-hero"><div><span class="appbridge-kicker">APPLICATION API GATEWAY</span><h1><?php esc_html_e('AppBridge','appbridge-api-gateway');?></h1><p><?php esc_html_e('Secure APIs for mobile, web, headless, commerce and form clients.','appbridge-api-gateway');?></p></div><div class="appbridge-version">v<?php echo esc_html(APPBRIDGE_VERSION);?></div></div>
			<nav class="appbridge-tabs" aria-label="<?php esc_attr_e('AppBridge sections','appbridge-api-gateway');?>"><button class="is-active" data-tab="overview"><?php esc_html_e('Overview','appbridge-api-gateway');?></button><button data-tab="settings"><?php esc_html_e('API & Security','appbridge-api-gateway');?></button><button data-tab="integrations"><?php esc_html_e('Integrations','appbridge-api-gateway');?></button><button data-tab="webhooks"><?php esc_html_e('Webhooks','appbridge-api-gateway');?></button><button data-tab="developer"><?php esc_html_e('Developer','appbridge-api-gateway');?></button></nav>
			<section class="appbridge-panel is-active" data-panel="overview"><div class="appbridge-grid"><?php $this->metric(__('API namespace','appbridge-api-gateway'),'/wp-json/appbridge/v1');$this->metric(__('WordPress','appbridge-api-gateway'),get_bloginfo('version'));$this->metric(__('HTTPS','appbridge-api-gateway'),is_ssl()?__('Enabled','appbridge-api-gateway'):__('Not detected','appbridge-api-gateway'));$this->metric(__('Integrations','appbridge-api-gateway'),(string)count(array_filter($this->integrations->status(),fn($i)=>$i['available'])));?></div><div class="appbridge-card"><h2><?php esc_html_e('Production checklist','appbridge-api-gateway');?></h2><ul class="appbridge-checks"><li><?php echo is_ssl()?'✓':'!';?> <?php esc_html_e('Use HTTPS in production.','appbridge-api-gateway');?></li><li>✓ <?php esc_html_e('Bearer tokens are hashed at rest.','appbridge-api-gateway');?></li><li>✓ <?php esc_html_e('WooCommerce HPOS compatibility is declared.','appbridge-api-gateway');?></li><li>✓ <?php esc_html_e('REST routes use explicit permission callbacks.','appbridge-api-gateway');?></li></ul></div></section>
			<section class="appbridge-panel" data-panel="settings"><form method="post" action="options.php"><?php settings_fields('appbridge');?><div class="appbridge-card"><h2><?php esc_html_e('API & Security','appbridge-api-gateway');?></h2><?php $this->checkbox('enabled',__('API enabled','appbridge-api-gateway'),$s);$this->number('rate_limit_per_min',__('Default requests / minute','appbridge-api-gateway'),$s,10,10000);$this->number('access_token_ttl',__('Access token TTL (seconds)','appbridge-api-gateway'),$s,300,86400);$this->number('refresh_token_ttl',__('Refresh token TTL (seconds)','appbridge-api-gateway'),$s,3600,15552000);?><label class="appbridge-field"><span><?php esc_html_e('Allowed CORS origins','appbridge-api-gateway');?></span><textarea name="appbridge_settings[cors_origins]" rows="5" placeholder="https://app.example.com"><?php echo esc_textarea(implode("\n",(array)($s['cors_origins']??array())));?></textarea><small><?php esc_html_e('One exact origin per line. Wildcards are intentionally not supported for authenticated APIs.','appbridge-api-gateway');?></small></label></div><div class="appbridge-card"><h2><?php esc_html_e('Application lifecycle','appbridge-api-gateway');?></h2><?php $this->checkbox('maintenance_mode',__('Maintenance mode','appbridge-api-gateway'),$s);$this->text('min_app_version',__('Minimum app version','appbridge-api-gateway'),$s);$this->text('latest_app_version',__('Latest app version','appbridge-api-gateway'),$s);$this->checkbox('force_update',__('Force application update','appbridge-api-gateway'),$s);?></div><?php submit_button(__('Save settings','appbridge-api-gateway'));?></form></section>
			<section class="appbridge-panel" data-panel="integrations"><div class="appbridge-grid"><?php foreach($this->integrations->status() as $i){?><div class="appbridge-card"><div class="appbridge-status <?php echo $i['available']?'ok':'off';?>"><?php echo $i['available']?'ACTIVE':'NOT DETECTED';?></div><h2><?php echo esc_html($i['name']);?></h2><p><?php echo esc_html(implode(' · ',$i['capabilities']));?></p></div><?php }?></div></section>
			<section class="appbridge-panel" data-panel="webhooks"><div class="appbridge-card"><h2><?php esc_html_e('Add webhook','appbridge-api-gateway');?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('appbridge_add_webhook');?><input type="hidden" name="action" value="appbridge_add_webhook"><div class="appbridge-row"><label class="appbridge-field"><span><?php esc_html_e('Name','appbridge-api-gateway');?></span><input required name="name"></label><label class="appbridge-field"><span><?php esc_html_e('HTTPS URL','appbridge-api-gateway');?></span><input required type="url" name="url" placeholder="https://example.com/hooks"></label></div><div class="appbridge-row"><label class="appbridge-field"><span><?php esc_html_e('Events','appbridge-api-gateway');?></span><input required name="events" value="post.updated,order.updated"></label><label class="appbridge-field"><span><?php esc_html_e('Signing secret','appbridge-api-gateway');?></span><input required type="password" name="secret" minlength="16"></label></div><?php submit_button(__('Create webhook','appbridge-api-gateway'),'secondary');?></form></div><div class="appbridge-card"><h2><?php esc_html_e('Configured webhooks','appbridge-api-gateway');?></h2><div class="appbridge-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Name','appbridge-api-gateway');?></th><th><?php esc_html_e('URL','appbridge-api-gateway');?></th><th><?php esc_html_e('Events','appbridge-api-gateway');?></th><th></th></tr></thead><tbody><?php if(!$webhooks):?><tr><td colspan="4"><?php esc_html_e('No webhooks configured.','appbridge-api-gateway');?></td></tr><?php endif;foreach($webhooks as $w):?><tr><td><?php echo esc_html($w['name']);?></td><td><code><?php echo esc_html($w['url']);?></code></td><td><?php echo esc_html($w['events']);?></td><td><a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=appbridge_delete_webhook&id='.(int)$w['id']),'appbridge_delete_webhook_'.(int)$w['id']));?>"><?php esc_html_e('Delete','appbridge-api-gateway');?></a></td></tr><?php endforeach;?></tbody></table></div></div></section>
			<section class="appbridge-panel" data-panel="developer"><div class="appbridge-card"><h2><?php esc_html_e('Core endpoints','appbridge-api-gateway');?></h2><pre><code>GET  /wp-json/appbridge/v1/bootstrap
GET  /wp-json/appbridge/v1/capabilities
POST /wp-json/appbridge/v1/auth/login
POST /wp-json/appbridge/v1/auth/refresh
GET  /wp-json/appbridge/v1/me
GET  /wp-json/appbridge/v1/content
GET  /wp-json/appbridge/v1/commerce/products
GET  /wp-json/appbridge/v1/commerce/orders
POST /wp-json/appbridge/v1/forms/gravity/{id}/submit
GET  /wp-json/appbridge/v1/admin/health</code></pre><p><?php esc_html_e('Use Authorization: Bearer <token> for application sessions. WordPress Application Passwords also remain available for trusted server-to-server use through WordPress core.','appbridge-api-gateway');?></p></div></section>
		</div><?php
	}
	private function metric(string $label,string $value):void{?><div class="appbridge-metric"><span><?php echo esc_html($label);?></span><strong><?php echo esc_html($value);?></strong></div><?php }
	private function checkbox(string $key,string $label,array $s):void{?><label class="appbridge-toggle"><input type="checkbox" name="appbridge_settings[<?php echo esc_attr($key);?>]" value="1" <?php checked(!empty($s[$key]));?>><span><?php echo esc_html($label);?></span></label><?php }
	private function number(string $key,string $label,array $s,int $min,int $max):void{?><label class="appbridge-field"><span><?php echo esc_html($label);?></span><input type="number" min="<?php echo esc_attr((string)$min);?>" max="<?php echo esc_attr((string)$max);?>" name="appbridge_settings[<?php echo esc_attr($key);?>]" value="<?php echo esc_attr((string)($s[$key]??''));?>"></label><?php }
	private function text(string $key,string $label,array $s):void{?><label class="appbridge-field"><span><?php echo esc_html($label);?></span><input type="text" name="appbridge_settings[<?php echo esc_attr($key);?>]" value="<?php echo esc_attr((string)($s[$key]??''));?>"></label><?php }
	public function add_webhook():void{if(!current_user_can('manage_options'))wp_die( esc_html__( 'Forbidden', 'appbridge-api-gateway' ), '', array( 'response' => 403 ) );check_admin_referer('appbridge_add_webhook');$url=esc_url_raw(wp_unslash($_POST['url']??''));if(!wp_http_validate_url($url)||'https'!==wp_parse_url($url,PHP_URL_SCHEME))wp_die( esc_html__( 'Webhook URL must be a valid HTTPS URL.', 'appbridge-api-gateway' ), '', array( 'response' => 400 ) );$host=wp_parse_url($url,PHP_URL_HOST);$ip=$host?gethostbyname($host):'';if($ip&&filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false)wp_die( esc_html__( 'Private or reserved webhook destinations are not allowed.', 'appbridge-api-gateway' ), '', array( 'response' => 400 ) );$secret=(string)wp_unslash($_POST['secret']??'');if(strlen($secret)<16)wp_die( esc_html__( 'Webhook secret is too short.', 'appbridge-api-gateway' ), '', array( 'response' => 400 ) );global $wpdb;$wpdb->insert($wpdb->prefix.'appbridge_webhooks',array('name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'url'=>$url,'secret_hash'=>hash('sha256',$secret),'secret_cipher'=>WebhookService::encrypt($secret),'events'=>sanitize_text_field(wp_unslash($_POST['events']??'')),'enabled'=>1,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql',true)));wp_safe_redirect(admin_url('admin.php?page=appbridge'));exit;}
	public function delete_webhook():void{$id=absint($_GET['id']??0);if(!current_user_can('manage_options'))wp_die( esc_html__( 'Forbidden', 'appbridge-api-gateway' ), '', array( 'response' => 403 ) );check_admin_referer('appbridge_delete_webhook_'.$id);global $wpdb;$wpdb->delete($wpdb->prefix.'appbridge_webhooks',array('id'=>$id),array('%d'));wp_safe_redirect(admin_url('admin.php?page=appbridge'));exit;}
}
