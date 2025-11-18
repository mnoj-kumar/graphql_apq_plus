<?php
namespace Drupal\custom_graphql_apq_plus\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\State\StateInterface;

class ApqStorage {

  protected CacheBackendInterface $cache;
  protected StateInterface $state;
  protected string $indexKey = 'graphql_apq_index';

  public function __construct(CacheBackendInterface $cache, StateInterface $state) {
    $this->cache = $cache;
    $this->state = $state;
  }

  protected function getCid(string $hash, string $domain = ''): string {
    $prefix = 'apq:';
    if (!empty($domain)) {
      $prefix .= $domain . ':';
    }
    return $prefix . $hash;
  }

  public function set(string $hash, string $query, int $ttl = 86400, string $domain = ''): void {
    $cid = $this->getCid($hash, $domain);
    $this->cache->set($cid, $query, time() + $ttl);
    $index = $this->state->get($this->indexKey, []);
    $key = ($domain ? $domain.':':'') . $hash;
    if (!isset($index[$key])) {
      $index[$key] = [
        'hash' => $hash,
        'domain' => $domain,
        'created' => time(),
        'last_access' => time(),
        'usage' => 0,
      ];
    }
    else {
      $index[$key]['last_access'] = time();
    }
    $this->state->set($this->indexKey, $index);
  }

  public function get(string $hash, string $domain = ''): ?string {
    $cid = $this->getCid($hash, $domain);
    $item = $this->cache->get($cid);
    if ($item) {
      $this->hit($hash, $domain);
      return $item->data;
    }
    return NULL;
  }

  public function delete(string $hash, string $domain = ''): void {
    $cid = $this->getCid($hash, $domain);
    $this->cache->delete($cid);
    $index = $this->state->get($this->indexKey, []);
    $key = ($domain ? $domain.':':'') . $hash;
    if (isset($index[$key])) {
      unset($index[$key]);
      $this->state->set($this->indexKey, $index);
    }
  }

  protected function hit(string $hash, string $domain = ''): void {
    $index = $this->state->get($this->indexKey, []);
    $key = ($domain ? $domain.':':'') . $hash;
    if (!isset($index[$key])) {
      $index[$key] = [
        'hash' => $hash,
        'domain' => $domain,
        'created' => time(),
        'last_access' => time(),
        'usage' => 1,
      ];
    }
    else {
      $index[$key]['usage'] = ($index[$key]['usage'] ?? 0) + 1;
      $index[$key]['last_access'] = time();
    }
    $this->state->set($this->indexKey, $index);
  }

  public function listEntries(string $domain = ''): array {
    $index = $this->state->get($this->indexKey, []);
    if ($domain) {
      $filtered = [];
      foreach ($index as $k => $meta) {
        if (($meta['domain'] ?? '') === $domain) {
          $filtered[$k] = $meta;
        }
      }
      return $filtered;
    }
    return $index;
  }

  public function exportAll(): array {
    $index = $this->state->get($this->indexKey, []);
    $export = [];
    foreach ($index as $key => $meta) {
      $hash = $meta['hash'];
      $domain = $meta['domain'] ?? '';
      $query = $this->get($hash, $domain);
      if ($query !== NULL) {
        $export[$key] = ['meta' => $meta, 'query' => $query];
      }
    }
    return $export;
  }

  public function import(array $data, int $ttl = 86400): int {
    $count = 0;
    foreach ($data as $key => $entry) {
      if (isset($entry['query']) && isset($entry['meta']['hash'])) {
        $hash = $entry['meta']['hash'];
        $domain = $entry['meta']['domain'] ?? '';
        $this->set($hash, $entry['query'], $ttl, $domain);
        $count++;
      }
    }
    return $count;
  }

  public function cleanupOlderThan(int $seconds): int {
    $index = $this->state->get($this->indexKey, []);
    $now = time();
    $removed = 0;
    foreach ($index as $key => $meta) {
      if (($meta['last_access'] ?? 0) > 0 && ($now - $meta['last_access']) > $seconds) {
        $parts = explode(':', $key, 2);
        if (count($parts) == 2) {
          $domain = $parts[0];
          $hash = $parts[1];
        } else {
          $domain = '';
          $hash = $key;
        }
        $this->delete($hash, $domain);
        $removed++;
      }
    }
    return $removed;
  }
}
