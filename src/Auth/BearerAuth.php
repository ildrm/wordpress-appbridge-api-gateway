<?php
namespace AppBridge\ApiGateway\Auth;

defined( 'ABSPATH' ) || exit;

final class BearerAuth {
	public function register(): void {
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 20 );
	}

	public function authenticate( int|false $user_id ): int|false {
		if ( $user_id ) {
			return $user_id;
		}
		$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' );
		if ( ! is_string( $header ) || ! preg_match( '/^Bearer\s+([A-Za-z0-9_-]{40,})$/i', trim( $header ), $m ) ) {
			return $user_id;
		}
		$result = ( new TokenService() )->validate( $m[1] );
		if ( is_wp_error( $result ) ) {
			return $user_id;
		}
		$GLOBALS['appbridge_token_context'] = $result;
		return (int) $result['user_id'];
	}

	public static function has_scope( string $scope ): bool {
		$ctx = $GLOBALS['appbridge_token_context'] ?? null;
		if ( ! $ctx ) {
			return true; // WordPress-native authenticated sessions remain capability-controlled.
		}
		$scopes = (array) ( $ctx['scopes'] ?? array() );
		return in_array( '*', $scopes, true ) || in_array( $scope, $scopes, true );
	}
}
