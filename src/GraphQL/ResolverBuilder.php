<?php

namespace Drupal\graphql\GraphQL;

use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\graphql\GraphQL\Resolver\Argument;
use Drupal\graphql\GraphQL\Resolver\Callback;
use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\Resolver\Condition;
use Drupal\graphql\GraphQL\Resolver\Context;
use Drupal\graphql\GraphQL\Resolver\DefaultValue;
use Drupal\graphql\GraphQL\Resolver\Map;
use Drupal\graphql\GraphQL\Resolver\ParentValue;
use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use Drupal\graphql\GraphQL\Resolver\SourceContext;
use Drupal\graphql\GraphQL\Resolver\Tap;
use Drupal\graphql\GraphQL\Resolver\Value;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy;
use Drupal\typed_data\DataFetcherTrait;

/**
 * Wires and maps different resolvers together to build the GraphQL tree.
 */
class ResolverBuilder {
  use TypedDataTrait;
  use DataFetcherTrait;

  /**
   * Instantiate a data producer proxy to lazy resolve a data producer plugin.
   *
   * @param string $id
   * @param array $config
   *
   * @return \Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy
   */
  public function produce($id, array $config = []) {
    return DataProducerProxy::create($id, $config);
  }

  /**
   * Combine multiple resolvers in a chain resolving after each other.
   *
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface ...$resolvers
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Composite
   */
  public function compose(ResolverInterface ...$resolvers) {
    return new Composite($resolvers);
  }

  /**
   * Register a resolver.
   *
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $callback
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Tap
   */
  public function tap(ResolverInterface $callback) {
    return new Tap($callback);
  }

  /**
   * Register a resolver for multiple items.
   *
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $callback
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Map
   */
  public function map(ResolverInterface $callback) {
    return new Map($callback);
  }

  /**
   * Register a callback as resolver.
   *
   * @param callable $callback
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Callback
   */
  public function callback(callable $callback) {
    return new Callback($callback);
  }

  /**
   * Add a context that is available for further resolvers.
   *
   * @param string $name
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $source
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Tap
   */
  public function context($name, ResolverInterface $source = NULL) {
    $callback = new SourceContext($name, $source);
    return $this->tap($callback);
  }

  /**
   * Add condition branches to resolve.
   *
   * @param array $branches
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Condition
   */
  public function cond(array $branches) {
    return new Condition($branches);
  }

  /**
   * Add a property path resolver.
   *
   * @param string $type
   * @param string $path
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $value
   *
   * @return \Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy
   */
  public function fromPath($type, $path, ResolverInterface $value = NULL) {
    return $this->produce('property_path')
      ->map('type', $this->fromValue($type))
      ->map('path', $this->fromValue($path))
      ->map('value', $value ?: $this->fromParent());
  }

  /**
   * Adds a fixed value to resolve to.
   *
   * @param mixed $value
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Value
   */
  public function fromValue($value) {
    return new Value($value);
  }

  /**
   * Adds a query argument value to resolve to.
   *
   * @param string $name
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Argument
   */
  public function fromArgument($name) {
    return new Argument($name);
  }

  /**
   * Resolves the current value that will be a parent for the field.
   *
   * @return \Drupal\graphql\GraphQL\Resolver\ParentValue
   */
  public function fromParent() {
    return new ParentValue();
  }

  /**
   * Resolves a value from the context by context name.
   *
   * @param string $name
   * @param callable|null $default
   *
   * @return \Drupal\graphql\GraphQL\Resolver\Context
   */
  public function fromContext($name, $default = NULL) {
    return new Context($name, $default);
  }

  /**
   * Adds a default value resolver.
   *
   * @param mixed $value
   * @param mixed $default
   *
   * @return \Drupal\graphql\GraphQL\Resolver\DefaultValue
   */
  public function defaultValue($value, $default) {
    return new DefaultValue($value, $default);
  }

}
