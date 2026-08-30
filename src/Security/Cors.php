<?php
namespace AppBridge\ApiGateway\Security;

use AppBridge\ApiGateway\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Cors {
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'configure' ), 5 );
	}
	public function configure(): void {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', array( $this, 'headers' ), 10, 4 );
	}
	public function headers( bool $served, $result, $request, $server ): bool {
		$route = method_exists( $request, 'get_route' ) ? $request->get_route() : '';
		if ( 0 !== strpos( $route, '/appbridge/v1/' ) ) {
			return $served;
		}
		$origin = get_http_origin();
		$allowed = array_values( array_filter( array_map( 'trim', (array) Settings::get( 'cors_origins', array() ) ) ) );
		if ( $origin && in_array( $origin, $allowed, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Vary: Origin', false );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Request-ID, X-AppBridge-Client, Idempotency-Key' );
			header( 'Access-Control-Expose-Headers: X-Request-ID, X-WP-Total, X-WP-TotalPages, ETag' );
		}
		return $served;
	}
}
