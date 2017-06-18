<?php

namespace Drupal\graphql_content\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for GraphQL Field Plugin derivers.
 */
abstract class FieldDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity display storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $displayStorage;

  /**
   * Bundle info provider.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Tells if given field is supported by the plugin.
   *
   * @param array $displaySettings
   *   Array of display settings.
   *
   * @return bool
   */
  protected abstract function isFieldSupported($displaySettings);

  /**
   * DisplayedFieldDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager instance.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info provider.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $bundleInfo,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->bundleInfo = $bundleInfo;
    $this->displayStorage = $entityTypeManager->getStorage('entity_view_display');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Retrieve the GraphQL display for a certain content entity bundle.
   *
   * @param string $entityType
   *   The entity type id.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The view display object.
   */
  protected function getDisplay($entityType, $bundle) {
    /** @var EntityViewDisplayInterface $display */
    $display = $this->displayStorage
      ->load(implode('.', [$entityType, $bundle, 'graphql']));
    if (!$display || !$display->status()) {
      $display = $this->displayStorage
        ->load(implode('.', [$entityType, $bundle, 'default']));
    }
    return $display instanceof EntityViewDisplayInterface ? $display : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    $this->derivatives = [];
    $bundles = $this->bundleInfo->getAllBundleInfo();

    foreach ($this->entityTypeManager->getDefinitions() as $typeId => $type) {
      if (!($type instanceof ContentEntityTypeInterface) || !array_key_exists($typeId, $bundles)) {
        continue;
      }

      $storages = $this->entityFieldManager->getFieldStorageDefinitions($typeId);

      foreach (array_keys($bundles[$typeId]) as $bundle) {
        if ($display = $this->getDisplay($typeId, $bundle)) {
          foreach ($display->getComponents() as $field => $displaySettings) {
            if ($this->isFieldSupported($displaySettings)) {
              $definition = [
                'name' => graphql_core_propcase($field),
                'types' => [graphql_core_camelcase([$typeId, $bundle])],
                'entity_type' => $typeId,
                'bundle' => $bundle,
                'field' => $field,
                'virtual' => !array_key_exists($field, $storages),
                'multi' => array_key_exists($field, $storages) ? $storages[$field]->getCardinality() != 1 : FALSE,
                'cache_tags' => $display->getCacheTags(),
                'cache_contexts' => $display->getCacheContexts(),
                'cache_max_age' => $display->getCacheMaxAge(),
              ] + $basePluginDefinition;

              $this->getFieldPluginDefinition($definition, $type, $bundle, $field);
            }
          }
        }
      }
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

  /**
   * Builds plugin definition for field and stores it in $this->derivatives.
   *
   * @param array $basePluginDefinition
   *   Base definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $type
   *   Entity type.
   * @param string $bundle
   *   Bundle id.
   * @param string $field
   *   Field id.
   * @return array
   *   Plugin definition.
   */
  protected function getFieldPluginDefinition($basePluginDefinition, EntityTypeInterface $type, $bundle, $field) {
    $this->derivatives[$type->id() . '-' . $bundle . '-' . $field] = $basePluginDefinition;
  }

}
