<?php
namespace Drupal\graphql_apq_plus\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;

/**
 * APQ storage service using a cache backend (Redis recommended).
 */
class ApqStorage {

  protected CacheBackendInterface $cache;
  protected LoggerInterface $logger;
  protected int $ttl;

  public function __construct(CacheBackendInterface $cache, LoggerInterface $logger) {
    $this->cache = $cache;
    $this->logger = $logger;
    $this->ttl = 2592000; // 30 days default
  }

  /**
   * Store a query by its hash.
   *
   * @param string $hash
   *   The sha256 hash (hex) identifier.
   * @param string $query
   *   Full GraphQL query string.
   */
  public function set(string $hash, string $query): void {
    try {
      // store as data with expiration
      $this->cache->set($hash, $query, time() + $this->ttl);
      $this->logger->debug('APQ: Stored query for hash ' . substr($hash, 0, 8));
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage set failed: ' . $e->getMessage());
    }
  }

  /**
   * Get a stored query by its hash.
   *
   * @param string $hash
   *   Hex hash.
   *
   * @return string|null
   *   The stored query or NULL when not found.
   */
  public function get(string $hash): ?string {
    try {
      $item = $this->cache->get($hash);
      if ($item !== NULL) {
        # Redis cache may return an object with 'data'
        if (is_object($item) && property_exists($item, 'data')) {
          return $item->data;
        }
        if (is_string($item)) {
          return $item;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage get failed: ' . $e->getMessage());
    }
    return NULL;
  }

  /**
   * Delete a stored query by its hash.
   */
  public function delete(string $hash): bool {
    try {
      $this->cache->delete($hash);
      return true;
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage delete failed: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * List keys stored in cache - backend dependent. For Redis, we try to scan.
   *
   * WARNING: not all cache backends support scanning. This method attempts
   * to return an array of hashes if possible.
   *
   * @return array
   *   Array of hashes (strings).
   */
  public function listKeys(): array {
    try {
      $backend = $this->cache;
      // If Redis backend provided by contrib/redis, it may expose the phpredis client.
      if (property_exists($backend, 'connection') && is_object($backend->connection)) {
        $client = $backend->connection;
        if (method_exists($client, 'scan')) {
          $it = NULL;
          $results = [];
          while ($keys = $client->scan($it, '*')) {
            foreach ($keys as $k) {
              // The redis key may include prefix; return raw key.
              $results[] = $k;
            }
          }
          return $results;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ listKeys failed: ' . $e->getMessage());
    }
    return [];
  }

}
