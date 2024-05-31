<?php

namespace Drupal\graphql\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manager that collects and exposes GraphQL schema extension plugins.
 *
 * @package Drupal\graphql\Plugin
 *
 * @codeCoverageIgnore
 */
class SchemaExtensionPluginManager extends DefaultPluginManager {

  /**
   * Static cache of plugin instances per schema plugin.
   *
   * @var \Drupal\graphql\Plugin\SchemaExtensionPluginInterface[][]
   */
  protected $extensions;

  /**
   * SchemaExtensionPluginManager constructor.
   *
   * @param bool|string $pluginSubdirectory
   *   The plugin's subdirectory.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param string|null $pluginInterface
   *   The interface each plugin should implement.
   * @param string $pluginAnnotationName
   *   The name of the annotation that contains the plugin definition.
   * @param array $config
   *   The configuration service parameter.
   */
  public function __construct(
    $pluginSubdirectory,
    \Traversable $namespaces,
    ModuleHandlerInterface $moduleHandler,
    CacheBackendInterface $cacheBackend,
    $pluginInterface,
    $pluginAnnotationName,
    array $config,
  ) {
    parent::__construct(
      $pluginSubdirectory,
      $namespaces,
      $moduleHandler,
      $pluginInterface,
      $pluginAnnotationName
    );

    $this->alterInfo('graphql_schema_extension');
    $this->useCaches(empty($config['development']));
    $this->setCacheBackend($cacheBackend, 'graphql_schema_extension', ['graphql_schema_extension']);
  }

  /**
   * Retrieves the schema extension plugin instances for a given schema plugin.
   *
   * @param string $id
   *   The id of the schema plugin to retrieve the extensions for.
   *
   * @return \Drupal\graphql\Plugin\SchemaExtensionPluginInterface[]
   */
  public function getExtensions($id) {
    if (!isset($this->extensions[$id])) {
      $this->extensions[$id] = array_map(function ($definition) {
        return $this->createInstance($definition['id']);
      }, array_filter($this->getDefinitions(), function ($definition) use ($id) {
        return $definition['schema'] === $id;
      }));
    }

    return $this->extensions[$id];
  }

}
