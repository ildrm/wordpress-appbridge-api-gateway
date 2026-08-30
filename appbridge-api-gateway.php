<?php
/**
 * Plugin Name:       AppBridge API Gateway
 * Plugin URI:        https://ildrm.com/
 * Description:       Secure, extensible application API gateway for WordPress, WooCommerce, ACF, Gravity Forms, mobile apps, web apps, and headless clients.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Shahin Ilderemi
 * Author URI:        https://ildrm.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       appbridge-api-gateway
 * Domain Path:       /languages
 * Update URI:        https://github.com/ildrm/appbridge-api-gateway
 *
 * @package AppBridge\ApiGateway
 */

defined( 'ABSPATH' ) || exit;

define( 'APPBRIDGE_VERSION', '1.0.0' );
define( 'APPBRIDGE_FILE', __FILE__ );
define( 'APPBRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'APPBRIDGE_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'AppBridge\\ApiGateway\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = APPBRIDGE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'AppBridge\\ApiGateway\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AppBridge\\ApiGateway\\Core\\Activator', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', APPBRIDGE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', APPBRIDGE_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'appbridge-api-gateway', false, dirname( plugin_basename( APPBRIDGE_FILE ) ) . '/languages' );
		AppBridge\ApiGateway\Core\Plugin::instance()->boot();
	}
);
