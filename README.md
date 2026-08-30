# AppBridge API Gateway

A secure, extensible application API gateway for WordPress 6.9+ (including WordPress 7.1), designed for Android, iOS, Flutter, React Native, web/PWA, headless frontends, trusted integrations, and automation clients.

**Author:** Shahin Ilderemi  
**Email:** ildrm@hotmail.com  
**Website:** https://ildrm.com  
**GitHub:** https://github.com/ildrm  
**LinkedIn:** https://www.linkedin.com/in/ildrm

## Highlights

- Versioned REST namespace: `/wp-json/appbridge/v1`
- WordPress Abilities API integration on WordPress 6.9+
- High-entropy opaque bearer access and refresh tokens
- Refresh-token rotation and family revocation
- WordPress Application Password compatibility for trusted integrations
- Rate limiting, strict CORS allow-listing, security headers, request IDs
- Redacted audit logging
- Device/session registration for mobile and web clients
- Notifications API
- HMAC-signed HTTPS webhooks with encrypted secrets and SSRF protections
- WordPress content API facade
- WooCommerce catalog and current-user/order access using WooCommerce CRUD APIs
- WooCommerce HPOS and Cart/Checkout Blocks compatibility declarations
- ACF detection and compatibility with ACF REST exposure
- Gravity Forms form/schema access and native submission-pipeline integration
- Detection/adapter surface for WPML, Polylang, Yoast SEO, Rank Math, WPGraphQL, LearnDash, Tutor LMS, MemberPress and BuddyPress
- Responsive WordPress admin UI
- Privacy-conscious uninstall behavior: data is retained unless `APPBRIDGE_REMOVE_DATA` is explicitly defined and true

## Core endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/bootstrap` | App/site bootstrap data and integration status |
| GET | `/capabilities` | Discover gateway features/integrations |
| POST | `/auth/login` | Issue access + refresh tokens |
| POST | `/auth/refresh` | Rotate refresh token family |
| POST | `/auth/logout` | Revoke current token family |
| GET/PATCH | `/me` | Current profile |
| GET/POST | `/devices` | Current user's registered devices |
| GET | `/notifications` | Current user's notifications |
| POST | `/notifications/{id}/read` | Mark owned notification read |
| GET | `/content` | Public WordPress content |
| GET | `/content/{id}` | Public WordPress content item |
| GET | `/commerce/products` | Public WooCommerce catalog facade |
| GET | `/commerce/orders` | Current user's orders; admins can see store orders |
| GET | `/forms/gravity/{id}` | Active Gravity Form schema |
| POST | `/forms/gravity/{id}/submit` | Submit through Gravity Forms processing |
| GET | `/admin/health` | Protected environment/integration health |

Prefix all endpoints with `/wp-json/appbridge/v1`.

## Authentication

For mobile/application sessions:

```http
Authorization: Bearer <access-token>
```

Access tokens are random opaque secrets and are stored only as SHA-256 hashes. Refresh tokens rotate as a family: a successful refresh revokes the old family and issues a new pair.

For trusted server-to-server clients, WordPress core Application Passwords continue to work with WordPress-native REST authentication.

## WooCommerce

AppBridge does not bypass WooCommerce. It uses WooCommerce CRUD APIs for protected data and is compatible with HPOS. For complete customer-facing cart and checkout flows, clients should also use WooCommerce's official Store API (`/wc/store/v1`) and its nonce/cart-token semantics. AppBridge intentionally does not weaken Store API nonce protections.

## Gravity Forms

AppBridge invokes Gravity Forms' submission API rather than inserting entries directly. That preserves Gravity Forms validation, anti-spam processing, add-on feeds, notifications, confirmations, and submission hooks.

## ACF

AppBridge does not expose arbitrary post meta. ACF remains responsible for REST-enabled field exposure. This avoids leaking private custom fields merely because they exist in the database.

## Security model

- Every custom REST route has an explicit `permission_callback`.
- Protected resources use WordPress authentication/capabilities and ownership restrictions.
- Tokens are random, hashed at rest, short-lived, revocable, and refreshable.
- Authentication attempts are throttled.
- CORS uses exact configured origins; authenticated wildcard CORS is not implemented.
- Private/no-store cache headers are used for authenticated AppBridge responses.
- Webhook secrets are encrypted with an installation-derived key and never displayed after creation.
- Webhook deliveries use `wp_safe_remote_post()`, HTTPS-only destinations, and private/reserved-address rejection.
- Audit context recursively redacts passwords, tokens, authorization headers and secrets.
- User meta, options, arbitrary post meta and payment credentials are never bulk-exposed.
- Pagination has hard maximums.

## Extensibility

Add integrations on `appbridge_register_integrations`:

```php
add_action( 'appbridge_register_integrations', function ( $registry ) {
    $registry->add( new My_Integration() );
} );
```

Implement `AppBridge\ApiGateway\Integrations\IntegrationInterface`.

The plugin also fires `appbridge_loaded` when its runtime is booted.

## WordPress Abilities API

When the Abilities API is available, AppBridge registers:

- `appbridge/get-site-info`
- `appbridge/get-current-user`

Both have declared schemas/permissions and are marked publicly discoverable in the Abilities API metadata. “Public” means discoverable/exposable; permission callbacks still apply.

## Admin UI

Go to **WP Admin → AppBridge** to view:

- gateway health/status
- API/security settings
- exact CORS origins
- access/refresh token TTLs
- app maintenance/version controls
- detected integrations
- signed webhook configuration
- developer endpoint reference

## Installation

1. Upload the ZIP in **Plugins → Add Plugin → Upload Plugin**.
2. Activate **AppBridge API Gateway**.
3. Open **AppBridge** in the admin menu.
4. Configure allowed CORS origins if a browser-based app will call authenticated endpoints.
5. Use HTTPS in production.

## Requirements

- WordPress 6.9+
- PHP 8.1+
- HTTPS strongly required in production

Optional integrations are activated only when the corresponding plugin is installed.

## License

GPL-2.0-or-later. See `LICENSE`.
