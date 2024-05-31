<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use Drupal\graphql\GraphQL\Utility\DeferredUtility;
use Drupal\graphql\Plugin\DataProducerPluginCachingInterface;
use Drupal\graphql\Plugin\DataProducerPluginInterface;
use Drupal\graphql\Plugin\DataProducerPluginManager;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A proxy class that lazy resolves data producers and has a result cache.
 */
class DataProducerProxy implements ResolverInterface {

  /**
   * The plugin config.
   *
   * @var array
   */
  protected $config;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $id;

  /**
   * The plugin manager.
   *
   * @var \Drupal\graphql\Plugin\DataProducerPluginManager
   */
  protected $pluginManager;

  /**
   * The request stack for looking up request time.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache context manager for cache keys.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $contextsManager;

  /**
   * The cache backend for results.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The mapping of names to resolvers.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * If results should be cached.
   *
   * @var bool
   */
  protected $cached = FALSE;

  /**
   * Construct DataProducerProxy object.
   *
   * @param string $id
   *   DataProducer plugin id.
   * @param array $mapping
   * @param array $config
   *   Plugin configuration.
   * @param \Drupal\graphql\Plugin\DataProducerPluginManager $pluginManager
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $contextsManager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   */
  public function __construct(
    $id,
    array $mapping,
    array $config,
    DataProducerPluginManager $pluginManager,
    RequestStack $requestStack,
    CacheContextsManager $contextsManager,
    CacheBackendInterface $cacheBackend,
  ) {
    $this->id = $id;
    $this->mapping = $mapping;
    $this->config = $config;
    $this->pluginManager = $pluginManager;
    $this->requestStack = $requestStack;
    $this->contextsManager = $contextsManager;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * Create a new data producer proxy.
   *
   * @param string $id
   * @param array $mapping
   * @param array $config
   *
   * @return mixed
   */
  public static function create($id, array $mapping = [], array $config = []) {
    $manager = \Drupal::service('plugin.manager.graphql.data_producer');
    return $manager->proxy($id, $mapping, $config);
  }

  /**
   * Store a resolver for a given name.
   *
   * @param string $name
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $mapping
   *
   * @return $this
   */
  public function map($name, ResolverInterface $mapping) {
    $this->mapping[$name] = $mapping;
    return $this;
  }

  /**
   * Set the cached flag.
   *
   * @param bool $cached
   *
   * @return $this
   */
  public function cached($cached = TRUE) {
    $this->cached = $cached;
    return $this;
  }

  /**
   * Resolve field value.
   *
   * @param mixed $value
   * @param mixed $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolve($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    $plugin = $this->prepare($value, $args, $context, $info, $field);

    return DeferredUtility::returnFinally($plugin, function (DataProducerPluginInterface $plugin) use ($context, $field) {
      foreach ($plugin->getContexts() as $item) {
        /** @var \Drupal\Core\Plugin\Context\Context $item */
        if ($item->getContextDefinition()->isRequired() && !$item->hasContextValue()) {
          return NULL;
        }
      }

      if ($this->cached && $plugin instanceof DataProducerPluginCachingInterface) {
        if (!!$context->getServer()->get('caching')) {
          return $this->resolveCached($plugin, $context, $field);
        }
      }

      return $this->resolveUncached($plugin, $context, $field);
    });
  }

  /**
   * Instantiate the actual data producer and populate it with context values.
   *
   * @param mixed $value
   * @param mixed $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return \GraphQL\Deferred|\Drupal\graphql\Plugin\DataProducerPluginInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  protected function prepare($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    /** @var \Drupal\graphql\Plugin\DataProducerPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($this->id, $this->config);
    $contexts = $plugin->getContextDefinitions();

    $values = [];
    foreach ($contexts as $name => $definition) {
      $mapper = $this->mapping[$name] ?? NULL;
      if ($definition->isRequired() && empty($mapper)) {
        throw new \LogicException(sprintf('Missing input mapper for argument %s.', $name));
      }

      if (!empty($mapper) && !($mapper instanceof ResolverInterface)) {
        throw new \Exception(sprintf('Invalid input mapper for argument %s.', $name));
      }

      $values[$name] = !empty($mapper) ? $mapper->resolve($value, $args, $context, $info, $field) : NULL;
    }

    $values = DeferredUtility::waitAll($values);
    return DeferredUtility::returnFinally($values, function ($values) use ($plugin) {
      foreach ($values as $name => $value) {
        $plugin->setContextValue($name, $value);
      }

      return $plugin;
    });
  }

  /**
   * Invoke the data producer directly.
   *
   * @param \Drupal\graphql\Plugin\DataProducerPluginInterface $plugin
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   */
  protected function resolveUncached(DataProducerPluginInterface $plugin, ResolveContext $context, FieldContext $field) {
    $output = $plugin->resolveField($field);
    return DeferredUtility::applyFinally($output, function () use ($plugin, $field) {
      $field->addCacheableDependency($plugin);
    });
  }

  /**
   * Try to return a value from cache, otherwise invoke data producer.
   *
   * @param \Drupal\graphql\Plugin\DataProducerPluginCachingInterface $plugin
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   */
  protected function resolveCached(DataProducerPluginCachingInterface $plugin, ResolveContext $context, FieldContext $field) {
    $prefix = $this->edgeCachePrefix($plugin);
    if ($cache = $this->cacheRead($prefix)) {
      [$value, $metadata] = $cache;
      $field->addCacheableDependency($metadata);
      return $value;
    }

    $output = $this->resolveUncached($plugin, $context, $field);
    return DeferredUtility::applyFinally($output, function ($value) use ($field, $prefix) {
      $this->cacheWrite($prefix, $value, $field);
    });
  }

  /**
   * Calculates a cache prefix.
   *
   * @param \Drupal\graphql\Plugin\DataProducerPluginCachingInterface $plugin
   *
   * @return string
   */
  protected function edgeCachePrefix(DataProducerPluginCachingInterface $plugin) {
    try {
      $prefix = $plugin->edgeCachePrefix();
    }
    catch (\Exception $e) {
      throw new \LogicException(sprintf('Failed to serialize edge cache vectors for plugin %s.', $plugin->getPluginId()));
    }

    $contexts = $plugin->getCacheContexts();
    $keys = $this->contextsManager->convertTokensToKeys($contexts)->getKeys();
    return md5(serialize([$plugin->getPluginId(), $prefix, $keys]));
  }

  /**
   * Cache lookup.
   *
   * @param string $prefix
   *
   * @return array|null
   */
  protected function cacheRead($prefix) {
    if ($cache = $this->cacheBackend->get("$prefix:context")) {
      $keys = !empty($cache->data) ? $this->contextsManager->convertTokensToKeys($cache->data)->getKeys() : [];
      $keys = serialize($keys);

      if (($cache = $this->cacheBackend->get("$prefix:result:$keys")) && $data = $cache->data) {
        return $data;
      }
    }

    return NULL;
  }

  /**
   * Store result values in cache.
   *
   * @param string $prefix
   * @param mixed $value
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   */
  protected function cacheWrite($prefix, $value, FieldContext $field): void {
    // Bail out early if the field context is already uncacheable.
    if ($field->getCacheMaxAge() === 0) {
      return;
    }

    $metadata = new CacheableMetadata();
    $metadata->addCacheableDependency($field);

    // Do not add the cache contexts from the result value because they are not
    // known at fetch time and would render the written cache unusable.
    if ($value instanceof CacheableDependencyInterface) {
      $metadata->addCacheTags($value->getCacheTags());
      $metadata->mergeCacheMaxAge($value->getCacheMaxAge());
    }

    if ($metadata->getCacheMaxAge() === 0) {
      return;
    }

    $expire = $this->maxAgeToExpire($metadata->getCacheMaxAge());
    $tags = $metadata->getCacheTags();
    $tokens = $metadata->getCacheContexts();

    $keys = !empty($tokens) ? $this->contextsManager->convertTokensToKeys($tokens)->getKeys() : [];
    $keys = serialize($keys);

    $this->cacheBackend->setMultiple([
      "$prefix:context" => [
        'data' => $tokens,
        'expire' => $expire,
        'tags' => $tags,
      ],
      "$prefix:result:$keys" => [
        'data' => [$value, $metadata],
        'expire' => $expire,
        'tags' => $tags,
      ],
    ]);
  }

  /**
   * Maps a cache max age value to an "expire" value for the Cache API.
   *
   * @param int $maxAge
   *
   * @return int
   *   A corresponding "expire" value.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::set()
   */
  protected function maxAgeToExpire($maxAge) {
    $time = $this->requestStack->getMainRequest()->server->get('REQUEST_TIME');
    return ($maxAge === Cache::PERMANENT) ? Cache::PERMANENT : (int) $time + $maxAge;
  }

}
