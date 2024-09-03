<?php

namespace Drupal\graphql\GraphQL\Execution;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to make our GraphQL executor, can be swapped out.
 */
class ExecutorFactory {

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * ExecutorFactory constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * Factory method to make a new executor.
   *
   * @param \GraphQL\Executor\Promise\PromiseAdapter $adapter
   * @param \GraphQL\Type\Schema $schema
   * @param \GraphQL\Language\AST\DocumentNode $document
   * @param mixed $root
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param mixed $variables
   * @param string $operation
   * @param callable $resolver
   *
   * @return \Drupal\graphql\GraphQL\Execution\Executor
   */
  public function create(
    PromiseAdapter $adapter,
    Schema $schema,
    DocumentNode $document,
    $root,
    ResolveContext $context,
    $variables,
    $operation,
    callable $resolver,
  ) {
    return Executor::create($this->container,
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

}
