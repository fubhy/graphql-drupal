<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\graphql\Entity\Server;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test disabled result cache.
 *
 * @group graphql
 */
class DisabledResultCacheTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $schema = <<<GQL
      type Query {
        root: String
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Set the development parameter to TRUE.
    $parameters = $container->getParameter('graphql.config');
    $parameters['development'] = TRUE;
    $container->setParameter('graphql.config', $parameters);
  }

  /**
   * Test if disabling the result cache has the desired effect.
   */
  public function testDisabledCache(): void {
    $this->createTestServer('test', '/graphql/uncached', [
      'caching' => FALSE,
    ]);

    $object = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id'])
      ->getMock();

    $object->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root', function () use ($object) {
      return $object->id();
    });

    // The first request that is not supposed to be cached.
    $this->query('{ root }');

    // This should invoke the processor a second time.
    $this->query('{ root }');
  }

}
