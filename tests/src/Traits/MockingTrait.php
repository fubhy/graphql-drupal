<?php

namespace Drupal\Tests\graphql\Traits;

use Drupal\graphql\Entity\Server;
use Drupal\graphql\GraphQL\Resolver\Callback;
use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use Drupal\graphql\GraphQL\Resolver\Value;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;
use Drupal\graphql\Plugin\SchemaExtensionPluginManager;
use Drupal\graphql\Plugin\SchemaPluginManager;
use Drupal\Tests\RandomGeneratorTrait;

/**
 * Contains helpers for setting up mock servers and schemas for testing.
 */
trait MockingTrait {
  use RandomGeneratorTrait;

  /**
   * @var \Drupal\graphql\Entity\ServerInterface
   */
  protected $server;

  /**
   * @var \Drupal\graphql\GraphQL\ResolverRegistry
   */
  protected $registry;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\graphql\Plugin\SchemaPluginInterface
   */
  protected $schema;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\graphql\Plugin\SchemaPluginManager
   */
  protected $schemaPluginManager;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\graphql\Plugin\DataProducerPluginManager
   */
  protected $dataProducerPluginManager;

  /**
   * Turn a value into a result promise.
   *
   * @param mixed $value
   *   The return value. Can also be a value callback.
   *
   * @return \PHPUnit\Framework\MockObject\Stub\ReturnCallback
   *   The return callback promise.
   */
  protected function toPromise($value) {
    return $this->returnCallback(is_callable($value) ? $value : function () use ($value) {
      yield $value;
    });
  }

  /**
   * Turn a value into a bound result promise.
   *
   * @param mixed $value
   *   The return value. Can also be a value callback.
   * @param mixed $scope
   *   The resolver's bound object and class scope.
   *
   * @return \PHPUnit\Framework\MockObject\Stub\ReturnCallback
   *   The return callback promise.
   */
  protected function toBoundPromise($value, $scope) {
    return $this->toPromise(is_callable($value) ? \Closure::bind($value, $scope, $scope) : $value);
  }

  /**
   * Setup server with schema.
   *
   * @param string $schema
   *   GraphQL schema description.
   * @param string $id
   *   Schema id.
   * @param array $values
   */
  protected function setUpSchema($schema, $id = 'test', array $values = []): void {
    $this->mockSchema($id, $schema);
    $this->mockSchemaPluginManager($id);
    $this->createTestServer($id, '/graphql/' . $id, $values);

    $this->schemaPluginManager->method('createInstance')
      ->with($this->equalTo($id))
      ->will($this->returnValue($this->schema));

    $this->container->set('plugin.manager.graphql.schema', $this->schemaPluginManager);
  }

  /**
   * Create test server.
   *
   * @param string $schema
   * @param string $endpoint
   * @param array $values
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTestServer($schema, $endpoint, array $values = []): void {
    $this->server = Server::create([
      'schema' => $schema,
      'name' => $this->randomMachineName(),
      'endpoint' => $endpoint,
    ] + $values);

    $this->server->save();
  }

  /**
   * Mock a schema instance.
   *
   * @param string $id
   *   The schema id.
   * @param string $schema
   *   The schema.
   * @param \Drupal\graphql\Plugin\SchemaExtensionPluginInterface[] $extensions
   *   An array of schema extension plugins.
   */
  protected function mockSchema($id, $schema, array $extensions = []): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $extensionManager */
    $extensionManager = $this->getMockBuilder(SchemaExtensionPluginManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getExtensions'])
      ->getMock();

    $extensionManager->expects(static::any())
      ->method('getExtensions')
      ->willReturn($extensions);

    $this->schema = $this->getMockBuilder(SdlSchemaPluginBase::class)
      ->setConstructorArgs([
        [],
        $id,
        [],
        $this->container->get('cache.graphql.ast'),
        $this->container->get('module_handler'),
        $extensionManager,
        ['development' => FALSE],
      ])
      ->onlyMethods(['getSchemaDefinition', 'getResolverRegistry'])
      ->getMockForAbstractClass();

    $this->schema->expects(static::any())
      ->method('getSchemaDefinition')
      ->willReturn($schema);

    $this->registry = new ResolverRegistry();
    $this->schema->expects($this->any())
      ->method('getResolverRegistry')
      ->willReturn($this->registry);
  }

  /**
   * Mock schema plugin manager.
   *
   * @param string $id
   */
  protected function mockSchemaPluginManager($id): void {
    $this->schemaPluginManager = $this->getMockBuilder(SchemaPluginManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->schemaPluginManager->expects($this->any())
      ->method('getDefinitions')
      ->will($this->returnValue([
        $id => [
          'id' => $id,
          'name' => 'Test schema',
          'provider' => 'graphql',
          'class' => '\Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase',
        ],
      ]));
  }

  /**
   * Mock data producer field.
   *
   * @param string $type
   *   Parent Type.
   * @param string $field
   *   Field name.
   * @param mixed|\Drupal\graphql\GraphQL\Resolver\ResolverInterface $resolver
   *   Resolver.
   */
  protected function mockResolver($type, $field, $resolver = NULL): void {
    if (is_callable($resolver)) {
      $resolver = new Callback($resolver);
    }

    if (!($resolver instanceof ResolverInterface)) {
      $resolver = new Value($resolver);
    }

    $this->registry->addFieldResolver($type, $field, $resolver);
  }

  /**
   * Mock type resolver.
   *
   * @param string $type
   *   Parent Type.
   * @param callable $resolver
   *   Type resolver.
   */
  protected function mockTypeResolver($type, callable $resolver): void {
    $this->registry->addTypeResolver($type, $resolver);
  }

}
