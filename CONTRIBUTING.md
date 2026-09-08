# Contributing

Use a focused branch and submit changes through a pull request. Every change
must preserve the invariants in `AGENTS.md` and pass:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer check --diff
./scripts/build-release.sh
```

Update the changelog and relevant security/architecture documentation whenever
behavior changes. Never use production secrets in tests, issues, fixtures, or
logs.

By contributing, you agree that your contribution is licensed under
GPL-3.0-or-later, the licence of this project.
