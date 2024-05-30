<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Context default value test.
 *
 * @group graphql
 */
class DefaultValueTest extends GraphQLTestBase {

  /**
   * Test that the entity_load data producer has the correct default values.
   */
  public function testEntityLoadDefaultValue(): void {
    $manager = $this->container->get('plugin.manager.graphql.data_producer');
    $plugin = $manager->createInstance('entity_load');
    // Only type is required.
    $plugin->setContextValue('type', 'node');
    $context_values = $plugin->getContextValuesWithDefaults();
    $this->assertTrue($context_values['access']);
    $this->assertSame('view', $context_values['access_operation']);
  }

}
