<?php
namespace AppBridge\ApiGateway\Core;

use AppBridge\ApiGateway\Admin\Admin;
use AppBridge\ApiGateway\Api\Routes;
use AppBridge\ApiGateway\Api\Abilities;
use AppBridge\ApiGateway\Auth\BearerAuth;
use AppBridge\ApiGateway\Integrations\Registry;
use AppBridge\ApiGateway\Security\Cors;
use AppBridge\ApiGateway\Security\Headers;
use AppBridge\ApiGateway\Security\GatewayGuard;
use AppBridge\ApiGateway\Services\WebhookService;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?self $instance = null;
	private Registry $integrations;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		$this->integrations = new Registry();
		$this->integrations->register_defaults();

		( new BearerAuth() )->register();
		( new Cors() )->register();
		( new Headers() )->register();
		( new GatewayGuard() )->register();
		( new Routes( $this->integrations ) )->register();
		( new Abilities() )->register();
		( new WebhookService() )->register_events();

		if ( is_admin() ) {
			( new Admin( $this->integrations ) )->register();
		}

		do_action( 'appbridge_loaded', $this );
	}

	public function integrations(): Registry {
		return $this->integrations;
	}
}
