# Implementation Roles and Review Lenses

The project is designed/reviewed through the following roles. A real team may combine roles, but each review lens is required.

1. **Product Architect / Product Manager** — scope, API consumers, compatibility roadmap, version policy.
2. **WordPress Plugin Architect** — lifecycle, hooks, plugin structure, standards, multisite and backward compatibility.
3. **Senior WordPress Backend Engineer** — REST controllers, capabilities, data APIs, sanitization/escaping.
4. **API Architect** — resource design, schemas, versioning, pagination, errors, idempotency and discoverability.
5. **Identity & Access Management Engineer** — session lifecycle, token rotation, scopes, revocation and device sessions.
6. **Application Security Engineer** — BOLA/IDOR, auth abuse, SSRF, rate limiting, secret management and threat modeling.
7. **WooCommerce Engineer** — Store API/CRUD usage, HPOS, orders, products and checkout compatibility.
8. **ACF Integration Engineer** — field exposure rules, nested/relationship fields and REST-safe schema behavior.
9. **Gravity Forms Engineer** — form schema, validation, anti-spam and native submission lifecycle.
10. **Integration Platform Engineer** — adapter contracts, optional dependencies and plugin coexistence.
11. **Database Engineer** — schema design, indexes, cleanup, concurrency and retention.
12. **Performance Engineer** — query counts, pagination limits, cache semantics, latency and payload size.
13. **Frontend Engineer** — accessible, responsive WordPress admin implementation and safe client-side behavior.
14. **UI/UX Product Designer** — information architecture, states, hierarchy, responsive behavior and error prevention.
15. **Accessibility Specialist** — keyboard navigation, semantic controls, contrast and WordPress admin accessibility.
16. **Mobile/API Consumer Engineer** — Android/iOS/PWA startup, retries, unreliable-network behavior and token refresh.
17. **DevOps/SRE Engineer** — health checks, diagnostics, logging, operational defaults and deployment concerns.
18. **QA/Test Automation Engineer** — unit/integration/security/compatibility regression strategy.
19. **Privacy Engineer** — PII minimization, retention, deletion and audit boundaries.
20. **Technical Writer / Developer Experience Engineer** — endpoint documentation, setup, security and extension examples.
21. **Open Source/License Reviewer** — WordPress-compatible licensing, notices and distributability.
22. **Release Engineer** — packaging, linting, file hygiene, semantic versions and reproducible releases.

## Required review sequence

1. Architecture and dependency-boundary pass.
2. Authentication/authorization/threat-model pass.
3. WordPress coding/escaping/nonce/capability pass.
4. WooCommerce/ACF/Gravity Forms integration pass.
5. UI/UX/accessibility/responsive pass.
6. Performance/database/query pass.
7. Packaging/license/documentation pass.
8. Final syntax/static scan of both source tree and packaged archive.
