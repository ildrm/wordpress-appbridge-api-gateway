<?php
namespace AppBridge\ApiGateway\Integrations;

defined( 'ABSPATH' ) || exit;

final class Registry {
	/** @var array<string,IntegrationInterface> */
	private array $items = array();

	public function register_defaults(): void {
		$this->add( new WordPressIntegration() );
		$this->add( new WooCommerceIntegration() );
		$this->add( new AcfIntegration() );
		$this->add( new GravityFormsIntegration() );
		$this->add( new DetectedIntegration( 'wpml', 'WPML', static fn() => defined( 'ICL_SITEPRESS_VERSION' ), array( 'languages', 'translations' ), 'ICL_SITEPRESS_VERSION' ) );
		$this->add( new DetectedIntegration( 'polylang', 'Polylang', static fn() => function_exists( 'pll_languages_list' ), array( 'languages', 'translations' ), 'POLYLANG_VERSION' ) );
		$this->add( new DetectedIntegration( 'yoast', 'Yoast SEO', static fn() => defined( 'WPSEO_VERSION' ), array( 'seo', 'schema' ), 'WPSEO_VERSION' ) );
		$this->add( new DetectedIntegration( 'rank_math', 'Rank Math', static fn() => defined( 'RANK_MATH_VERSION' ), array( 'seo', 'schema' ), 'RANK_MATH_VERSION' ) );
		$this->add( new DetectedIntegration( 'wpgraphql', 'WPGraphQL', static fn() => function_exists( 'graphql' ) || defined( 'WPGRAPHQL_VERSION' ), array( 'graphql' ), 'WPGRAPHQL_VERSION' ) );
		$this->add( new DetectedIntegration( 'learndash', 'LearnDash', static fn() => defined( 'LEARNDASH_VERSION' ), array( 'courses', 'progress' ), 'LEARNDASH_VERSION' ) );
		$this->add( new DetectedIntegration( 'tutor_lms', 'Tutor LMS', static fn() => function_exists( 'tutor' ), array( 'courses', 'progress' ) ) );
		$this->add( new DetectedIntegration( 'memberpress', 'MemberPress', static fn() => defined( 'MEPR_VERSION' ), array( 'memberships', 'entitlements' ), 'MEPR_VERSION' ) );
		$this->add( new DetectedIntegration( 'buddypress', 'BuddyPress', static fn() => function_exists( 'buddypress' ), array( 'profiles', 'activity', 'groups' ) ) );
		do_action( 'appbridge_register_integrations', $this );
	}
	public function add( IntegrationInterface $integration ): void {
		$this->items[ $integration->key() ] = $integration;
	}
	public function all(): array { return $this->items; }
	public function status(): array {
		$out = array();
		foreach ( $this->items as $key => $item ) {
			$out[ $key ] = array(
				'name' => $item->name(), 'available' => $item->available(),
				'capabilities' => $item->available() ? $item->capabilities() : array(),
			);
		}
		return $out;
	}
	public function register_routes(): void {
		foreach ( $this->items as $item ) {
			if ( $item->available() ) { $item->register_routes(); }
		}
	}
}
