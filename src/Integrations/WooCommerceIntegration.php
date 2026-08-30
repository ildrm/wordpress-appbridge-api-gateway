<?php
namespace AppBridge\ApiGateway\Integrations;

use AppBridge\ApiGateway\Support\Response;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class WooCommerceIntegration implements IntegrationInterface {
	public function key(): string { return 'woocommerce'; }
	public function name(): string { return 'WooCommerce'; }
	public function available(): bool { return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ); }
	public function capabilities(): array { return array( 'catalog', 'orders', 'customer', 'store-api', 'hpos' ); }
	public function health(): array { return array( 'ok' => $this->available(), 'version' => defined( 'WC_VERSION' ) ? WC_VERSION : null ); }
	public function register_routes(): void {
		register_rest_route( 'appbridge/v1', '/commerce/products', array(
			'methods' => 'GET', 'callback' => array( $this, 'products' ), 'permission_callback' => '__return_true',
			'args' => array( 'page' => array( 'type'=>'integer','minimum'=>1,'default'=>1 ), 'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>100,'default'=>20), 'search'=>array('type'=>'string','sanitize_callback'=>'sanitize_text_field') ),
		) );
		register_rest_route( 'appbridge/v1', '/commerce/orders', array(
			'methods' => 'GET', 'callback' => array( $this, 'orders' ), 'permission_callback' => static fn() => is_user_logged_in(),
		) );
	}
	public function products( WP_REST_Request $r ) {
		$result = wc_get_products( array( 'status'=>'publish', 'limit'=>(int)$r['per_page'], 'page'=>(int)$r['page'], 'search'=>$r['search'] ?: '', 'paginate'=>true ) );
		$items = array_map( array( $this, 'normalize_product' ), $result->products );
		return Response::success( $items, 200, array( 'total'=>(int)$result->total, 'total_pages'=>(int)$result->max_num_pages ) );
	}
	public function orders() {
		$user_id = get_current_user_id();
		$args = array( 'customer_id'=>$user_id, 'limit'=>50, 'orderby'=>'date', 'order'=>'DESC' );
		if ( current_user_can( 'manage_woocommerce' ) ) { unset( $args['customer_id'] ); }
		$orders = wc_get_orders( $args );
		return Response::success( array_map( static function( $o ) { return array( 'id'=>$o->get_id(), 'number'=>$o->get_order_number(), 'status'=>$o->get_status(), 'currency'=>$o->get_currency(), 'total'=>$o->get_total(), 'date_created'=>$o->get_date_created()?->format(DATE_ATOM) ); }, $orders ) );
	}
	private function normalize_product( $p ): array {
		return array( 'id'=>$p->get_id(), 'type'=>$p->get_type(), 'name'=>$p->get_name(), 'slug'=>$p->get_slug(), 'sku'=>$p->get_sku(), 'price'=>$p->get_price(), 'regular_price'=>$p->get_regular_price(), 'sale_price'=>$p->get_sale_price(), 'stock_status'=>$p->get_stock_status(), 'stock_quantity'=>$p->get_stock_quantity(), 'permalink'=>$p->get_permalink(), 'image_id'=>$p->get_image_id(), 'image_url'=>$p->get_image_id()?wp_get_attachment_image_url($p->get_image_id(),'full'):null );
	}
}
