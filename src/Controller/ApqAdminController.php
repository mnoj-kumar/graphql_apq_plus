<?php
namespace Drupal\graphql_apq_plus\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Simple admin UI to view and manage stored persisted queries.
 */
class ApqAdminController {

  public function overview() {
    $storage = \Drupal::service('graphql_apq_plus.apq_storage');
    $keys = [];
    try {
      $keys = $storage->listKeys();
    } catch (\Throwable $e) {
      \Drupal::logger('graphql_apq_plus')->warning('Listing keys failed: ' . $e->getMessage());
    }

    if (empty($keys)) {
      $build = [
        '#markup' => Markup::create('<p>No keys available for listing. This page uses a best-effort scan; ensure your cache backend supports key scanning (Redis). You can still delete known hashes through direct URL if you have them.</p>'),
      ];
      $build['instructions'] = [
        '#markup' => Markup::create('<p>To remove a stored hash manually: <code>/admin/config/graphql/apq/delete/{hash}</code></p>'),
      ];
      return $build;
    }

    $rows = [];
    foreach ($keys as $k) {
      $value = $storage->get($k);
      $delete_url = Url::fromRoute('graphql_apq_plus.delete', ['hash' => $k]);
      $link = Link::fromTextAndUrl('Delete', $delete_url)->toString();
      $rows[] = [
        'hash' => $k,
        'preview' => ['#markup' => '<pre style="max-height:200px;overflow:auto;">' . htmlspecialchars(substr($value, 0, 1000)) . '</pre>'],
        'action' => ['#markup' => $link],
      ];
    }

    $header = ['hash' => 'Hash', 'preview' => 'Query (preview)', 'action' => 'Actions'];

    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
  }

  public function delete($hash) {
    $storage = \Drupal::service('graphql_apq_plus.apq_storage');
    $storage->delete($hash);
    return new RedirectResponse('/admin/config/graphql/apq');
  }

}
