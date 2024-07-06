<?php

namespace Drupal\Tests\graphql\Kernel;

use Drupal\graphql\GraphQL\ResolverRegistry;

/**
 * Tests that the resolver registry behaves correctly.
 *
 * @coversDefaultClass \Drupal\graphql\GraphQL\ResolverRegistry
 *
 * @group graphql
 */
class ResolverRegistryTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // cspell:ignore Cabrio cabrio
    $schema = <<<GQL
      type Query {
        transportation: Vehicle
      }

      interface Vehicle {
        model: String
      }

      interface Car implements Vehicle {
        model: String
      }

      type Cabrio implements Car {
        model: String
      }
GQL;

    $this->setUpSchema($schema);

    // Our mocking trait sets up the ResolverRegistry that we're interested in
    // testing for us. This assertion guards against the mock implementation
    // invalidating that and this test becoming useless.
    self::assertInstanceOf(ResolverRegistry::class, $this->registry);
  }

  /**
   * @covers ::getAllFieldResolvers
   */
  public function testGetAllFieldResolvers() : void {
    $transportation_resolver = $this->builder->fromValue('Ford Model T');
    $this->mockResolver('Query', 'transportation', $transportation_resolver);
    $car_resolver = $this->builder->fromParent();
    $this->mockResolver('Car', 'model', $car_resolver);
    $cabrio_resolver = $this->builder->fromValue('Cabrio');
    $this->mockResolver('Cabrio', 'model', $cabrio_resolver);

    self::assertEquals(
      [
        'Query' => ['transportation' => $transportation_resolver],
        'Car' => ['model' => $car_resolver],
        'Cabrio' => ['model' => $cabrio_resolver],
      ],
      $this->registry->getAllFieldResolvers()
    );

  }

  /**
   * @covers ::getFieldResolverWithInheritance
   */
  public function testGetFieldResolverWithInheritanceTraversesSingleInheritance() : void {
    $expected_resolver = $this->builder->fromValue('Car');
    $this->mockResolver('Car', 'model', $expected_resolver);

    $returned_resolver = $this->registry->getFieldResolverWithInheritance(
      $this->schema->getSchema($this->registry)->getType('Cabrio'),
      'model'
    );

    self::assertEquals(
      $expected_resolver,
      $returned_resolver
    );
  }

  /**
   * @covers ::getFieldResolverWithInheritance
   */
  public function testGetFieldResolverWithInheritanceTraversesMultipleInheritance() : void {
    $expected_resolver = $this->builder->fromValue('Vehicle');
    $this->mockResolver('Vehicle', 'model', $expected_resolver);

    $returned_resolver = $this->registry->getFieldResolverWithInheritance(
      $this->schema->getSchema($this->registry)->getType('Cabrio'),
      'model'
    );

    self::assertEquals(
      $expected_resolver,
      $returned_resolver
    );
  }

  /**
   * @covers ::getFieldResolverWithInheritance
   */
  public function testGetFieldResolverWithInheritanceGivesPrecedenceToType() : void {
    $this->mockResolver('Vehicle', 'model', $this->builder->fromValue('Vehicle'));
    $expected_resolver = $this->builder->fromValue('Cabrio');
    $this->mockResolver('Cabrio', 'model', $expected_resolver);

    $returned_resolver = $this->registry->getFieldResolverWithInheritance(
      $this->schema->getSchema($this->registry)->getType('Cabrio'),
      'model'
    );

    self::assertEquals(
      $expected_resolver,
      $returned_resolver
    );
  }

}
