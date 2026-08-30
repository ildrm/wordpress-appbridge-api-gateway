<?php
namespace AppBridge\ApiGateway\Support;

use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Response {
	public static function success( mixed $data, int $status = 200, array $meta = array() ): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'meta'    => array_merge( array( 'request_id' => Request::id() ), $meta ),
			),
			$status
		);
		$response->header( 'X-Request-ID', Request::id() );
		return $response;
	}
}
