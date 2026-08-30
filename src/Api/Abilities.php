<?php
namespace AppBridge\ApiGateway\Api;

defined( 'ABSPATH' ) || exit;

final class Abilities {
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'abilities' ) );
	}

	public function category(): void {
		wp_register_ability_category(
			'appbridge',
			array(
				'label'       => __( 'AppBridge', 'appbridge-api-gateway' ),
				'description' => __( 'Application-safe abilities exposed by AppBridge.', 'appbridge-api-gateway' ),
			)
		);
	}

	public function abilities(): void {
		wp_register_ability(
			'appbridge/get-site-info',
			array(
				'label'               => __( 'Get site information', 'appbridge-api-gateway' ),
				'description'         => __( 'Returns public site information for an application client.', 'appbridge-api-gateway' ),
				'category'            => 'appbridge',
				'execute_callback'    => static fn() => array(
					'name'        => get_bloginfo( 'name' ),
					'description' => get_bloginfo( 'description' ),
					'url'         => home_url( '/' ),
					'locale'      => get_locale(),
				),
				'permission_callback' => '__return_true',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string', 'format' => 'uri' ),
						'locale'      => array( 'type' => 'string' ),
					),
				),
				'meta'                => array( 'annotations' => array( 'readonly' => true ), 'public' => true ),
			)
		);
		wp_register_ability(
			'appbridge/get-current-user',
			array(
				'label'               => __( 'Get current user', 'appbridge-api-gateway' ),
				'description'         => __( 'Returns the authenticated user identity and roles.', 'appbridge-api-gateway' ),
				'category'            => 'appbridge',
				'execute_callback'    => static function() {
					$u = wp_get_current_user();
					return array( 'id' => (int) $u->ID, 'display_name' => $u->display_name, 'roles' => array_values( $u->roles ) );
				},
				'permission_callback' => static fn() => is_user_logged_in(),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true ), 'public' => true ),
			)
		);
	}
}
