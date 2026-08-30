# Security Policy

Security reports should be sent privately to **ildrm@hotmail.com**. Do not include secrets, production credentials, personal data, or exploit traffic from systems you do not own or have permission to test.

## Design rules

- No arbitrary option/meta exposure.
- No arbitrary PHP/hook execution endpoints.
- All routes define permission callbacks.
- Protected resources must enforce both authentication and object-level authorization.
- Tokens are opaque random secrets; only hashes are stored.
- Secrets are redacted from audit logs.
- HTTPS is required for configured webhook destinations.
- Outbound webhooks use WordPress safe HTTP functions.
- WooCommerce data must use WooCommerce APIs/CRUD, not order-table assumptions.
- Gravity Forms submissions must use Gravity Forms' submission pipeline.
