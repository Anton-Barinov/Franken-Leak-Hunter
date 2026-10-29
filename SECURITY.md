# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Security Considerations for Worker Mode

Running PHP in persistent worker mode (FrankenPHP, RoadRunner, Swoole, Workerman) changes PHP's security model fundamentally:
- In PHP-FPM, request state is torn down at `RSHUTDOWN`.
- In worker mode, uncleaned state leaks between users (e.g., authenticated user ID, cart data, tenant IDs, DB transactions).

`Franken-Leak-Hunter` helps maintain zero-trust tenant boundaries and prevents cross-request data leaks.

## Reporting a Vulnerability

If you discover a vulnerability or security flaw, please report it via GitHub Security Advisories or contact the maintainer at [github.com/Anton-Barinov](https://github.com/Anton-Barinov).
