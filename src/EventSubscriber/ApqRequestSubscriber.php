<?php
namespace Drupal\graphql_apq_plus\EventSubscriber;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\graphql_apq_plus\Service\ApqStorage;
use Psr\Log\LoggerInterface;

/**
 * Intercepts GraphQL HTTP requests and handles APQ persisted queries.
 */
class ApqRequestSubscriber implements EventSubscriberInterface {

  protected ApqStorage $storage;
  protected LoggerInterface $logger;

  public function __construct(ApqStorage $storage, LoggerInterface $logger) {
    $this->storage = $storage;
    $this->logger = $logger;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 100],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();
    try {
      if ($request->getMethod() !== 'POST') {
        return;
      }

      $path = $request->getPathInfo();
      if (strpos($path, '/graphql') !== 0 && strpos($path, '/graphql/') !== 0) {
        return;
      }

      $contentType = (string) $request->headers->get('Content-Type', '');
      if ($contentType === '' || stripos($contentType, 'application/json') === false) {
        return;
      }

      $content = $request->getContent();
      if ($content === '' || $content === null) {
        return;
      }

      $data = json_decode($content, true);
      if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return;
      }

      $extensions = isset($data['extensions']) && is_array($data['extensions']) ? $data['extensions'] : [];
      $persisted = isset($extensions['persistedQuery']) && is_array($extensions['persistedQuery']) ? $extensions['persistedQuery'] : null;
      if (!$persisted) {
        return;
      }

      $hash = $persisted['sha256Hash'] ?? $persisted['hash'] ?? null;
      if ($hash !== null && !is_string($hash)) {
        $this->logger->warning('APQ: received non-string hash; ignoring.');
        return;
      }

      if ($hash !== null) {
        if (!empty($data['query']) && is_string($data['query'])) {
          try {
            $this->storage->set($hash, $data['query']);
          }
          catch (\Throwable $e) {
            $this->logger->warning('APQ store error: ' . $e->getMessage());
          }
          $request->request->replace(is_array($data) ? $data : []);
          return;
        }

        try {
          $stored = $this->storage->get($hash);
        }
        catch (\Throwable $e) {
          $this->logger->warning('APQ get error: ' . $e->getMessage());
          $stored = null;
        }

        if ($stored !== null && is_string($stored) && $stored !== '') {
          $data['query'] = $stored;
          $request->request->replace(is_array($data) ? $data : []);
          $this->logger->debug('APQ: Injected stored query for hash ' . substr($hash, 0, 8));
          return;
        }
        else {
          $this->logger->notice('APQ: No stored query found for hash ' . substr((string) $hash, 0, 8));
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('APQ subscriber error: ' . $e->getMessage());
      return;
    }
  }

}
