<?php
namespace Drupal\graphql_apq_plus\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\graphql_apq_plus\Service\ApqStorage;

/**
 * Simple admin UI to view and manage stored persisted queries.
 */
class ApqAdminController implements ContainerInjectionInterface {

  protected $storage;

  public function __construct(ApqStorage $storage) {
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('graphql_apq_plus.apq_storage')
    );
  }

  /**
   * Overview page.
   */
  public function overview() {
    $rows = [];
    // Try to retrieve keys (best-effort).
    $keys = $this->storage->listKeys();

    // If listKeys couldn't return anything, show message and sample interface.
    if (empty($keys)) {
      $build = [
        '#markup' => Markup::create('<p>No keys available for listing. This page uses a best-effort scan; ensure your cache backend supports key scanning (Redis). You can still delete known hashes through direct URL if you have them.</p>'),
      ];
      $build['instructions'] = [
        '#markup' => Markup::create('<p>To remove a stored hash manually: <code>/admin/config/graphql/apq/delete/{hash}</code></p>'),
      ];
      return $build;
    }

    foreach ($keys as $k) {
      // attempt to normalize key name (strip prefix if used)
      $label = $k;
      $value = $this->storage->get($k);
      $delete_url = Url::fromRoute('graphql_apq_plus.delete', ['hash' => $k]);
      $link = Link::fromTextAndUrl('Delete', $delete_url)->toString();
      $rows[] = [
        'hash' => $label,
        'preview' => '<pre style="max-height:200px;overflow:auto;">' . htmlspecialchars(substr($value, 0, 1000)) . '</pre>',
        'action' => $link,
      ];
    }

    $header = [
      'hash' => 'Hash',
      'preview' => 'Query (preview)',
      'action' => 'Actions',
    ];

    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
  }

  /**
   * Delete a stored query.
   */
  public function delete($hash) {
    $this->storage->delete($hash);
    $response = new RedirectResponse('/admin/config/graphql/apq');
    $response->send();
    return;
  }

}
