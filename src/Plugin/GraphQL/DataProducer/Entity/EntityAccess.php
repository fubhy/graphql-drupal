<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns whether the given user has access to the entity.
 *
 * @DataProducer(
 *   id = "entity_access",
 *   name = @Translation("Entity access"),
 *   description = @Translation("Returns whether the given user has entity access."),
 *   produces = @ContextDefinition("boolean",
 *     label = @Translation("Access result")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     ),
 *     "operation" = @ContextDefinition("string",
 *       label = @Translation("Operation"),
 *       required = FALSE
 *     ),
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class EntityAccess extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access for.
   * @param string $operation
   *   The access operation, for example "view".
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account access should be checked for.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The context to add caching information to.
   *
   * @return bool
   *   TRUE when access to the entity is allowed, FALSE otherwise.
   */
  public function resolve(EntityInterface $entity, ?string $operation, ?AccountInterface $user, FieldContext $context) {
    $result = $entity->access($operation ?? 'view', $user, TRUE);
    $context->addCacheableDependency($result);
    return $result->isAllowed();
  }

}
