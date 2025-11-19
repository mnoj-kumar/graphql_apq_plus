# graphql_apq_plus

A small APQ helper module for Drupal 11 GraphQL endpoints.

## Install

1. Place the module in `web/modules/custom/graphql_apq_plus`.
2. Run `ddev drush en graphql_apq_plus -y` (or enable through UI).
3. Clear caches: `ddev drush cr`.

## How it works

- When the client sends **both** `query` and `extensions.persistedQuery.sha256Hash`, the module stores the mapping in Drupal cache.
- When the client sends **only** `extensions.persistedQuery.sha256Hash` without `query`, the module injects the stored `query` into the request body before GraphQL handling.

## Notes

- This module uses `cache.default`. If you use Redis or a dedicated cache bin, change the service injection in `services.yml`.
- The subscriber listens to requests on paths starting with `/graphql`. If your GraphQL endpoint path differs, adjust the check in `ApqRequestSubscriber::onKernelRequest()`.
- The module does not perform any signature validation; it trusts the provided hash as an identifier. For production, you may want to validate the hash format (e.g., hex 64-character sha256) and/or limit which clients may register persisted queries.
- The cache TTL is set to 30 days by default.

## Security & hardening ideas

- Validate `$hash` format (only hex strings of 64 chars).
- Use a dedicated cache bin (e.g., `cache.graphql_apq`) via service injection.
- Rate-limit storing operations to avoid cache spam.
- Optionally require an authorization header to allow registering persisted queries.
