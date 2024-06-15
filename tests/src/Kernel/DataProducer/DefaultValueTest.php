<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\Entity\EntityLoad;
use Drupal\media_test_source\Plugin\media\Source\Test;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use GraphQL\Deferred;
use PHPUnit\Framework\Assert;

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

  /**
   * Test that the legacy dataproducer_populate_default_values setting works.
   */
  public function testLegacyDefaultValueSetting(): void {
    $this->container->get('config.factory')->getEditable('graphql.settings')
      ->set('dataproducer_populate_default_values', FALSE)
      ->save();
    $manager = $this->container->get('plugin.manager.graphql.data_producer');

    // Manipulate the plugin definitions to use our test class for entity_load.
    $definitions = $manager->getDefinitions();
    $definitions['entity_load']['class'] = TestLegacyEntityLoad::class;
    $reflection = new \ReflectionClass($manager);
    $property = $reflection->getProperty('definitions');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $definitions);

    $this->executeDataProducer('entity_load', ['type' => 'node']);
  }

}

class TestLegacyEntityLoad extends EntityLoad {
  public function resolve($type, $id, ?string $language, ?array $bundles, ?bool $access, ?AccountInterface $accessUser, ?string $accessOperation, FieldContext $context): ?Deferred {
    Assert::assertNull($access);
    Assert::assertNull($accessOperation);
    return NULL;
  }
}
