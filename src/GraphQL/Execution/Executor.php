<?php

namespace Drupal\graphql\GraphQL\Execution;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Utility\Error as ErrorUtil;
use Drupal\graphql\Event\OperationEvent;
use Drupal\graphql\GraphQL\Execution\ExecutionResult as CacheableExecutionResult;
use Drupal\graphql\GraphQL\Utility\DocumentSerializer;
use GraphQL\Error\ClientAware;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\ExecutorImplementation;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Executor\ReferenceExecutor;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Server\OperationParams;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Executes GraphQL queries with cache lookup.
 */
class Executor implements ExecutorImplementation {

  /**
   * The schema plugin manager.
   *
   * @var \Drupal\graphql\Plugin\SchemaPluginManager
   */
  protected $pluginManager;

  /**
   * The cache backend for caching query results.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The cache contexts manager service.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $contextsManager;

  /**
   * The date/time service.
   */
  protected TimeInterface $time;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The adapter for promises.
   *
   * @var \GraphQL\Executor\Promise\PromiseAdapter
   */
  protected $adapter;

  /**
   * Represents the GraphQL schema document.
   *
   * @var \GraphQL\Language\AST\DocumentNode
   */
  protected $document;

  /**
   * The context to pass down during field resolving.
   *
   * @var \Drupal\graphql\GraphQL\Execution\ResolveContext
   */
  protected $context;

  /**
   * The root of the GraphQL execution tree.
   *
   * @var mixed
   */
  protected $root;

  /**
   * Variables.
   *
   * @var array
   */
  protected $variables;

  /**
   * The parsed GraphQL schema.
   *
   * @var \GraphQL\Type\Schema
   */
  protected $schema;

  /**
   * The operation to be performed.
   *
   * @var string
   */
  protected $operation;

  /**
   * The resolver to get results for the query.
   *
   * @var callable
   */
  protected $resolver;

  /**
   * Executor constructor.
   *
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $contextsManager
   *   The cache contexts manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend for caching query results.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The date/time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \GraphQL\Executor\Promise\PromiseAdapter $adapter
   * @param \GraphQL\Type\Schema $schema
   * @param \GraphQL\Language\AST\DocumentNode $document
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param mixed $root
   * @param mixed $variables
   * @param string $operation
   * @param callable $resolver
   */
  public function __construct(
    CacheContextsManager $contextsManager,
    CacheBackendInterface $cacheBackend,
    TimeInterface $time,
    EventDispatcherInterface $dispatcher,
    PromiseAdapter $adapter,
    Schema $schema,
    DocumentNode $document,
    ResolveContext $context,
    $root,
    $variables,
    $operation,
    callable $resolver
  ) {
    $this->contextsManager = $contextsManager;
    $this->cacheBackend = $cacheBackend;
    $this->time = $time;
    $this->dispatcher = $dispatcher;

    $this->adapter = $adapter;
    $this->document = $document;
    $this->context = $context;
    $this->root = $root;
    $this->variables = $variables;
    $this->schema = $schema;
    $this->operation = $operation;
    $this->resolver = $resolver;
  }

  /**
   * Constructs an object from a services container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \GraphQL\Executor\Promise\PromiseAdapter $adapter
   * @param \GraphQL\Type\Schema $schema
   * @param \GraphQL\Language\AST\DocumentNode $document
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param mixed $root
   * @param mixed $variables
   * @param string $operation
   * @param callable $resolver
   *
   * @return \Drupal\graphql\GraphQL\Execution\Executor
   */
  public static function create(
    ContainerInterface $container,
    PromiseAdapter $adapter,
    Schema $schema,
    DocumentNode $document,
    ResolveContext $context,
    $root,
    $variables,
    $operation,
    callable $resolver
  ) {
    return new static(
      $container->get('cache_contexts_manager'),
      $container->get('cache.graphql.results'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $adapter,
      $schema,
      $document,
      $context,
      $root,
      $variables,
      $operation,
      $resolver
    );
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(): Promise {
    $server = $this->context->getServer();
    $operation_def = AST::getOperationAST($this->document, $this->operation);
    if ($operation_def && $operation_def->operation === 'query' && !!$server->get('caching')) {
      return $this->doExecuteCached($this->cachePrefix());
    }

    // This operation can never be cached because we are either in development
    // mode (caching is disabled) or this is a non-cacheable operation.
    return $this->doExecuteUncached()->then(function ($result) {
      $this->context->mergeCacheMaxAge(0);

      $result = new CacheableExecutionResult($result->data, $result->errors, $result->extensions);
      $result->addCacheableDependency($this->context);
      return $result;
    });
  }

  /**
   * Try to return cached results, otherwise resolve the query.
   *
   * @param string $prefix
   *
   * @return \GraphQL\Executor\Promise\Promise
   */
  protected function doExecuteCached($prefix) {
    if ($result = $this->cacheRead($prefix)) {
      return $this->adapter->createFulfilled($result);
    }

    return $this->doExecuteUncached()->then(function (ExecutionResult $result) use ($prefix) {
      if (!empty($result->errors)) {
        $this->context->mergeCacheMaxAge(0);
      }

      $result = new CacheableExecutionResult($result->data, $result->errors, $result->extensions);
      $result->addCacheableDependency($this->context);
      if ($result->getCacheMaxAge() !== 0) {
        $this->cacheWrite($prefix, $result);
      }

      return $result;
    });
  }

  /**
   * Get query results on a cache miss.
   *
   * @return \GraphQL\Executor\Promise\Promise
   */
  protected function doExecuteUncached() {
    $executor = ReferenceExecutor::create(
      $this->adapter,
      $this->schema,
      $this->document,
      $this->root,
      $this->context,
      $this->variables,
      $this->operation,
      $this->resolver
    );

    $event = new OperationEvent($this->context);
    $this->dispatcher->dispatch($event, OperationEvent::GRAPHQL_OPERATION_BEFORE);

    return $executor->doExecute()->then(function ($result) {
      $event = new OperationEvent($this->context, $result);
      $this->dispatcher->dispatch($event, OperationEvent::GRAPHQL_OPERATION_AFTER);

      $this->logUnsafeErrors($this->context->getOperation(), $result);

      return $result;
    });
  }

  /**
   * Logs unsafe errors if any.
   *
   * @param \GraphQL\Server\OperationParams $operation
   * @param \Drupal\graphql\GraphQL\Execution\ExecutionResult $result
   */
  protected function logUnsafeErrors(OperationParams $operation, ExecutionResult $result): void {
    $hasUnsafeErrors = FALSE;
    $previousErrors = [];

    foreach ($result->errors as $index => $error) {
      // Don't log errors intended for clients, only log those that
      // a client would not be able to solve, they'd require work from
      // a server developer.
      if ($error instanceof ClientAware && $error->isClientSafe()) {
        continue;
      }

      $hasUnsafeErrors = TRUE;
      // Log the error that cause the error we caught. This makes the error
      // logs more useful because GraphQL usually wraps the original error.
      if ($error->getPrevious() instanceof \Throwable) {
        $previousErrors[] = strtr(
          "For error #@index: %type: @message in %function (line %line of %file)\n@backtrace_string.",
          ErrorUtil::decodeException($error->getPrevious()) + ['@index' => $index]
        );
      }
    }

    if ($hasUnsafeErrors) {
      \Drupal::logger('graphql')->error(
        "There were errors during a GraphQL execution.\nOperation details:\n<pre>\n{details}\n</pre>\nPrevious errors:\n<pre>\n{previous}\n</pre>",
        [
          'details' => json_encode([
            '$operation' => $operation,
            // Do not pass $result to json_encode because it implements
            // JsonSerializable and strips some data out during the
            // serialization.
            '$result->data' => $result->data,
            '$result->errors' => $result->errors,
            '$result->extensions' => $result->extensions,
          ], JSON_PRETTY_PRINT),
          'previous' => implode('\n\n', $previousErrors),
        ]
      );
    }
  }

  /**
   * Calculates the cache prefix from context for the current query.
   *
   * @return string
   */
  protected function cachePrefix() {
    // Sorting the variables and extensions will cause fewer cache vectors.
    // @todo Should we try to sort these recursively?
    $variables = $this->variables ?: [];
    ksort($variables);
    // @todo Should we submit a pull request to also pass the extensions in the
    // executor?
    $extensions = $this->context->getOperation()->extensions ?: [];
    ksort($extensions);

    $hash = hash('sha256', serialize([
      'query' => DocumentSerializer::serializeDocument($this->document),
      'variables' => $variables,
      'extensions' => $extensions,
      'operation' => $this->operation,
    ]));

    return $hash;
  }

  /**
   * Calculate the cache suffix for the current contexts.
   *
   * @param array $contexts
   *
   * @return string
   */
  protected function cacheSuffix(array $contexts = []) {
    $keys = $this->contextsManager->convertTokensToKeys($contexts)->getKeys();
    return hash('sha256', serialize($keys));
  }

  /**
   * Lookup cached results by contexts for this query.
   *
   * @param string $prefix
   *
   * @return \GraphQL\Executor\ExecutionResult|null
   */
  protected function cacheRead($prefix) {
    if (($cache = $this->cacheBackend->get("contexts:$prefix"))) {
      $suffix = $this->cacheSuffix($cache->data ?? []);
      if (($cache = $this->cacheBackend->get("result:$prefix:$suffix"))) {
        $result = new CacheableExecutionResult($cache->data['data'], [], $cache->data['extensions']);
        $result->addCacheableDependency($cache->data['metadata']);
        return $result;
      }
    }

    return NULL;
  }

  /**
   * Store results in cache.
   *
   * @param string $prefix
   * @param \Drupal\graphql\GraphQL\Execution\ExecutionResult $result
   *
   * @return \Drupal\graphql\GraphQL\Execution\Executor
   */
  protected function cacheWrite($prefix, CacheableExecutionResult $result) {
    $contexts = $result->getCacheContexts();
    $expire = $this->maxAgeToExpire($result->getCacheMaxAge());
    $tags = $result->getCacheTags();
    $suffix = $this->cacheSuffix($contexts);

    $metadata = new CacheableMetadata();
    $metadata->addCacheableDependency($result);

    $cache = [
      'data' => $result->data,
      'extensions' => $result->extensions,
      'metadata' => $metadata,
    ];

    $this->cacheBackend->setMultiple([
      "contexts:$prefix"       => [
        'data' => $contexts,
        'expire' => $expire,
        'tags' => $tags,
      ],
      "result:$prefix:$suffix" => [
        'data' => $cache,
        'expire' => $expire,
        'tags' => $tags,
      ],
    ]);

    return $this;
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
    $time = $this->time->getRequestTime();
    return ($maxAge === Cache::PERMANENT) ? Cache::PERMANENT : (int) $time + $maxAge;
  }

}
