# Contributing

Thanks for considering a contribution. Here's how the project works and what to expect.

## Getting Started

Fork the repo, clone it, and install dependencies:

```bash
composer install
```

## Running the Test Suite

The full pipeline runs Rector, Pint, PHPStan (level 10), type coverage, and Pest:

```bash
composer test
```

You can also run individual steps:

```bash
composer test:refactor    # Rector (dry-run)
composer test:lint        # Pint
composer test:types       # PHPStan
composer test:type-coverage  # 100% type coverage check
pest --parallel           # tests only
```

All of these need to pass before a PR can be merged. CI runs the same `composer test` command.

## Code Style

The project uses [Laravel Pint](https://laravel.com/docs/pint) with the default Laravel preset. Run `composer lint` to auto-fix formatting. Don't worry about getting it perfect — Pint catches anything you miss.

## Static Analysis

PHPStan runs at **level 10** with [Larastan](https://github.com/larastan/larastan). The project also enforces 100% type coverage via Pest. If you add a public method, it needs a return type. If you add a relationship method, it needs a `@return` generic.

## Rector

[Rector](https://getrector.com/) runs in dry-run mode as part of the test suite. If it wants to change your code, either apply the suggestion (`composer refactor`) or skip the rule in `rector.php` with a comment explaining why.

## Writing Tests

Tests live in `tests/` and use [Pest](https://pestphp.com/). Feature tests use Orchestra Testbench with an in-memory SQLite database. Test fixtures (models, migrations, commands) live in `tests/Fixtures/`.

If you're fixing a bug, write a test that fails without your fix first.

## Pull Requests

- One feature or fix per PR. Keep it focused.
- Write a clear description of what changed and why. If there's a related issue, reference it.
- New features should include tests. Bug fixes should include a regression test.
- Don't bundle unrelated formatting changes, refactors, or dependency bumps.
- Target the `main` branch.

## Branch Naming

No strict convention — just make it descriptive. `fix/scoping-bug`, `feature/subdomain-resolver`, `docs/queue-section` are all fine.

## Commit Messages

Follow conventional commits loosely. A prefix like `feat:`, `fix:`, `docs:`, `test:`, or `chore:` helps when scanning the log, but don't overthink it.

## Security Vulnerabilities

If you find a security issue, **do not open a public issue**. Email **ubayedtanvir@yahoo.com** directly. See [SECURITY](SECURITY.md) for the full policy.

## Questions?

Open a discussion or issue. Happy to help you get oriented if you're unsure where something lives or how a piece fits together.
