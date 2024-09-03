<?php

namespace Drupal\graphql\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use GraphQL\Executor\ExecutionResult;

/**
 * Represents an event that is triggered before and after a GraphQL operation.
 */
class OperationEvent extends Event {

  /**
   * Event fired before an operation is executed.
   *
   * @var string
   */
  const GRAPHQL_OPERATION_BEFORE = 'graphql.operation.before';

  /**
   * Event fired after an operation was executed.
   *
   * @var string
   */
  const GRAPHQL_OPERATION_AFTER = 'graphql.operation.after';

  /**
   * Result of the query execution.
   *
   * @var \GraphQL\Executor\ExecutionResult
   */
  protected $result;

  /**
   * Resolver context used for the query.
   *
   * @var \Drupal\graphql\GraphQL\Execution\ResolveContext
   */
  protected $context;

  /**
   * OperationEvent constructor.
   *
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Executor\ExecutionResult $result
   */
  public function __construct(ResolveContext $context, ExecutionResult $result = NULL) {
    $this->context = $context;
    $this->result = $result;
  }

  /**
   * Returns the execution result.
   *
   * @return \GraphQL\Executor\ExecutionResult
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Returns the resolver context.
   *
   * @return \Drupal\graphql\GraphQL\Execution\ResolveContext
   */
  public function getContext() {
    return $this->context;
  }

}
