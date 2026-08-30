<?php
namespace AppBridge\ApiGateway\Security;

use AppBridge\ApiGateway\Support\Request;
use AppBridge\ApiGateway\Support\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {
	public static function check( string $bucket = 'default', ?int $limit = null ): true|WP_Error {
		$limit = max( 1, $limit ?: (int) Settings::get( 'rate_limit_per_min', 120 ) );
		$key_material = get_current_user_id() ? 'u:' . get_current_user_id() : 'ip:' . Request::ip();
		$key = 'appbridge_rl_' . md5( $bucket . '|' . $key_material . '|' . gmdate( 'YmdHi' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'appbridge_rate_limited', __( 'Too many requests. Please retry shortly.', 'appbridge-api-gateway' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS + 5 );
		return true;
	}
}
