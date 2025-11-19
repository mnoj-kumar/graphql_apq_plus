<?php

namespace Drupal\graphql_apq_plus\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;

/**
 * Simple APQ storage using Drupal cache.
 */
class ApqStorage {
  protected CacheBackendInterface $cache;
  protected LoggerInterface $logger;

  // Cache key prefix to avoid collisions.
  protected string $prefix = 'graphql_apq_plus:';

  public function __construct(CacheBackendInterface $cache, LoggerInterface $logger) {
    $this->cache = $cache;
    $this->logger = $logger;
  }

  /**
   * Store a query by its hash.
   *
   * @param string $hash
   *   The hash (sha256 hex string or arbitrary identifier).
   * @param string $query
   *   Full GraphQL query string.
   * @param int $ttl
   *   Time to live in seconds (default 30 days).
   */
  public function set(string $hash, string $query, int $ttl = 2592000): void {
    $key = $this->prefix . $hash;
    try {
      $this->cache->set($key, $query, time() + $ttl);
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage set failed: ' . $e->getMessage());
    }
  }

  /**
   * Get a stored query by its hash.
   *
   * @param string $hash
   *   The hash identifier.
   *
   * @return string|null
   *   The stored query or NULL when not found.
   */
  public function get(string $hash): ?string {
    $key = $this->prefix . $hash;
    try {
      $item = $this->cache->get($key);
      if ($item !== NULL && is_string($item)) {
        return $item;
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage get failed: ' . $e->getMessage());
    }
    return NULL;
  }
}
