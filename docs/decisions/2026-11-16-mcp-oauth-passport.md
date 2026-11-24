# MCP product server with Passport OAuth

## Context

AI agents need a first-party way to create draft autofatture on every Fatturino
instance without sending documents to SDI. Manual Sanctum personal access tokens
would work for private tooling but force users to paste secrets into clients.
Product use needs a browser consent step that shows which client is asking.

## Decision

Ship a web MCP server in the main `fatturino` app with `laravel/mcp`, protected
by Laravel Passport OAuth 2.1 (PKCE, dynamic client registration via
`Mcp::oauthRoutes()`, scope `mcp:use`).

- Endpoint: `POST /mcp` with middleware `auth:api` and `throttle:mcp`.
- Consent view: branded `mcp.authorize` showing client name, redirect URI, and
  logged-in user before Approva/Rifiuta.
- Settings page `settings.mcp` lists non-revoked tokens and supports revoke.
- First tools: `search_contacts` and `create_self_invoice` (draft only via
  `SaveSelfInvoice`; no validate-xml / send-sdi tools).
- One DB per instance, Passport keys in `storage/oauth-*.key`, MCP at
  `{APP_URL}/mcp`. In-app cloud tenancy for MCP was removed; see
  [2026-11-24-remove-in-app-cloud-tenancy.md](2026-11-24-remove-in-app-cloud-tenancy.md).
- Sanctum remains for existing first-party `/api/v1` session/API use; MCP auth is
  Passport only.

## Consequences

- New production dependencies: `laravel/mcp`, `laravel/passport`.
- Operators must run Passport migrations and `passport:keys` on each instance.
- MCP clients discover OAuth metadata and complete browser login/consent; users
  can revoke access without rotating a pasted PAT.
- Expanding MCP beyond draft autofatture requires new tools and renewed review;
  SDI send stays out of MCP by design for this decision.
