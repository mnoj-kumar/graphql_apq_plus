# graphql_apq_plus — Complete module

This module provides Apollo APQ support for Drupal 11 with Redis-backed storage and a simple admin UI.

## Installation

1. Copy this folder to `web/modules/custom/graphql_apq_plus`.
2. In `settings.php`, add the redis services YAML and map the cache bin (if using redis module):

```php
$settings['container_yamls'][] = DRUPAL_ROOT . '/modules/contrib/redis/redis.services.yml';
$settings['cache']['bins']['graphql_apq_plus'] = 'cache.backend.redis';
$settings['redis.connection']['host'] = '127.0.0.1';
$settings['redis.connection']['port'] = 6379;
