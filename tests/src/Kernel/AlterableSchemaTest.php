<?php

namespace Drupal\Tests\graphql\Kernel;

use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql\Plugin\SchemaExtensionPluginManager;
use Drupal\Tests\graphql\Kernel\Schema\AlterableComposableTestSchema;

/**
 * Tests the alterable schema.
 *
 * @group graphql
 */
class AlterableSchemaTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'graphql_alterable_schema_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $schema = <<<GQL
      schema {
        query: Query
      }
      type Query {
        alterableQuery(id: Int): Result
      }
      type Result {
        id: Int
      }
    GQL;

    $this->setUpSchema($schema);
    $this->mockResolver('Query', 'alterableQuery', function () {
      return ['id' => 1];
    });
  }

  /**
   * Test if schema altering data is working and arg id is required.
   */
  public function testSchemaAlteredQueryArgumentToRequired(): void {
    $result = $this->query('query { alterableQuery { id } }');
    $this->assertSame(200, $result->getStatusCode());
    // Here should be error that query argument data is required.
    $this->assertSame([
      'errors' => [
        0 => [
          'message' => 'Field "alterableQuery" argument "id" of type "Int!" is required but not provided.',
          'extensions' => [
            'category' => 'graphql',
          ],
          'locations' => [
            0 => [
              'line' => 1,
              'column' => 9,
            ],
          ],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));
  }

  /**
   * Test if schema extension altering is working and arg position is non-null.
   */
  public function testSchemaExtensionAlteredQueryResultPropertyToNonNull(): void {
    $result = $this->query('query { alterableQuery(id: 1) { id, position } }');
    $this->assertSame(200, $result->getStatusCode());
    // Here should be error that query result position variable cannot be null.
    // This leads to the internal server error with reference to the variable.
    $this->assertSame([
      'errors' => [
        0 => [
          'message' => 'Internal server error',
          'extensions' => [
            'category' => 'internal',
          ],
          'locations' => [
            0 => [
              'line' => 1,
              'column' => 37,
            ],
          ],
          'path' => [
            'alterableQuery',
            // Reference to our variable in the error.
            'position',
          ],
        ],
      ],
      'data' => [
        'alterableQuery' => NULL,
      ],
    ], json_decode($result->getContent(), TRUE));
  }

  /**
   * Test if schema extension altering is working with empty extensions.
   */
  public function testEmptySchemaExtensionAlteredQueryResultPropertyAdded(): void {
    $result = $this->query('query { alterableQuery(id: 1) { id, empty } }');
    $this->assertSame(200, $result->getStatusCode());
    // Here should be error that query result empty variable cannot be null.
    // This leads to the internal server error with reference to the variable.
    $this->assertSame([
      'errors' => [
        0 => [
          'message' => 'Internal server error',
          'extensions' => [
            'category' => 'internal',
          ],
          'locations' => [
            0 => [
              'line' => 1,
              'column' => 37,
            ],
          ],
          'path' => [
            'alterableQuery',
            // Reference to our variable in the error.
            'empty',
          ],
        ],
      ],
      'data' => [
        'alterableQuery' => NULL,
      ],
    ], json_decode($result->getContent(), TRUE));
  }

  /**
   * {@inheritdoc}
   */
  protected function mockSchema($id, $schema, array $extensions = []): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $extensionManager */
    $extensionManager = $this->getMockBuilder(SchemaExtensionPluginManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getExtensions'])
      ->getMock();

    // Adds extra extension in order to test alter extension data event.
    $extensions['graphql_alterable_schema_test'] = $this->getMockBuilder(SdlSchemaExtensionPluginBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getBaseDefinition', 'getExtensionDefinition'])
      ->getMockForAbstractClass();

    $extensions['graphql_alterable_schema_test']->expects(static::any())
      ->method('getBaseDefinition')
      ->willReturn('');

    // Different extension definition for different tests.
    switch ($this->getName()) {
      case 'testEmptySchemaExtensionAlteredQueryResultPropertyAdded':
        $extensionDefinition = '';
        break;

      default:
        $extensionDefinition = <<<GQL
          extend type Result {
            position: Int
          }
        GQL;
        break;
    }

    $extensions['graphql_alterable_schema_test']->expects(static::any())
      ->method('getExtensionDefinition')
      ->willReturn($extensionDefinition);

    $extensionManager->expects(static::any())
      ->method('getExtensions')
      ->willReturn($extensions);

    // Replace mock schema with our own implementation.
    $this->schema = $this->getMockBuilder(AlterableComposableTestSchema::class)
      ->setConstructorArgs([
        [],
        $id,
        [],
        $this->container->get('cache.graphql.ast'),
        $this->container->get('module_handler'),
        $extensionManager,
        ['development' => FALSE],
        $this->container->get('event_dispatcher'),
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

}
