<?php
namespace AppBridge\ApiGateway\Core;

use AppBridge\ApiGateway\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate(): void {
		Schema::install();
		$defaults = array(
			'enabled'             => true,
			'cors_origins'        => array(),
			'rate_limit_per_min'  => 120,
			'access_token_ttl'    => 3600,
			'refresh_token_ttl'   => 2592000,
			'audit_retention_days'=> 90,
			'enable_registration' => false,
			'enable_comments'     => true,
			'enable_push'         => false,
			'maintenance_mode'    => false,
			'min_app_version'     => '',
			'latest_app_version'  => '',
			'force_update'        => false,
		);
		if ( false === get_option( 'appbridge_settings', false ) ) {
			add_option( 'appbridge_settings', $defaults, '', false );
		}
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
