<?php
namespace AppBridge\ApiGateway\Integrations;

defined( 'ABSPATH' ) || exit;

interface IntegrationInterface {
	public function key(): string;
	public function name(): string;
	public function available(): bool;
	public function capabilities(): array;
	public function register_routes(): void;
	public function health(): array;
}
