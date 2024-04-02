<?php

namespace Drupal\graphql\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\graphql\Event\OperationEvent;
use GraphQL\Error\Error;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Save persisted queries to cache.
 */
class ApqSubscriber implements EventSubscriberInterface {

  /**
   * The cache to store persisted queries.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a ApqSubscriber object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache to store persisted queries.
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * Handle operation start events.
   *
   * @param \Drupal\graphql\Event\OperationEvent $event
   *   The kernel event object.
   *
   * @throws \GraphQL\Error\Error
   */
  public function onBeforeOperation(OperationEvent $event): void {
    if (!array_key_exists('automatic_persisted_query', $event->getContext()->getServer()->getPersistedQueryInstances() ?? [])) {
      return;
    }
    $query = $event->getContext()->getOperation()->query;
    $queryHash = $event->getContext()->getOperation()->extensions['persistedQuery']['sha256Hash'] ?? '';

    if (is_string($query) && is_string($queryHash) && $queryHash !== '') {
      $computedQueryHash = hash('sha256', $query);
      if ($queryHash !== $computedQueryHash) {
        throw new Error('Provided sha does not match query');
      }
      $this->cache->set($queryHash, $query);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OperationEvent::GRAPHQL_OPERATION_BEFORE => 'onBeforeOperation',
    ];
  }

}
