# Contributing

Thanks for taking the time to contribute to the Einundzwanzig Portal.

## Where things happen

**GitHub is the single place for issues and pull requests.** The project no longer
uses gitworkshop.dev / NIP-34 Nostr git artifacts — everything is tracked here:

- [Issues](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues)
- [Pull requests](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/pulls)
- [Discussions and questions](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues/new/choose)

> **Planned:** issues and pull requests opened here will be mirrored to a Nostr Buzz relay,
> where an agent team is meant to pick them up. That pipeline is being built and is **not
> active yet** — GitHub remains the source of truth, and nothing you open here depends on it.

## Reporting a bug

Open a [bug report](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues/new?template=bug_report.yml)
and include the URL, what you expected, what happened, and — if the browser is involved —
the console output.

For anything security related, do **not** open a public issue. See [SECURITY.md](SECURITY.md).

## Proposing a change

1. Open an issue first for anything larger than a fix, so the approach can be agreed on.
2. Fork the repository and branch off `master`.
3. Keep the pull request focused — one topic per PR.

## Development setup

Prerequisites: PHP 8.4+, PostgreSQL, Redis, Node.js.

```bash
cp .env.example .env
composer install     # needs a valid Flux Pro license, see README
npm install
php artisan key:generate
php artisan ciphersweet:generate-key
php artisan migrate:fresh --seed
php artisan storage:link
composer run dev
```

Without a Flux Pro license `composer install` cannot resolve `livewire/flux-pro`. See the
README for how to get in touch about it.

## Before you open a pull request

```bash
vendor/bin/pint --dirty     # code style (Laravel Pint)
php artisan test --compact  # the test suite (Pest)
```

- **Every change needs a test.** Add a new test or update an existing one, and make sure the
  affected tests pass.
- Follow the conventions already used in the file you are editing — check sibling files for
  structure, naming and approach before introducing a new pattern.
- Browser tests (`tests/Browser`) need Playwright and have to be run locally.

**There is no CI in this repository.** Nothing runs your tests for you when you open a pull
request, so run them yourself before you do — a maintainer checks them again before merging.

## Commit messages and pull requests

- Write commit messages in the imperative ("Add city import", not "Added").
- Reference the issue the PR closes (`Closes #123`).
- Fill in the pull request template — it exists so a reviewer does not have to ask.

## Code of conduct

By participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Deployment

Pushing to `master` deploys to production automatically — Laravel Forge is connected to this
repository through its native GitHub integration (Quick Deploy). There is no deployment
workflow in `.github/workflows/`, and none is needed.
