<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\EntityDefinition;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets entity definition for a given entity type.
 *
 * @DataProducer(
 *   id = "entity_definition",
 *   name = @Translation("Entity definition"),
 *   description = @Translation("Return entity definitions for given entity type."),
 *   consumes = {
 *     "entity_type" = @ContextDefinition("string",
 *       label = @Translation("Entity type")
 *     ),
 *     "bundle" = @ContextDefinition("string",
 *       label = @Translation("Bundle"),
 *       required = FALSE
 *     ),
 *     "field_types" = @ContextDefinition("string",
 *       label = @Translation("Field types (ALL, BASE_FIELDS, FIELD_CONFIG)"),
 *       default = "ALL",
 *       required = FALSE
 *     )
 *   },
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Entity definition")
 *   )
 * )
 */
class EntityDefinition extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * EntityLoad constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition array.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    EntityTypeManager $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Resolves entity definition for a given entity type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string|null $bundle
   *   Optional. The entity bundle which are stored as a context for upcoming
   *   data producers deeper in hierarchy.
   * @param string|null $field_types
   *   Optional. The field types to retrieve (base fields, configurable fields,
   *   or both) which are stored as a context for upcoming data producers deeper
   *   in hierarchy.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field_context
   *   Field context.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity definition.
   */
  public function resolve(
    string $entity_type,
    ?string $bundle,
    ?string $field_types,
    FieldContext $field_context,
  ): EntityTypeInterface {
    if ($bundle) {
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
      if (isset($bundle_info[$bundle])) {
        $bundle_context = $bundle_info[$bundle];
        $bundle_context['key'] = $bundle;
        $field_context->setContextValue('bundle', $bundle_context);
      }
    }

    if ($field_types) {
      $field_context->setContextValue('field_types', $field_types);
    }

    return $this->entityTypeManager->getDefinition($entity_type);
  }

}
