# graphql_apq_plus — Full version

This module provides Apollo APQ support for Drupal 11 with Redis-backed storage and an admin UI to view/delete persisted queries.

## Installation

1. Copy this folder to `web/modules/custom/graphql_apq_plus`.
2. Configure a dedicated cache bin in `settings.php` (example below).
3. Ensure the Redis module is installed and configured (or your preferred cache backend supports key listing).
4. Enable the module: `ddev drush en graphql_apq_plus -y`.

## settings.php example (using Redis via redis module)

```php
// Add the redis services YAML if using contrib redis module (adjust path if needed).
$settings['container_yamls'][] = DRUPAL_ROOT . '/modules/contrib/redis/redis.services.yml';

// Map custom bin to redis backend
$settings['cache']['bins']['graphql_apq_plus'] = 'cache.backend.redis';

// Redis connection settings
$settings['redis.connection']['host'] = '127.0.0.1';
$settings['redis.connection']['port'] = 6379;
// If your Redis requires auth or TLS, configure accordingly.
```

## How it works

- Client sends APQ hash (extensions.persistedQuery.sha256Hash). If only hash is provided, this module will attempt to load the stored query and inject it into the request before GraphQL runs.
- If the client sends the full query with hash, the module stores it in the cache bin for future use.

## Admin UI

Visit: `/admin/config/graphql/apq` to view stored hashes (best-effort listing) and delete entries.

## Notes

- Listing keys depends on your cache backend. Redis supports scanning; other cache backends may not, which will make the UI only show the 'no keys' message but delete still works if you know the hash.
- Ensure your Nuxt client uses a HEX-string SHA256 (CryptoJS .toString(encHex)) so hashes match.
