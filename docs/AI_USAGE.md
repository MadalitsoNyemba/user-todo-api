# AI Usage

The brief asked for the models used and the prompts given. This document covers both, plus
the workflow they sat inside and the defects the review passes caught.

AI was used: for planning, for implementation, and for review. Every line was
read and every finding was judged before being applied. Where a review was wrong, it was
declined, and those cases are recorded below alongside the ones that were accepted.

## Models and tools

| Tool | Model | Used for |
|---|---|---|
| Claude Code | Claude Sonnet 5 | Spec decomposition into issues, the `/code-review` pass after each PR |
| Claude (chat) | Claude Opus 5 | Planning, architectural discussion, debugging sessions |
| Cursor | Built-in completion and review | In-editor autocomplete while implementing |
| Laravel Boost (MCP) | n/a | Framework-aware context for Claude Code: `tinker`, `search-docs`, `database-query` against the running application |

Laravel Boost matters more than it looks. A general model knows Laravel broadly but nothing
about this project, so it invents methods and writes conventions from whichever version
dominated its training data. Boost is an MCP server that exposes the actual application —
schema, routes, installed package versions, recent errors — so output is idiomatic to this
codebase rather than plausible in the abstract. Its config is committed at `.mcp.json` and
`CLAUDE.md`.

## Workflow

1. The brief was decomposed into GitHub issues through an adversarial planning conversation
   (prompt A below), which produced the issue list and the initial README.
2. Each issue became a feature branch, implemented with editor-level AI assistance.
3. Each branch got a `/code-review` pass before its PR was merged into `develop`.
4. Findings were triaged individually. Accepted ones were fixed with a regression test where
   the defect was behavioural; declined ones were answered with a reason.

Representative PRs: docker environment (#40), API conventions (#42), users and JWT guard
(#43), auth endpoints (#45), CI (#46), OpenAPI docs (#47), token lifecycle (#48), profile
endpoints (#49), todos persistence (#50), todo CRUD (#51), job status (#52), bulk-complete
job (#53), rate limiting (#54), RBAC (#55).

## Defects the review passes caught

These are the substantive ones. Each was verified before being fixed.

- **Reserved middleware alias collision.** The JWT middleware was registered as `jwt.auth`,
  which `tymon/jwt-auth` also registers. The package's registration won during the real
  request lifecycle, so the application's `AuthenticateWithJwt` never ran in production —
  while the test suite passed, because the harness resolves the Kernel in a different order.
  This was confirmed against the running stack, not inferred. Renamed to `auth.jwt`.
- **Sanctum CSRF against a JWT API.** `EnsureFrontendRequestsAreStateful` was in the `api`
  middleware group, matching requests by Origin against a stateful-domain list that includes
  localhost and routing them through CSRF verification. A browser client with a valid bearer
  token would have received 419. Removed, and Sanctum was taken out of the project entirely.
- **Double token verification.** The middleware authenticated through the `JWTAuth` facade
  without populating the guard, so every later `auth()->user()` re-parsed and re-verified the
  token. Fixed by setting the resolved user on the guard once. Handling the return value
  properly also closed a gap where a token whose subject had been deleted passed through
  unauthenticated.
- **Unconditional seeder.** `DatabaseSeeder` created two accounts with the password
  `password` in any environment. Guarded with an early return in production.
- **Exception handler ordering.** Renderable callbacks match in registration order, and the
  JWT callbacks sat below the `Throwable` catch-all, so every JWT exception was reported as a
  generic 500. The suite caught this immediately.
- **`abort()` losing its status.** `abort()` throws a plain `HttpException` for every status
  except 404, which matched none of the specific callbacks and fell to the catch-all, turning
  `abort(409)` into a 500 with the message discarded.
- **Dead CORS entry.** `config/cors.php` still listed `sanctum/csrf-cookie` after Sanctum was
  removed.

## Findings that were declined

- **Adding CRLF and control-character validation to `LoginRequest`.** Declined. The
  looseness is deliberate: login validates presence only, so it neither rejects legacy
  addresses nor reveals which formats are accepted, and the CVE in question concerns the
  `email` validation rule, which login does not use.
- **`app/Http/Responses/` as a new top-level namespace.** Flagged against a project rule
  about new directories. Kept: it is the conventional Laravel home for this, and the
  alternative is response construction spread across controllers.

## Design decisions and rationale
W

- **Hand-rolled RBAC over `spatie/laravel-permission`.** Two fixed roles do not justify that
  package's tables and cache layer. An enum, a column, a policy and one middleware is about
  forty lines and correct. It is trivially upgradable if the permission set grows.
- **404 rather than 403 for another user's todo.** A 403 confirms the record exists, which
  makes the endpoint an id-enumeration oracle across tenants. The generic not-found message
  in the handler exists for the same reason: Laravel's own message names the model class.
- **A `token_version` claim.** Bumped on password or email change, invalidating every
  outstanding token. Blacklisting alone only covers tokens the client surrenders.
- **A dedicated `job_statuses` table rather than `Bus::batch()`.** Batches carry no result
  payload and are not scoped to a user. A UUID-keyed row gives per-user status that is not
  enumerable across tenants.
- **CI running the README's own commands.** The workflow brings up the real compose stack and
  runs the documented steps rather than using service containers, so a clean clone is proven
  to work rather than assumed. It is slower, and it has already earned the difference: it
  caught a stale lock file, a uid assumption that only appears on a machine where the
  checkout is not owned by uid 1000, and a `jwt:secret` step that reported success while
  writing nothing.
- **Composer advisory policy.** Laravel 10 is past security support, so three advisories
  against the framework are accepted explicitly, with reasons and mitigations recorded in
  `composer.json` under `config.policy.advisories.ignore-id` rather than the check being
  disabled wholesale. One of them, CVE-2026-48019, is a CRLF injection in the default email
  validation rule with no patched 10.x release, mitigated in application code by validating
  with `email:rfc,strict` and rejecting control characters in every Form Request accepting an
  email address.

## Appendix: prompts

Reconstructed from session history. Prompt A is verbatim; the rest are the substance of what
was asked, with routine follow-ups ("run the tests") omitted.

### A. Planning, which produced the issue list

> I have created a Laravel 10 project with `docker run --rm -e
> COMPOSER_NO_SECURITY_BLOCKING=1 -v "$PWD":/app -w /app composer:2 create-project
> laravel/laravel:^10.0 user-todo-api --no-interaction` and the reason I have no security
> blocking has been recorded in my composer.json due to it being Laravel 10. The project is
> based on [the test brief PDF] so I wanted to create the different tasks as in the github
> repo project that I have pushed. It is not part of the requirements but I wanted a task
> where I will create a github action to ensure all tests pass on PR to main. This means it
> needs to be after setting up everything as I have not set up docker all environments and
> the like. Interview me relentlessly about every aspect of this plan until we reach a shared
> understanding. Walk down each branch of the design tree, resolving dependencies between
> decisions one-by-one. For each question, provide your recommended answer. Ask the questions
> one at a time.

### B. Per-issue implementation

Most issues were implemented with AI involvement being inline auto-complete. However, one issue,
the CI one, was assisted by Claude Code by giving it the below prompt:

> Implement issue #27: GitHub Actions CI. Read first: README.md, docker-compose.yml,
> Dockerfile, phpunit.xml, .env.example and CLAUDE.md. The workflow must run the exact
> commands the README tells a reviewer to run. If the README and the workflow disagree, that
> is a bug in one of them — tell me which rather than silently diverging.
>
> [scope: two jobs, triggers, healthcheck polling, teardown]
>
> Known traps in this repo, all of which have already cost time: use `docker compose exec -T`
> for every in-container command, since runners have no TTY. Pass `-e HOME=/tmp` to artisan
> commands, because www-data's home is root-owned. `env_file: .env` means compose bakes
> environment values in at container creation, so a key generated after `up -d` is written to
> the file but invisible to the running container.
>
> Verify before you report done: push the branch, open a PR, confirm the run goes green. A
> workflow that has never executed is not finished work.

### C. Review

Run after each issue's branch was complete:

```
/code-review
/code-review low
/code-review medium
```

Findings were then triaged individually rather than applied wholesale, with an explicit
response to each — for example:

> Fixing: the protected-route test, the dummy hash cost, the duplicate-register race, the
> composer php constraint. Deferring: auth throttling is a later issue. Declining: the
> CRLF checks on LoginRequest — that looseness is deliberate. On the register-vs-login
> enumeration tradeoff: correct, and I'm documenting it rather than fixing it.

### D. Verification

A final independent pass, run against a freshly provisioned stack rather than the working
copy, exercising every endpoint in order and reviewing the codebase against each numbered
requirement in the brief.