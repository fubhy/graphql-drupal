<?php

namespace Drupal\graphql_content\Plugin\Deriver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\graphql\Utility\StringHelper;
use Drupal\graphql_content\Plugin\GraphQL\Types\RawValueFieldType;
use Drupal\graphql_content\TypeMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\graphql_content\ContentEntitySchemaConfig;

class RawValueFieldItemDeriver extends FieldFormatterDeriver {

  /**
   * The type mapper service.
   *
   * @var \Drupal\graphql_content\TypeMapper
   */
  protected $typeMapper;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * The schema configuration service.
   *
   * @var \Drupal\graphql_content\ContentEntitySchemaConfig
   */
  protected $schemaConfig;

  /**
   * RawValueFieldItemDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   An entity type manager instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   An entity field manager instance.
   * @param \Drupal\graphql_content\ContentEntitySchemaConfig $schemaConfig
   *   The schema configuration service.
   * @param \Drupal\graphql_content\TypeMapper $typeMapper
   *   The graphql type mapper service.
   * @param string $basePluginId
   *   The base plugin id.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    ContentEntitySchemaConfig $schemaConfig,
    TypeMapper $typeMapper,
    $basePluginId
  ) {
    parent::__construct($entityTypeManager, $entityFieldManager, $schemaConfig, $basePluginId);
    $this->typeMapper = $typeMapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('graphql_content.schema_config'),
      $container->get('graphql_content.type_mapper'),
      $basePluginId
    );
  }

  protected function getDefinitions($entityType, $bundle, array $displayOptions, FieldStorageDefinitionInterface $storage = NULL) {
    if (!isset($storage)) {
      return NULL;
    }

    $fieldName = $storage->getName();
    $dataType = RawValueFieldType::getId($entityType, $fieldName);

    // Add the subfields, eg. value, summary.
    $definitions = [];

    foreach ($storage->getPropertyDefinitions() as $property => $definition) {
      if ($definition->getDataType() == 'map') {
        continue;
        // TODO Is it possible to get the keys of a map (eg. the options array for link field) here?
      }

      $definitions["$entityType-$fieldName-$property"] = [
        'name' => StringHelper::propCase($property),
        'property' => $property,
        'multi' => FALSE,
        'type' => $this->typeMapper->typedDataToGraphQLFieldType($definition),
        'types' => [$dataType],
      ];
    }

    return $definitions;
  }

}
