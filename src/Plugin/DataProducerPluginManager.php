<?php

namespace Drupal\graphql\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Collects data producer plugins that are composed to read and write data.
 */
class DataProducerPluginManager extends DefaultPluginManager {

  /**
   * The request stack later used to get the request time.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache context manager for calculating cache keys.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $contextsManager;

  /**
   * The cache backend to cache results in.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $resultCacheBackend;

  /**
   * DataProducerPluginManager constructor.
   *
   * @param bool|string $pluginSubdirectory
   *   The plugin's subdirectory.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $definitionCacheBackend
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $contextsManager
   * @param \Drupal\Core\Cache\CacheBackendInterface $resultCacheBackend
   * @param string|null $pluginInterface
   *   The interface each plugin should implement.
   * @param string $pluginAnnotationName
   *   The name of the annotation that contains the plugin definition.
   * @param array $config
   *   The configuration service parameter.
   */
  public function __construct(
    $pluginSubdirectory,
    \Traversable $namespaces,
    ModuleHandlerInterface $moduleHandler,
    CacheBackendInterface $definitionCacheBackend,
    RequestStack $requestStack,
    CacheContextsManager $contextsManager,
    CacheBackendInterface $resultCacheBackend,
    $pluginInterface,
    $pluginAnnotationName,
    array $config,
  ) {
    parent::__construct(
      $pluginSubdirectory,
      $namespaces,
      $moduleHandler,
      $pluginInterface,
      $pluginAnnotationName
    );

    $this->alterInfo('graphql_data_producer');
    $this->useCaches(empty($config['development']));
    $this->setCacheBackend($definitionCacheBackend, 'graphql_data_producer', ['graphql_data_producer']);

    $this->requestStack = $requestStack;
    $this->contextsManager = $contextsManager;
    $this->resultCacheBackend = $resultCacheBackend;
  }

  /**
   * Creates a data producer proxy that lazy forwards resolve requests.
   *
   * The data producer with the given ID is wrapped.
   *
   * @param string $id
   * @param array $mapping
   * @param array $config
   *
   * @return \Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy
   */
  public function proxy($id, array $mapping = [], array $config = []) {
    return new DataProducerProxy(
      $id,
      $mapping,
      $config,
      $this,
      $this->requestStack,
      $this->contextsManager,
      $this->resultCacheBackend
    );
  }

}
