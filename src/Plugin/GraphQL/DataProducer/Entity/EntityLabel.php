<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\DataProducerPluginCachingInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns the labels of an entity.
 *
 * @DataProducer(
 *   id = "entity_label",
 *   name = @Translation("Entity label"),
 *   description = @Translation("Returns the entity label."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Label")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     ),
 *     "access" = @ContextDefinition("boolean",
 *       label = @Translation("Check access"),
 *       required = FALSE,
 *       default_value = TRUE
 *     ),
 *     "access_user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       required = FALSE,
 *       default_value = NULL
 *     ),
 *   }
 * )
 */
class EntityLabel extends DataProducerPluginBase implements DataProducerPluginCachingInterface {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param bool $access
   * @param \Drupal\Core\Session\AccountInterface|null $accessUser
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *
   * @return string|null
   */
  public function resolve(EntityInterface $entity, bool $access, ?AccountInterface $accessUser, FieldContext $context) {
    if ($access) {
      /** @var \Drupal\Core\Access\AccessResultInterface $accessResult */
      $accessResult = $entity->access('view label', $accessUser, TRUE);
      $context->addCacheableDependency($accessResult);
      if (!$accessResult->isAllowed()) {
        return NULL;
      }
    }
    return $entity->label();
  }

}
