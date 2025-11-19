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
    // Priority high to run before GraphQL processing.
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 100],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Only handle JSON POST requests to GraphQL endpoint.
    if ($request->getMethod() !== 'POST') {
      return;
    }

    $path = $request->getPathInfo();
    if (strpos($path, '/graphql') !== 0 && strpos($path, '/graphql/') !== 0) {
      return;
    }

    $content = $request->getContent();
    if (empty($content)) {
      return;
    }

    $data = json_decode($content, TRUE);
    if ($data === NULL) {
      return;
    }

    $extensions = $data['extensions'] ?? [];
    $persisted = $extensions['persistedQuery'] ?? NULL;

    if (!$persisted || !is_array($persisted)) {
      return;
    }

    // Apollo uses sha256Hash field.
    $hash = $persisted['sha256Hash'] ?? $persisted['hash'] ?? NULL;

    if ($hash !== NULL && !is_string($hash)) {
      // If client sent crypto object, try convert to string if possible.
      if (is_array($hash) && isset($hash['toString'])) {
        $hash = (string) $hash['toString'];
      } else {
        $this->logger->warning('APQ: received non-string hash; ignoring.');
        return;
      }
    }

    if ($hash !== NULL) {
      // If the client included the query, store it.
      if (!empty($data['query']) && is_string($data['query'])) {
        try {
          $this->storage->set($hash, $data['query']);
        }
        catch (\Throwable $e) {
          $this->logger->warning('APQ store error: ' . $e->getMessage());
        }
        // Let GraphQL process the request normally.
        return;
      }

      // Client only provided hash — attempt to load stored query.
      try {
        $stored = $this->storage->get($hash);
      }
      catch (\Throwable $e) {
        $this->logger->warning('APQ get error: ' . $e->getMessage());
        $stored = NULL;
      }

      if ($stored !== NULL && is_string($stored) && $stored !== '') {
        // Inject stored query into request body.
        $data['query'] = $stored;
        $newContent = json_encode($data);
        if ($newContent !== FALSE) {
          $request->setContent($newContent);
          $request->request->replace(is_array($data) ? $data : []);
          $this->logger->debug('APQ: Injected stored query for hash ' . substr($hash, 0, 8));
          return;
        }
      }
      else {
        // Not found — let GraphQL server respond with PersistedQueryNotFound.
        $this->logger->notice('APQ: No stored query found for hash ' . substr((string) $hash, 0, 8));
      }
    }
  }

}
