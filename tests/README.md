# Test strategy

The package is syntax-linted during release. Full runtime tests should execute in a WordPress test environment and cover:

- authentication success/failure, refresh rotation/replay and revocation
- BOLA/IDOR across users, orders, notifications and devices
- route permission callbacks
- rate-limit behavior
- exact-origin CORS behavior
- WooCommerce product/order behavior with HPOS enabled
- Gravity Forms validation, anti-spam, feeds, notification and confirmation behavior
- webhook HMAC signatures, network failures and SSRF edge cases
- schema upgrades and uninstall retention behavior
- multisite activation where supported
- responsive/admin accessibility regression tests
