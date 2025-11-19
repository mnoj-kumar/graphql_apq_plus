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
    // Use a high priority so we run before GraphQL request processing.
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 100],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Only handle JSON POST requests to the typical GraphQL endpoint.
    if ($request->getMethod() !== 'POST') {
      return;
    }

    $path = $request->getPathInfo();
    // Adjust this if your GraphQL endpoint path differs.
    if (strpos($path, '/graphql') !== 0 && strpos($path, '/graphql/') !== 0) {
      return;
    }

    $content = $request->getContent();
    if (empty($content)) {
      return;
    }

    $data = json_decode($content, TRUE);
    if ($data === NULL) {
      // Not JSON — nothing to do.
      return;
    }

    $extensions = $data['extensions'] ?? [];
    $persisted = $extensions['persistedQuery'] ?? null;

    if (!$persisted || !is_array($persisted)) {
      // No APQ payload present — nothing to do.
      return;
    }

    // Apollo's field is usually `sha256Hash` (camelCase). Some clients use `hash`.
    $hash = $persisted['sha256Hash'] ?? $persisted['hash'] ?? NULL;

    // Validate hash is a string of expected format (optional weak validation).
    if ($hash !== NULL && !is_string($hash)) {
      // If it's not a string, log and bail to avoid TypeErrors.
      $this->logger->warning('APQ: received non-string hash. Ignoring.');
      return;
    }

    if ($hash !== NULL) {
      // If full `query` is present, store it.
      if (!empty($data['query']) && is_string($data['query'])) {
        try {
          $this->storage->set($hash, $data['query']);
        }
        catch (\Throwable $e) {
          $this->logger->warning('APQ store error: ' . $e->getMessage());
        }

        // No further modification required; let GraphQL handle the request normally.
        return;
      }

      // No `query` provided — attempt to retrieve stored query.
      try {
        $stored = $this->storage->get($hash);
      }
      catch (\Throwable $e) {
        $this->logger->warning('APQ get error: ' . $e->getMessage());
        $stored = NULL;
      }

      if ($stored !== NULL) {
        // Inject the stored query into the request body so GraphQL gets it.
        $data['query'] = $stored;
        $newContent = json_encode($data);
        if ($newContent !== FALSE) {
          $request->setContent($newContent);

          // Also update parsed body if GraphQL code reads from it.
          $request->request->replace(is_array($data) ? $data : []);

          $this->logger->debug('APQ: Injected stored query for hash ' . substr($hash, 0, 8));
        }
        else {
          $this->logger->warning('APQ: Failed to re-encode request body for hash ' . substr($hash, 0, 8));
        }
      }
      else {
        // Stored query not found — let the GraphQL server return appropriate error.
        $this->logger->notice('APQ: No stored query found for hash ' . substr($hash, 0, 8));
      }
    }
  }
}
