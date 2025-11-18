<?php
namespace Drupal\custom_graphql_apq_plus\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApqAdminController extends ControllerBase {

  protected $storage;

  public function __construct() {
    $this->storage = \Drupal::service('custom_graphql_apq_plus.storage');
  }

  public static function create($container) {
    return new static();
  }

  public function overview(Request $request) {
    $domain = $request->query->get('domain', '');
    $entries = $this->storage->listEntries($domain);
    $rows = [];
    foreach ($entries as $key => $meta) {
      $rows[] = [
        'Hash' => $meta['hash'],
        'Domain' => $meta['domain'],
        'Created' => date('Y-m-d H:i:s', $meta['created']),
        'Last access' => date('Y-m-d H:i:s', $meta['last_access']),
        'Usage' => $meta['usage'],
        'Actions' => '<a href="/admin/config/graphql/apq/view?key=' . urlencode($key) . '">View</a> | <a href="/admin/config/graphql/apq/delete?key=' . urlencode($key) . '">Delete</a>',
      ];
    }

    $header = ['Hash', 'Domain', 'Created', 'Last access', 'Usage', 'Actions'];
    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => array_map(function($r){ return array_values($r); }, $rows),
    ];
    $build['export'] = [
      '#type' => 'link',
      '#title' => $this->t('Export all as JSON'),
      '#url' => \Drupal\Core\Url::fromRoute('custom_graphql_apq_plus.export'),
    ];
    return $build;
  }

  public function view(Request $request) {
    $key = $request->query->get('key');
    if (!$key) {
      return new Response('Missing key', 400);
    }
    $parts = explode(':', $key, 2);
    if (count($parts) == 2) {
      $domain = $parts[0];
      $hash = $parts[1];
    } else {
      $domain = '';
      $hash = $key;
    }
    $query = $this->storage->get($hash, $domain);
    if ($query === NULL) {
      return new Response('Not found', 404);
    }
    $build = [
      '#type' => 'markup',
      '#markup' => '<pre>' . htmlspecialchars($query) . '</pre>',
    ];
    return $build;
  }

  public function delete(Request $request) {
    $key = $request->query->get('key');
    if (!$key) {
      return new Response('Missing key', 400);
    }
    $parts = explode(':', $key, 2);
    if (count($parts) == 2) {
      $domain = $parts[0];
      $hash = $parts[1];
    } else {
      $domain = '';
      $hash = $key;
    }
    $this->storage->delete($hash, $domain);
    return new Response('Deleted', 200);
  }

  public function exportAll() {
    $data = $this->storage->exportAll();
    $response = new Response(json_encode($data, JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json']);
    return $response;
  }

  public function import(Request $request) {
    $content = $request->getContent();
    $json = json_decode($content, TRUE);
    if (!is_array($json)) {
      return new Response('Invalid JSON', 400);
    }
    $count = $this->storage->import($json);
    return new Response('Imported: ' . $count, 200);
  }
}
