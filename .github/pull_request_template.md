## What changed

<!-- One short paragraph. What does this PR add or alter? -->

## Decisions and tradeoffs

<!-- What alternatives were considered and why this one. Leave as "none" if
     the change was mechanical. -->

## How to test

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app vendor/bin/phpunit
```

<!-- Plus any endpoint calls specific to this change. -->

## Checklist

- [ ] Tests written and passing
- [ ] `pint --test` clean
- [ ] No `Model::` calls outside the Eloquent repositories
- [ ] README updated if setup changed
- [ ] `docs/AI_USAGE.md` updated

Closes #
