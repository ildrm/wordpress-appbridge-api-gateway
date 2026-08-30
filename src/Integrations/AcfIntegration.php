<?php
namespace AppBridge\ApiGateway\Integrations;

defined( 'ABSPATH' ) || exit;
final class AcfIntegration implements IntegrationInterface {
	public function key(): string { return 'acf'; }
	public function name(): string { return 'Advanced Custom Fields'; }
	public function available(): bool { return function_exists( 'acf_get_field_groups' ); }
	public function capabilities(): array { return array( 'fields', 'field-groups', 'rest-enabled-fields' ); }
	public function register_routes(): void {}
	public function health(): array { return array( 'ok' => $this->available(), 'version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : null ); }
}
