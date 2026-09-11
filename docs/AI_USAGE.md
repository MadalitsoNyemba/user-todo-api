# AI Usage

This build was AI-assisted throughout. This document covers the tools, the workflow, and — in the
interest of the same transparency the brief asks the API itself to have — the specific issues an AI
review pass caught and fixed along the way.

## Tools

- **Claude Code (Claude Sonnet 5)** — planning, spec-to-issue decomposition, README authoring, and the
  `/code-review` pass run after each issue's PR.
- **Cursor** — in-editor autocomplete while implementing each issue.
- **Laravel Boost (MCP)** — framework-aware tools (`tinker`, `search-docs`, `database-query`) available to
  Claude Code during implementation and review.

## 1. Bootstrapping

The project was scaffolded with:

```bash
docker run --rm -e COMPOSER_NO_SECURITY_BLOCKING=1 \
  -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel:^10.0 user-todo-api --no-interaction
```

`COMPOSER_NO_SECURITY_BLOCKING=1` was necessary because Laravel 10 has known advisories with no 10.x
patch — Composer's install-time security check would otherwise refuse the install outright. The permanent
record of *why* this is acceptable lives in `composer.json`'s `config.policy.advisories.ignore-id` block,
which documents each ignored advisory and its in-app mitigation (e.g. the CRLF-injection email validation
CVE is mitigated by validating with `email:rfc,strict` and rejecting control characters in every Form
Request that accepts an email).

## 2. From spec to issues

The brief was broken into GitHub issues with AI assistance, then implemented issue-by-issue. One task was
added beyond the brief's scope: a GitHub Actions workflow that runs the full test suite against the
dockerized stack on every PR to `main`/`develop` (`ci/github-actions`, #46) — sequenced after the Docker
environment itself existed (`feat/docker-environment`, #40), since the workflow needs a real stack to test
against.

## 3. Implementation workflow

Each issue followed the same loop: feature branch → Cursor-assisted implementation → PR into `develop` →
a Claude Code `/code-review` pass → fix any findings → merge. Representative PRs: docker environment
(#40), API conventions (#42), users + JWT guard (#43), auth endpoints (#45), CI (#46), OpenAPI docs (#47),
token lifecycle (#48), profile endpoints (#49), todos persistence (#50), todo CRUD (#51), job status
(#52), bulk-complete job (#53), rate limiting (#54), RBAC (#55).

## 4. Notable issues an AI review caught

- **Sanctum CSRF vs. JWT auth** — `EnsureFrontendRequestsAreStateful` was present in the `api` middleware
  group, which would enforce Sanctum's CSRF check against any request matching its stateful-domain list.
  Since this API authenticates with bearer JWTs, not Sanctum SPA sessions, a legitimate client could get a
  419 with a perfectly valid token. Removed.
- **Unconditional seeder with hardcoded credentials** — `DatabaseSeeder` seeded two accounts with the
  password `password` regardless of environment. Guarded with an early return in `production`.
- **Double JWT verification** — `AuthenticateWithJwt` authenticated via the `JWTAuth` facade but never
  populated the `api` guard, so every later `auth()->user()` call re-parsed and re-verified the token from
  scratch. Fixed to push the resolved user into the guard once, in the middleware.
- **Reserved alias collision** — the middleware was registered under the alias `jwt.auth`, which
  `tymon/jwt-auth` also registers for its own middleware; the package's registration silently won in the
  real request lifecycle, meaning the app's custom `AuthenticateWithJwt` never actually ran in production
  despite passing tests (the test harness resolved the Kernel in a different order, masking it). Renamed
  to `auth.jwt`, a name the package doesn't claim.
- **Dead CORS entry** — `config/cors.php` still whitelisted `sanctum/csrf-cookie` after Sanctum itself was
  removed from the stack.

## 5. Decisions made independent of AI suggestion

The following were deliberate architectural calls: hand-rolled RBAC (a `role`
column + enum) instead of `spatie/laravel-permission`; returning 404 rather than 403 for another user's
todo, to avoid confirming a record's existence; a `token_version` claim that invalidates all outstanding
JWTs on password or email change; and a dedicated `job_statuses` table instead of Laravel's `Bus::batch()`,
since batches carry no result payload and aren't scoped to a user.
