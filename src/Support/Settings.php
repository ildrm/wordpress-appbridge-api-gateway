<?php
namespace AppBridge\ApiGateway\Support;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public static function all(): array {
		$value = get_option( 'appbridge_settings', array() );
		return is_array( $value ) ? $value : array();
	}
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}
}
