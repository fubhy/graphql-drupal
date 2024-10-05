<?php

namespace Drupal\Tests\graphql\Traits;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\GraphQL\Execution\ExecutionResult;
use GraphQL\Server\OperationParams;

/**
 * Trait for easier assertion on GraphQL query results.
 */
trait QueryResultAssertionTrait {

  /**
   * @var \Drupal\graphql\Entity\ServerInterface
   */
  protected $server;

  /**
   * @var \Drupal\Core\Render\RendererInterface|null
   */
  protected $renderer;

  /**
   * Return the default cache max age for this test case.
   *
   * @return int
   *   The default max age value.
   */
  abstract protected function defaultCacheMaxAge();

  /**
   * Return the default cache cache tags for this test case.
   *
   * @return string[]
   *   The default cache tags.
   */
  abstract protected function defaultCacheTags();

  /**
   * Return the default cache contexts for this test case.
   *
   * @return string[]
   *   The default cache contexts.
   */
  abstract protected function defaultCacheContexts();

  /**
   * The default cache metadata object.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cache metadata object.
   */
  protected function defaultCacheMetaData() {
    $metadata = new CacheableMetadata();
    $metadata->setCacheMaxAge($this->defaultCacheMaxAge());
    $metadata->setCacheTags($this->defaultCacheTags());
    $metadata->setCacheContexts($this->defaultCacheContexts());
    return $metadata;
  }

  /**
   * The default mutation cache metadata object.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cache metadata object.
   */
  protected function defaultMutationCacheMetaData() {
    $metadata = new CacheableMetadata();
    $metadata->setCacheMaxAge(0);
    $metadata->setCacheTags($this->defaultCacheTags());
    $metadata->setCacheContexts($this->defaultCacheContexts());
    return $metadata;
  }

  /**
   * Assert a result for a graphql query and variables.
   *
   * @param string $query
   *   The query string.
   * @param array $variables
   *   The query variables.
   * @param array $expected
   *   The expected result.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $metadata
   *   The expected cache metadata object.
   */
  protected function assertResults($query, array $variables, array $expected, ?CacheableMetadata $metadata = NULL): void {
    $context = new RenderContext();
    $result = $this->getRenderer()->executeInRenderContext(
      $context,
      function () use ($query, $variables) {
        return $this->server->executeOperation(
          OperationParams::create([
            'query' => $query,
            'variables' => $variables,
          ])
        );
      }
    );

    $this->assertResultErrors($result, []);
    $this->assertResultData($result, $expected);
    $this->assertResultMetadata($result, $metadata ?: $this->defaultCacheMetaData());
    self::assertTrue($context->isEmpty(), "Metadata was leaked during operation execution: {$context->serialize()}");
  }

  /**
   * Assert a query result with certain errors.
   *
   * @param string $query
   *   The query string.
   * @param array $variables
   *   The query variables.
   * @param mixed $expected
   *   The expected error messages.
   * @param \Drupal\Core\Cache\CacheableMetadata $metadata
   *   The expected cache metadata object.
   */
  protected function assertErrors($query, array $variables, $expected, CacheableMetadata $metadata): void {
    $context = new RenderContext();
    $result = $this->getRenderer()->executeInRenderContext(
      $context,
      function () use ($query, $variables) {
        return $this->server->executeOperation(
          OperationParams::create([
            'query' => $query,
            'variables' => $variables,
          ])
        );
      }
    );

    $this->assertResultErrors($result, $expected);
    $this->assertResultMetadata($result, $metadata);
    self::assertTrue($context->isEmpty(), "Metadata was leaked during operation execution: {$context->serialize()}");
  }

  /**
   * Assert a certain result data set on a query result.
   *
   * @param \Drupal\graphql\GraphQL\Execution\ExecutionResult $result
   *   The query result object.
   * @param mixed $expected
   *   The expected result data set.
   *
   * @internal
   */
  private function assertResultData(ExecutionResult $result, $expected): void {
    $data = $result->toArray();
    $this->assertArrayHasKey('data', $data, 'No result data.');
    $this->assertEquals($expected, $data['data'], 'Unexpected query result.');
  }

  /**
   * Assert that the result contains contains a certain set of errors.
   *
   * @param \Drupal\graphql\GraphQL\Execution\ExecutionResult $result
   *   The query result object.
   * @param array $expected
   *   The list of expected error messages. Also allows regular expressions.
   *
   * @internal
   */
  private function assertResultErrors(ExecutionResult $result, array $expected): void {
    // Initialize the status.
    $unexpected = [];
    $matchCount = array_fill_keys($expected, 0);

    // Iterate through error messages.
    // Collect unmatched errors and count pattern hits.
    foreach ($result->errors as $error) {
      $error_message = $error->getMessage();
      $match = FALSE;
      foreach ($expected as $pattern) {
        if (@preg_match($pattern, $error_message) === FALSE) {
          $match = $match || $pattern == $error_message;
          $matchCount[$pattern]++;
        }
        else {
          $match = $match || preg_match($pattern, $error_message);
          $matchCount[$pattern]++;
        }
      }

      if (!$match) {
        // Add error location information of the original error in the chain to
        // show developers where to look.
        $original_error = $error;
        while ($original_error->getPrevious() !== NULL) {
          $original_error = $original_error->getPrevious();
        }
        $unexpected[] = "Error message: {$error_message}\n  Originated in: {$original_error->getFile()}:{$original_error->getLine()}";
      }
    }

    // Create a list of patterns that never matched.
    $missing = array_keys(array_filter($matchCount, function ($count) {
      return $count == 0;
    }));

    self::assertEmpty($missing, "Missing errors:\n* " . implode("\n* ", $missing));
    self::assertEmpty($unexpected, "Unexpected errors:\n* " . implode("\n* ", $unexpected));
  }

  /**
   * Assert a certain set of result metadata on a query result.
   *
   * @param \Drupal\graphql\GraphQL\Execution\ExecutionResult $result
   *   The query result object.
   * @param \Drupal\Core\Cache\CacheableMetadata $expected
   *   The expected metadata object.
   *
   * @internal
   */
  private function assertResultMetadata(ExecutionResult $result, CacheableMetadata $expected): void {
    $this->assertEquals($expected->getCacheMaxAge(), $result->getCacheMaxAge(), 'Unexpected cache max age.');

    $missingContexts = array_diff($expected->getCacheContexts(), $result->getCacheContexts());
    $this->assertEmpty($missingContexts, 'Missing cache contexts: ' . implode(', ', $missingContexts));

    $unexpectedContexts = array_diff($result->getCacheContexts(), $expected->getCacheContexts());
    $this->assertEmpty($unexpectedContexts, 'Unexpected cache contexts: ' . implode(', ', $unexpectedContexts));

    $missingTags = array_diff($expected->getCacheTags(), $result->getCacheTags());
    $this->assertEmpty($missingTags, 'Missing cache tags: ' . implode(', ', $missingTags));

    $unexpectedTags = array_diff($result->getCacheTags(), $expected->getCacheTags());
    $this->assertEmpty($unexpectedTags, 'Unexpected cache tags: ' . implode(', ', $unexpectedTags));
  }

  /**
   * Get the Drupal renderer.
   *
   * Uses either the renderer available in the test class or fetches the Drupal
   * renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer service for the test.
   */
  private function getRenderer() : RendererInterface {
    if (!isset($this->renderer)) {
      $this->renderer = \Drupal::service('renderer');
    }
    return $this->renderer;
  }

}
