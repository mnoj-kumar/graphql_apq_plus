# Custom GraphQL APQ Plus

This module implements Automatic Persisted Queries (APQ) for GraphQL Compose (Drupal 11) and provides a control panel with analytics, Redis-friendly storage, export/import, per-domain support, and cleanup tools.

Installation:
1. Copy module to modules/custom/custom_graphql_apq_plus
2. Ensure cache bin 'graphql.apq' is configured to use Redis in settings.php
3. drush en custom_graphql_apq_plus -y
4. drush cr

Admin UI: /admin/config/graphql/apq
