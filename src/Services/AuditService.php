<?php
namespace AppBridge\ApiGateway\Services;

use AppBridge\ApiGateway\Support\Request;

defined( 'ABSPATH' ) || exit;

final class AuditService {
	public static function log( string $action, ?string $resource = null, array $context = array(), ?int $user_id = null ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'appbridge_audit',
			array(
				'user_id'      => $user_id ?: ( get_current_user_id() ?: null ),
				'action'       => sanitize_key( $action ),
				'resource'     => $resource ? sanitize_text_field( $resource ) : null,
				'request_id'   => Request::id(),
				'ip'           => Request::ip(),
				'client_id'    => sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_APPBRIDGE_CLIENT'] ?? 'default' ) ),
				'context_json' => wp_json_encode( self::redact( $context ) ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private static function redact( array $context ): array {
		$blocked = array( 'password', 'token', 'access_token', 'refresh_token', 'authorization', 'secret' );
		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $blocked, true ) ) {
				$context[ $key ] = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = self::redact( $value );
			}
		}
		return $context;
	}
}
