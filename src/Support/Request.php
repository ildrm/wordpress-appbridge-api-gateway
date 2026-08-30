<?php
namespace AppBridge\ApiGateway\Support;

defined( 'ABSPATH' ) || exit;

final class Request {
	public static function id(): string {
		static $id = null;
		if ( null === $id ) {
			$incoming = isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : '';
			$id = preg_match( '/^[A-Za-z0-9._:-]{8,64}$/', $incoming ) ? $incoming : wp_generate_uuid4();
		}
		return $id;
	}

	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
