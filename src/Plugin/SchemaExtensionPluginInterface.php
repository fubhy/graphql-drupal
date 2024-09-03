<?php

namespace Drupal\graphql\Plugin;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;

/**
 * Defines plugins that can extend the GraphQL schema definition.
 */
interface SchemaExtensionPluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Registers type and field resolvers in the shared registry.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   */
  public function registerResolvers(ResolverRegistryInterface $registry);

  /**
   * Retrieves the base schema definition.
   *
   * @return string|null
   *   The base schema definition.
   */
  public function getBaseDefinition();

  /**
   * Retrieves the extension schema definition.
   *
   * @return string|null
   *   The extension schema definition.
   */
  public function getExtensionDefinition();

}
