<?php
namespace Drupal\custom_graphql_apq_plus\EventSubscriber;

use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\custom_graphql_apq_plus\Service\ApqStorage;

class ApqTerminateSubscriber implements EventSubscriberInterface {

  protected ApqStorage $storage;

  public function __construct(ApqStorage $storage) {
    $this->storage = $storage;
  }

  public static function getSubscribedEvents() {
    return ['kernel.terminate' => ['onTerminate', 0]];
  }

  public function onTerminate(TerminateEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (strpos($path, '/graphql') !== 0 || !$request->isMethod('POST')) {
      return;
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return;
    }

    $apq = $data['extensions']['persistedQuery'] ?? NULL;
    if (empty($apq) || empty($apq['sha256Hash'])) {
      return;
    }
    if (empty($data['query'])) {
      return;
    }

    $hash = $apq['sha256Hash'];
    $host = $request->getHost();
    $ttl = 86400;
    $this->storage->set($hash, $data['query'], $ttl, $host);
  }
}
