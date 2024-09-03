<?php

namespace Drupal\Tests\graphql\Kernel\Schema;

use Drupal\graphql\Plugin\GraphQL\Schema\AlterableComposableSchema;

/**
 * Extends alterable schema and provides base methods to pass tests.
 *
 * @Schema(
 *   id = "alterable_composable_test",
 *   name = "Alterable composable test schema"
 * )
 */
class AlterableComposableTestSchema extends AlterableComposableSchema {

  /**
   * {@inheritdoc}
   *
   * Just copy from the base class in order to pass tests.
   *
   * @see \Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase
   */
  protected function getExtensions() {
    return $this->extensionManager->getExtensions($this->getPluginId());
  }

}
