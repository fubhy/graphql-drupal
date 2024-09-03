<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\EntityDefinition\Fields;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Retrieves the "hidden" property from a given field definition.
 *
 * @DataProducer(
 *   id = "entity_definition_field_hidden",
 *   name = @Translation("Entity definition field hidden"),
 *   description = @Translation("Return entity definition field hidden."),
 *   consumes = {
 *     "entity_definition_field" = @ContextDefinition("any",
 *       label = @Translation("Entity definition field")
 *     ),
 *     "entity_form_display_context" = @ContextDefinition("any",
 *       label = @Translation("Entity form display context"),
 *       required = FALSE,
 *     )
 *   },
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Entity definition field hidden")
 *   )
 * )
 */
class Hidden extends DataProducerPluginBase {

  /**
   * Resolves the hidden property.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $entity_definition_field
   *   The entity field definition.
   * @param \Drupal\Core\Entity\Entity\EntityFormDisplay|null $entity_form_display_context
   *   Entity form display context.
   *
   * @return bool
   *   If the field is hidden or not.
   */
  public function resolve(
    FieldDefinitionInterface $entity_definition_field,
    ?EntityFormDisplay $entity_form_display_context,
  ): bool {
    if ($entity_form_display_context) {
      $hidden = $entity_form_display_context->get('hidden');
      $field_id = $entity_definition_field->getName();

      if (isset($hidden[$field_id])) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

}
