<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\Context\ContextCacheKeys;
use Drupal\Core\Render\RenderContext;
use Drupal\graphql\Entity\Server;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use GraphQL\Deferred;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test query result caching.
 *
 * @group graphql
 */
class ResultCacheTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $schema = <<<GQL
      type Query {
        root: String
        leakA: String
        leakB: String
      }
GQL;
    $this->setUpSchema($schema);
  }

  /**
   * Check basic result caching.
   */
  public function testCacheableResult(): void {
    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->once())
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root',
      function () use ($dummy) {
        return $dummy->id();
      }
    );

    // The first request that is supposed to be cached.
    $this->query('{ root }');

    // This should *not* invoke the processor a second time.
    $this->query('{ root }');
  }

  /**
   * Verify that uncacheable results are not cached.
   */
  public function testUncacheableResult(): void {
    $cacheable = $this->getMockBuilder(CacheableDependencyInterface::class)
      ->onlyMethods(['getCacheTags', 'getCacheMaxAge', 'getCacheContexts'])
      ->getMock();

    $cacheable->expects($this->any())
      ->method('getCacheTags')
      ->willReturn([]);

    $cacheable->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(0);

    $cacheable->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);

    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root',
      $this->builder->compose(
        $this->builder->fromValue($cacheable),
        $this->builder->callback(function () use ($dummy) {
          return $dummy->id();
        })
      )
    );

    // The first request that is not supposed to be cached.
    $this->query('{ root }');

    // This should invoke the processor a second time.
    $this->query('{ root }');
  }

  /**
   * Verify that fields with uncacheable annotations are not cached.
   */
  public function testUncacheableResultAnnotation(): void {
    $cacheable = $this->getMockBuilder(CacheableDependencyInterface::class)
      ->onlyMethods(['getCacheTags', 'getCacheMaxAge', 'getCacheContexts'])
      ->getMock();

    $cacheable->expects($this->any())
      ->method('getCacheTags')
      ->willReturn([]);

    $cacheable->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(0);

    $cacheable->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);

    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root',
      $this->builder->compose(
        $this->builder->fromValue($cacheable),
        $this->builder->callback(function () use ($dummy) {
          return $dummy->id();
        })
      )
    );

    // The first request that is not supposed to be cached.
    $this->query('{ root }');

    // This should invoke the processor a second time.
    $this->query('{ root }');
  }

  /**
   * Test if caching properly handles variables.
   */
  public function testVariables(): void {
    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root',
      function () use ($dummy) {
        return $dummy->id();
      }
    );

    // This result will be stored in the cache.
    $this->query('{ root }', NULL, ['value' => 'a']);

    // This will trigger a new evaluation since it passes different variables.
    $this->query('{ root }', NULL, ['value' => 'b']);

    // This should be served from cache.
    $this->query('{ root }', NULL, ['value' => 'a']);
  }

  /**
   * Test if changing test context's trigger re-evaluations.
   */
  public function testContext(): void {
    $cacheable = $this->getMockBuilder(CacheableDependencyInterface::class)
      ->onlyMethods(['getCacheTags', 'getCacheMaxAge', 'getCacheContexts'])
      ->getMock();

    $cacheable->expects($this->any())
      ->method('getCacheTags')
      ->willReturn([]);

    $cacheable->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(45);

    $cacheable->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn(['context']);

    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    // Prepare a prophesied context manager.
    $contextManager = $this->prophesize(CacheContextsManager::class);
    $this->container->set('cache_contexts_manager', $contextManager->reveal());

    // All tokens are valid for this test.
    $contextManager->assertValidTokens(Argument::any())
      ->willReturn(TRUE);

    // Argument patterns that check if the 'context' is in the list.
    $hasContext = Argument::containing('context');
    $hasNotContext = Argument::that(function ($arg) {
      return !in_array('context', $arg);
    });

    // If 'context' is not defined, we return no cache keys.
    $contextManager->convertTokensToKeys($hasNotContext)
      ->willReturn(new ContextCacheKeys([]));

    // Store the method prophecy so we can replace the result on the fly.
    /** @var \Prophecy\Prophecy\MethodProphecy $contextKeys */
    $contextKeys = $contextManager->convertTokensToKeys($hasContext);

    $this->mockResolver('Query', 'root',
      $this->builder->compose(
        $this->builder->fromValue($cacheable),
        $this->builder->callback(function () use ($dummy) {
          return $dummy->id();
        })
      )
    );

    // Set the context value to 'a'/.
    $contextKeys->willReturn(new ContextCacheKeys(['a']));
    // This will be stored in the cache key for context 'a'.
    $this->query('{ root }');

    // Change the context value to 'b'.
    $contextKeys->willReturn(new ContextCacheKeys(['b']));
    // This will be stored in the cache key for context 'b'.
    $this->query('{ root }');

    // Change the context value back to 'a'.
    $contextKeys->willReturn(new ContextCacheKeys(['a']));
    // This will be retrieved from cache for context 'a'.
    $this->query('{ root }');
  }

  /**
   * Test if results cache properly acts on cache tag clears.
   */
  public function testTags(): void {
    $cacheable = $this->getMockBuilder(CacheableDependencyInterface::class)
      ->onlyMethods(['getCacheTags', 'getCacheMaxAge', 'getCacheContexts'])
      ->getMock();

    $cacheable->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(['a', 'b']);

    $cacheable->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(45);

    $cacheable->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);

    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root',
      $this->builder->compose(
        $this->builder->fromValue($cacheable),
        $this->builder->callback(function () use ($dummy) {
          return $dummy->id();
        })
      )
    );

    // First call that will be cached.
    $this->query('{ root }');

    // Invalidate a tag that is part of the result metadata.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['a']);

    // Another call will invoke the processor a second time.
    $this->query('{ root }');

    // Invalidate a tag that is NOT part of the result metadata.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['c']);

    // Result will be served from cache.
    $this->query('{ root }');
  }

  /**
   * Test behavior in case of leaking cache metadata.
   *
   * Intentionally emit undeclared cache metadata as side effect of field
   * resolvers. Should still be added to the processors result.
   */
  public function testLeakingCacheMetadata(): void {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $this->mockResolver('Query', 'leakA',
      function ($a, $b, $c, $d, FieldContext $field) use ($renderer) {
        $el = [
          '#plain_text' => 'Leak A',
          '#cache' => [
            'tags' => ['a'],
          ],
        ];

        $renderContext = new RenderContext();
        $value = $renderer->executeInRenderContext($renderContext, function () use ($renderer, $el) {
          return $renderer->render($el)->__toString();
        });

        if (!$renderContext->isEmpty()) {
          $field->addCacheableDependency($renderContext->pop());
        }

        return $value;
      }
    );

    $this->mockResolver('Query', 'leakB',
      function ($a, $b, $c, $d, FieldContext $field) use ($renderer) {
        $el = [
          '#plain_text' => 'Leak B',
          '#cache' => [
            'tags' => ['b'],
          ],
        ];

        $renderContext = new RenderContext();
        $value = $renderer->executeInRenderContext($renderContext, function () use ($renderer, $el) {
          return $renderer->render($el)->__toString();
        });

        if (!$renderContext->isEmpty()) {
          $field->addCacheableDependency($renderContext->pop());
        }

        return new Deferred(function () use ($value) {
          return $value;
        });
      }
    );

    $query = <<<GQL
      query {
        leakA
        leakB
      }
GQL;

    $metadata = $this->defaultCacheMetaData()
      ->addCacheTags(['a', 'b']);

    $this->assertResults($query, [], [
      'leakA' => 'Leak A',
      'leakB' => 'Leak B',
    ], $metadata);

    $result = $this->query($query);
    $this->assertEquals(200, $result->getStatusCode());
  }

  /**
   * Ensure that a different operation name does not get a cached result.
   */
  public function testOperationNameCaching(): void {
    $dummy = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    // The dataproducer should be called twice because 2 differently named
    // queries are not cached.
    $dummy->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    // Use the same resolver for both fields.
    foreach (['root', 'leakA'] as $field_name) {
      $this->mockResolver('Query', $field_name,
        function () use ($dummy) {
          return $dummy->id();
        }
      );
    }

    // First call is uncached.
    $this->query('query one { root } query two { leakA }', NULL, [], [], FALSE, Request::METHOD_GET, 'one');
    // Second call is uncached.
    $this->query('query one { root } query two { leakA }', NULL, [], [], FALSE, Request::METHOD_GET, 'two');
  }

}
