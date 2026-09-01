# freimguork-webservice

Reusable token-auth API module for Freimguork projects — register/login/logout, password reset,
email change, account deletion. A Composer package (`optisistem/freimguork-webservice`) built on
`freimguork-core`, mounted by a consuming app's `config/projects.php` via the `vendorApps` config
key (see `Core\Utils\Config`'s vendor-app merging). Never run standalone.

Sibling packages in this family: `freimguork-core` (shared framework), `freimguork-appacman`,
`freimguork-jwt`. See [API.md](API.md) for every endpoint.

## Requirements

- A consuming application already built on `freimguork-core`
- The consuming app owns the `user`/`user_token` schema (this package doesn't ship its own
  migration - see `Webservice\Model\User`/`UserToken`)

## Installation

```bash
composer require optisistem/freimguork-webservice
```

## What's here

- `Controller/` - the API endpoints (see [API.md](API.md))
- `Model/User.php` / `UserToken.php` - account + session token logic
- `Model/PasswordReset.php` / `EmailChange.php` - 6-digit code, 15 min TTL, 5 max attempts, same
  shape for both flows
- `Model/App.php` - maintenance-mode / forced-update checks, consulted on every request
  (`WebserviceController::build()`)
- Every controller requires an `Authorization` header: the consuming app's own shared secret
  (`requiresUserToken() === false` controllers only) or a real user token

## Testing

```bash
docker exec php sh -c "cd /var/www/html/freimguork-webservice && composer install"
docker exec php sh -c "cd /var/www/html/freimguork-webservice && composer test"
```

PHPUnit covers the four Models (`User`, `UserToken`, `EmailChange`, `PasswordReset`). No PHPStan
configured yet, unlike `freimguork-core`/`freimguork-appacman`.

## Versioning

Follows the same `v1.0`/`dev-master` split as `freimguork-core`: `v1.0` is the last
pre-modernization snapshot, still used by consuming apps that haven't migrated their controller
constructors yet. This package itself already tracks core's `dev-master`, since its own controllers
(`WebserviceController`, `WebController`, and their subclasses) are already migrated to the new
required-dependency constructor shape. `v2.0` is a checkpoint tag matching `freimguork-core`'s own
`v2.0` milestone, not a new pinning point.
