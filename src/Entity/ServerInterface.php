<?php

namespace Drupal\graphql\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\graphql\Plugin\PersistedQueryPluginInterface;
use GraphQL\Server\OperationParams;

/**
 * Defines a GraphQL server that has configuration and executes queries.
 */
interface ServerInterface extends ConfigEntityInterface {

  /**
   * Execute an operation on this server.
   *
   * @param \GraphQL\Server\OperationParams $operation
   *
   * @return \Drupal\graphql\GraphQL\Execution\ExecutionResult
   */
  public function executeOperation(OperationParams $operation);

  /**
   * Execute multiple operations as batch on this server.
   *
   * @param \GraphQL\Server\OperationParams[] $operations
   *
   * @return \Drupal\graphql\GraphQL\Execution\ExecutionResult[]
   */
  public function executeBatch(array $operations);

  /**
   * Retrieves the server configuration.
   *
   * @return \GraphQL\Server\ServerConfig
   *   The server configuration.
   */
  public function configuration();

  /**
   * Adds a Persisted Query plugin instance to the persisted queries set.
   *
   * @param \Drupal\graphql\Plugin\PersistedQueryPluginInterface $queryPlugin
   */
  public function addPersistedQueryInstance(PersistedQueryPluginInterface $queryPlugin);

  /**
   * Removes a Persisted Query plugin instance from the persisted queries set.
   *
   * @param string $queryPluginId
   *   The plugin id to be removed.
   */
  public function removePersistedQueryInstance($queryPluginId);

  /**
   * Removes all the persisted query instances.
   */
  public function removeAllPersistedQueryInstances();

  /**
   * Returns the current persisted queries set.
   *
   * @return \Drupal\graphql\Plugin\PersistedQueryPluginInterface[]|null
   */
  public function getPersistedQueryInstances();

  /**
   * Returns the current persisted queries set, sorted by the plugins weight.
   *
   * @return \Drupal\graphql\Plugin\PersistedQueryPluginInterface[]
   */
  public function getSortedPersistedQueryInstances();

}
