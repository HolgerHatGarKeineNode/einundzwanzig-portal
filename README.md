[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2Ff25a1151-9c87-4f14-9943-17d05fa736c9&style=plastic)](https://forge.laravel.com/prime-software/lsm-server-1/1833504)
[![Tests](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/actions/workflows/tests.yml/badge.svg)](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

# Einundzwanzig Portal

The code base behind [portal.einundzwanzig.space](https://portal.einundzwanzig.space) — the
Bitcoin meetup, course and library portal of the Einundzwanzig community.

### Hosted:

- de-DE: [https://portal.einundzwanzig.space/de/meetups](https://portal.einundzwanzig.space/de/meetups)
- de-AT: [https://portal.einundzwanzig.space/at/meetups](https://portal.einundzwanzig.space/at/meetups)
- de-CH: [https://portal.einundzwanzig.space/ch/meetups](https://portal.einundzwanzig.space/ch/meetups)
- pl-PL: [https://portal.dwadziesciajeden.pl/pl/meetups](https://portal.dwadziesciajeden.pl/pl/meetups)
- hu-HU: [https://portal.huszonegy.world/hu/meetups](https://portal.huszonegy.world/hu/meetups)

### Host your national domain?

To add your national domain, you need to create a CNAME record pointing to `portal.einundzwanzig.space`.

Here's how:

1. Add a subdomain like `portal.yourdomain.tld`
2. Create a CNAME record pointing to `portal.einundzwanzig.space`

DNS provider CNAME settings:

Type: `CNAME`
Name/Host/Alias: `portal`
Target/Value/Destination: `portal.einundzwanzig.space`

After setting up your CNAME, please notify the repository owner to refresh SSL certificates to include your domain.

## Contributing and Proposals

Issues, feature requests and pull requests are handled on GitHub:

- Issues: [github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues)
- Pull requests: [github.com/HolgerHatGarKeineNode/einundzwanzig-app/pulls](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/pulls)

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

## Development

### Prerequisites

- PHP 8.4+
- PostgreSQL (running locally or as a container)
- Redis (running locally or as a container)
- Node.js (npm)

### Installation

```cp .env.example .env```

```composer install```
*(you need a valid Flux Pro license or send a message to [Nostr - The Ben](http://njump.me/npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6))*

### Migrate and seed the database

```php artisan migrate:fresh --seed```

### Laravel storage link

```php artisan storage:link```

#### Install node dependencies

```npm install```

#### Start development environment

```composer run dev```

This starts the PHP dev server, queue worker, Pail log viewer, and Vite concurrently.

#### Update dependencies

```npm update```

## Security Vulnerabilities

Please do **not** open a public issue for security problems. Report them privately via
[GitHub Security Advisories](https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/security/advisories/new).
See [SECURITY.md](SECURITY.md) for details. All security vulnerabilities will be promptly addressed.

## License

Open-sourced software licensed under the [MIT license](LICENSE).
