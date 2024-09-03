<?php

namespace Drupal\graphql\Plugin\GraphQL\PersistedQuery;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\PersistedQuery\PersistedQueryPluginBase;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Load persisted queries from the cache.
 *
 * @PersistedQuery(
 *   id = "automatic_persisted_query",
 *   label = "Automatic Persisted Query",
 *   description = "Load persisted queries from the cache."
 * )
 */
class AutomaticPersistedQuery extends PersistedQueryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The cache to store persisted queries.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache, KillSwitch $pageCacheKillSwitch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->cache = $cache;
    $this->pageCacheKillSwitch = $pageCacheKillSwitch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.graphql.apq'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery($id, OperationParams $operation) {
    if ($query = $this->cache->get($id)) {
      return $query->data;
    }
    // Preventing page cache for this request. Otherwise, we would need to add
    // a cache tag to the response and flush it when we add the persisted
    // query. This is not necessary, because the PersistedQueryNotFound
    // response is very short-lived.
    $this->pageCacheKillSwitch->trigger();
    throw new RequestError('PersistedQueryNotFound');
  }

}
