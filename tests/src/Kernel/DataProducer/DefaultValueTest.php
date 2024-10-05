<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\Entity\EntityLoad;
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
   *
   * @dataProvider settingsProvider
   */
  public function testLegacyDefaultValueSetting(bool $populate_setting, string $testClass): void {
    $this->container->get('config.factory')->getEditable('graphql.settings')
      ->set('dataproducer_populate_default_values', $populate_setting)
      ->save();
    $manager = $this->container->get('plugin.manager.graphql.data_producer');

    // Manipulate the plugin definitions to use our test class for entity_load.
    $definitions = $manager->getDefinitions();
    $definitions['entity_load']['class'] = $testClass;
    $reflection = new \ReflectionClass($manager);
    $property = $reflection->getProperty('definitions');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $definitions);

    $this->executeDataProducer('entity_load', ['type' => 'node']);
  }

  /**
   * Data provider for the testLegacyDefaultValueSetting test.
   */
  public static function settingsProvider(): array {
    return [
      [FALSE, TestLegacyEntityLoad::class],
      [TRUE, TestNewEntityLoad::class],
    ];
  }

}

/**
 * Helper class to test the legacy behavior.
 */
class TestLegacyEntityLoad extends EntityLoad {

  /**
   * {@inheritdoc}
   */
  public function resolve($type, $id, ?string $language, ?array $bundles, ?bool $access, ?AccountInterface $accessUser, ?string $accessOperation, FieldContext $context): ?Deferred {
    // Old behavior: no default values applied, so we get NULL here.
    Assert::assertNull($access);
    Assert::assertNull($accessOperation);
    return NULL;
  }

}

/**
 * Helper class to test the new behavior.
 */
class TestNewEntityLoad extends EntityLoad {

  /**
   * {@inheritdoc}
   */
  public function resolve($type, $id, ?string $language, ?array $bundles, ?bool $access, ?AccountInterface $accessUser, ?string $accessOperation, FieldContext $context): ?Deferred {
    // New behavior: default values are applied.
    Assert::assertTrue($access);
    Assert::assertSame('view', $accessOperation);
    return NULL;
  }

}
