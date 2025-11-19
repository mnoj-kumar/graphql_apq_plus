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
    $this->ttl = 2592000; // 30 days
  }

  /**
   * Store a query by its hash.
   */
  public function set(string $hash, string $query): void {
    try {
      $this->cache->set($hash, $query, time() + $this->ttl);
      $this->logger->debug('APQ: Stored query for hash ' . substr($hash, 0, 8));
    }
    catch (\Throwable $e) {
      $this->logger->warning('APQ storage set failed: ' . $e->getMessage());
    }
  }

  /**
   * Get a stored query by its hash.
   */
  public function get(string $hash): ?string {
    try {
      $item = $this->cache->get($hash);
      if ($item !== NULL) {
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
   * Delete a stored query by hash.
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
   * List keys stored in cache (best-effort). Works with Redis.
   */
  public function listKeys(): array {
    try {
      if (property_exists($this->cache, 'connection') && is_object($this->cache->connection)) {
        $client = $this->cache->connection;
        if (method_exists($client, 'scan')) {
          $it = NULL;
          $results = [];
          while ($keys = $client->scan($it, '*')) {
            foreach ($keys as $k) {
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