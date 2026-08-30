<?php
namespace AppBridge\ApiGateway\Security;

defined( 'ABSPATH' ) || exit;

final class Headers {
	public function register(): void {
		add_filter( 'rest_post_dispatch', array( $this, 'apply' ), 20, 3 );
	}
	public function apply( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/appbridge/v1/' ) ) {
			$response->header( 'X-Content-Type-Options', 'nosniff' );
			$response->header( 'Referrer-Policy', 'no-referrer' );
			if ( is_user_logged_in() ) {
				$response->header( 'Cache-Control', 'private, no-store' );
			}
		}
		return $response;
	}
}
