<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns the URL of an entity.
 *
 * @DataProducer(
 *   id = "entity_url",
 *   name = @Translation("Entity url"),
 *   description = @Translation("Returns the entity's url."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Url")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     ),
 *     "rel" = @ContextDefinition("string",
 *       label = @Translation("Relationship type"),
 *       description = @Translation("The relationship type, e.g. canonical"),
 *       required = FALSE
 *     ),
 *     "options" = @ContextDefinition("any",
 *       label = @Translation("URL Options"),
 *       description = @Translation("Options to pass to the toUrl call"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class EntityUrl extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to create a canonical URL for.
   * @param string|null $rel
   *   The link relationship type, for example: canonical or edit-form.
   * @param array|null $options
   *   The options to provided to the URL generator.
   *
   * @return \Drupal\Core\Url
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function resolve(EntityInterface $entity, ?string $rel, ?array $options) {
    return $entity->toUrl($rel ?? 'canonical', $options ?? []);
  }

}
