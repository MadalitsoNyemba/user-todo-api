# User & Todo API

A dockerised RESTful API managing users and a per-user todo list. Layered Laravel 10 application (controllers to services to repositories) behind JWT authentication, with asynchronous work offloaded to Redis-backed queues.

> **Build status:** this README describes the finished stack. Steps marked
> _(pending)_ depend on work not yet merged and can be skipped until then.
> This note is removed before submission.

## Requirements

- Docker Engine 24+ with Compose v2 (`docker compose`, not `docker-compose`)
- No local PHP, Composer or MySQL needed. Everything runs in containers.

Windows users: run from inside WSL2 with the project on the Linux filesystem
(`~/code/...`, not `/mnt/c/...`). Bind mounts across the Windows filesystem
boundary are slow enough to be noticeable on every request.

## Quick start

```bash
# 1. Configuration
cp .env.example .env

# 2. Build and start the stack
docker compose build
docker compose run --rm app php artisan key:generate
docker compose up -d

# 3. Wait for db and redis to report healthy
docker compose ps

# 4. Install dependencies
docker compose exec app composer install

# 5. Application key
docker compose exec app php artisan key:generate

# 6. JWT signing secret                                          (pending)
docker compose exec app php artisan jwt:secret

# 7. Schema and demo data
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed                    # (pending)
```

The API is then at **http://localhost:8080**.

Migrations are deliberately not run from the container entrypoint. Automatic
migration on boot races itself the moment the app runs more than one replica,
so it stays an explicit step here.

### Seeded accounts _(pending)_

| email | password | role |
|---|---|---|
| `alice@example.com` | `password` | user |
| `bob@example.com` | `password` | user |

Two users rather than one, so tenant isolation can be checked by hand: log in
as Alice, note a todo id, log in as Bob, request that id, receive a 404.

## Services

| service | image | purpose | host port |
|---|---|---|---|
| `nginx` | `nginx:1.27-alpine` | serves `public/`, proxies PHP to `app:9000` | 8080 |
| `app` | built from `Dockerfile` (`dev` target) | PHP-FPM | — |
| `worker` | same image as `app` | `queue:work redis` | — |
| `db` | `mysql:8.0` | application and test schemas | 3307 |
| `redis` | `redis:7-alpine` | queue driver and cache store | 6380 |

Host ports for MySQL and Redis are shifted off their defaults so the stack does
not collide with anything already running locally. Change `FORWARD_DB_PORT` and
`FORWARD_REDIS_PORT` in `.env` if needed.

Redis is the queue driver, not only a cache. The `worker` service starts with
the stack and consumes jobs immediately, so no extra command is needed to see
asynchronous work processed.

## The queue worker

The worker runs automatically as its own service. To watch it:

```bash
docker compose logs -f worker
```

To run one in the foreground instead, for example while debugging a job:

```bash
docker compose stop worker
docker compose exec app php artisan queue:work redis --verbose
```

Code changes are picked up on the worker's next job cycle only after a restart,
since queue workers boot the framework once:

```bash
docker compose restart worker
```

## Tests

```bash
docker compose exec app vendor/bin/phpunit
```

Tests run against a real MySQL schema (`user_todo_api_testing`), created by
`docker/mysql/init/01-create-test-db.sql` on first initialisation of the
database volume. No sqlite or in-memory shortcut, so behaviour under test
matches behaviour in the running stack, and running the suite never touches
seeded data in the application database.

Code style:

```bash
docker compose exec app vendor/bin/pint --test
```

## Common commands

```bash
docker compose exec app php artisan migrate:fresh --seed   # reset schema and data
docker compose exec app php artisan tinker
docker compose exec app bash                               # shell in the app container
docker compose exec db mysql -u utapi -psecret user_todo_api
docker compose logs -f app
docker compose down                                        # stop, keep data
docker compose down -v                                     # stop and destroy data
```

## Production image

The `Dockerfile` has two targets. Compose uses `dev`, which carries no
application code and expects the source to be bind-mounted, so edits are live.
The `production` target bakes in the source, installs without dev dependencies,
builds a class-map authoritative autoloader and disables opcache timestamp
validation:

```bash
docker build --target production -t user-todo-api:prod .
```

CI builds this target on every pull request, so the shipping image is never an
untested artifact.

## Troubleshooting

**`docker compose up` fails complaining about `.env`**
Compose reads `.env` for both service configuration and variable interpolation.
Run `cp .env.example .env` first.

**Tests fail with `Unknown database 'user_todo_api_testing'`**
MySQL only runs the scripts in `docker-entrypoint-initdb.d` when the data volume
is first initialised. If the stack was started before that file existed:

```bash
docker compose down -v
docker compose up -d
```

**Permission errors writing to `storage/` or `bootstrap/cache/`**
The image remaps `www-data` to the UID and GID given as build arguments,
defaulting to 1000. If `id -u` reports something else, set `UID` and `GID` in
`.env` and rebuild with `docker compose build --no-cache app`.

**Port already in use**
Change `APP_PORT`, `FORWARD_DB_PORT` or `FORWARD_REDIS_PORT` in `.env` and run
`docker compose up -d` again.

**Jobs stay queued and never complete**
Check the worker is alive with `docker compose ps` and
`docker compose logs worker`. A worker that started before migrations ran will
have failed, and `restart: unless-stopped` should have recovered it.

**500 responses immediately after setup**
Usually a missing `APP_KEY` or `JWT_SECRET`. Re-run steps 5 and 6.

## Architecture

_Added as the layers land. See `CONTRIBUTING.md` for the layering rules._

## API reference

_Added with the endpoints. Interactive documentation will be available at
`/api/documentation`._

## AI usage

Models, tools and prompts used during this build are listed in
[`docs/AI_USAGE.md`](docs/AI_USAGE.md).