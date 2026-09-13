# Architecture

## Why a modular monolith

The source project proves that a framework-free PHP application can run reliably on ordinary Apache/DirectAdmin hosting. Copying all of its product, profile, billing and legacy migration code would make a poor generic template, though. This repository keeps the reusable infrastructure and turns business areas into optional modules.

```text
HTTP / CLI
  -> bootstrap (environment, errors, session, database)
  -> application (guards, router, modules)
  -> domain service
  -> repository / gateway
  -> MariaDB or external provider
```

## Boundaries

- `Core`: application lifecycle and module registry.
- `Http`: request, response, router and middleware contract.
- `Security`: request guard, CSRF and database rate limiting.
- `Auth`: session authentication and RBAC.
- `Database`: PDO connection and ordered migrations.
- `Queue` / `Notification`: durable asynchronous work.
- `Media`: validated local uploads.
- `Payment`: provider-neutral contract and adapters.
- `Modules`: project/domain code. A module owns its schema and routes.

Code outside a module may depend on the foundation. One optional business module should not reach into another module's tables directly; expose a small service contract instead. This makes later extraction to a separate service possible without rewriting callers.

## What was generalized from Nlink

- `NlinkV2\\...` became the neutral `App\\...` namespace.
- hard-coded `v2_` table names became a validated `DB_TABLE_PREFIX`.
- Nlink routes, UI, profile builder and V1 migration compatibility were removed.
- the request guard, strict PDO defaults, safe error behavior, CSRF strategy, rate limiting, upload checks and payment adapter pattern were retained and generalized.
- one large front controller was replaced with small modules and middleware.
- migrations are tracked and selected domain packs can be enabled independently.

## Security defaults

- Production never returns exception messages to clients.
- PHP sessions use strict mode, HttpOnly cookies and `SameSite=Lax`; HTTPS enables Secure cookies and HSTS.
- Browser state changes require a CSRF token.
- Passwords use `PASSWORD_DEFAULT`; login work is balanced for unknown accounts.
- User status and `auth_version` are checked on every session resolution.
- Database identifiers are allowlisted; values use prepared statements.
- Uploads use MIME inspection, random names and executable-file blocking.
- `APP_KEY` is required outside development and signs rate-limit subjects.

## Scaling path

Start with one deployable unit and one database. Extract a module only when it has a concrete reason—independent scaling, isolation, separate ownership or deployment cadence. Before extraction, move cross-module calls behind interfaces and use the durable jobs/outbox boundary for asynchronous work.
