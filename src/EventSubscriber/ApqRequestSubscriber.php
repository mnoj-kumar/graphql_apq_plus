<?php
namespace Drupal\custom_graphql_apq_plus\EventSubscriber;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\custom_graphql_apq_plus\Service\ApqStorage;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApqRequestSubscriber implements EventSubscriberInterface {

  protected ApqStorage $storage;

  public function __construct(ApqStorage $storage) {
    $this->storage = $storage;
  }

  public static function getSubscribedEvents() {
    return ['kernel.request' => ['onRequest', 100]];
  }

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (strpos($path, '/graphql') !== 0 || !$request->isMethod('POST')) {
      return;
    }

    $content = $request->getContent();
    if (empty($content)) {
      return;
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data)) {
      return;
    }

    $apq = $data['extensions']['persistedQuery'] ?? NULL;
    if (empty($apq) || empty($apq['sha256Hash'])) {
      return;
    }

    $hash = $apq['sha256Hash'];
    $host = $request->getHost();
    $query = $this->storage->get($hash, $host);
    if ($query !== NULL) {
      $data['query'] = $query;
      $request->initialize(
        $request->query->all(),
        $request->request->all(),
        $request->attributes->all(),
        $request->cookies->all(),
        $request->files->all(),
        $request->server->all(),
        json_encode($data)
      );
      return;
    }

    $response = new JsonResponse(['errors' => [['message' => 'PersistedQueryNotFound']]], 200);
    $event->setResponse($response);
  }
}
