<?php

namespace Drupal\Tests\graphql\Kernel;

use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use GraphQL\Deferred;

/**
 * Tests that the resolver builder behaves correctly.
 *
 * @coversDefaultClass \Drupal\graphql\GraphQL\ResolverBuilder
 *
 * @group graphql
 */
class ResolverBuilderTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['graphql_resolver_builder_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $schema = <<<GQL
      type Query {
        me: String
        tree(id: Int): Tree
      }

      type Tree {
        id(someArg: Int): Int
        name: String
        uri: String
        context: Context
      }

      type Context {
        myContext: String
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * @covers ::produce
   *
   * @dataProvider builderProducingProvider
   *
   * @param string $input
   * @param string $expected
   */
  public function testBuilderProducing($input, $expected): void {
    $plugin = $this->builder->produce($input, []);
    $this->assertInstanceOf($expected, $plugin);
  }

  /**
   * @return array
   */
  public static function builderProducingProvider(): array {
    return [
      ['entity_load', ResolverInterface::class],
      ['entity_id', ResolverInterface::class],
      ['uppercase', ResolverInterface::class],
    ];
  }

  /**
   * @covers ::fromValue
   */
  public function testFromValue(): void {
    $this->mockResolver('Query', 'me', $this->builder->fromValue('some me'));

    $query = <<<GQL
      query {
        me
      }
GQL;

    $this->assertResults($query, [], ['me' => 'some me']);
  }

  /**
   * @covers ::fromParent
   */
  public function testFromParent(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue('Some string value'));
    $this->mockResolver('Tree', 'name', $this->builder->fromParent());

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => 'Some string value']]);
  }

  /**
   * @covers ::fromArgument
   */
  public function testFromArgument(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue([
      'name' => 'some tree',
      'id' => 5,
    ]));
    $this->mockResolver('Tree', 'id', $this->builder->fromArgument('someArg'));

    $query = <<<GQL
      query {
        tree(id: 5) {
          id(someArg: 234)
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['id' => 234]]);
  }

  /**
   * @covers ::fromPath
   */
  public function testFromPath(): void {
    $manager = $this->container->get('typed_data_manager');
    $tree_definition = $manager->createDataDefinition('tree');
    /** @var \Drupal\graphql_resolver_builder_test\Plugin\DataType\Tree $right */
    $right = $manager->create($tree_definition);
    $right->set('value', 'Front page');
    /** @var \Drupal\graphql_resolver_builder_test\Plugin\DataType\Tree $tree */
    $tree = $manager->create($tree_definition);
    $tree->set('left', [
      'value' => '<front>',
      'right' => $right,
    ]);

    $this->mockResolver('Query', 'tree', $this->builder->fromValue($tree));
    $this->mockResolver('Tree', 'uri', $this->builder->fromPath('tree', 'left.value'));

    $query = <<<GQL
      query {
        tree {
          uri
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['uri' => '<front>']]);
  }

  /**
   * @covers ::compose
   */
  public function testCompose(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue([
      'name' => 'some tree',
      'id' => 5,
    ]));
    $this->mockResolver('Tree', 'name', $this->builder->compose(
      $this->builder->fromValue('Some tree name'),
      $this->builder->produce('uppercase')
        ->map('string', $this->builder->fromParent())
    ));

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => 'SOME TREE NAME']]);
  }

  /**
   * @covers ::compose
   */
  public function testComposeNullValue(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue([
      'name' => 'some tree',
      'id' => 5,
    ]));
    $this->mockResolver('Tree', 'name', $this->builder->compose(
      $this->builder->fromValue(NULL),
      $this->builder->produce('uppercase')
        ->map('string', $this->builder->fromParent())
    ));

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => NULL]]);
  }

  /**
   * @covers ::context
   * @covers ::fromContext
   */
  public function testFromContext(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue('some value'));

    $this->mockResolver('Tree', 'context', $this->builder->compose(
      $this->builder->context('my context', $this->builder->fromValue('my context value')),
      $this->builder->fromValue('some language value')
    ));

    $this->mockResolver('Context', 'myContext', $this->builder->fromContext('my context'));

    $query = <<<GQL
      query {
        tree {
          context {
            myContext
          }
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['context' => ['myContext' => 'my context value']]]);
  }

  /**
   * @covers ::cond
   */
  public function testSingleCond(): void {
    $this->mockResolver('Query', 'me', $this->builder->cond([
      [
        $this->builder->fromValue(FALSE),
        $this->builder->fromValue('This should resolve into null.'),
      ],
    ]));

    $query = <<<GQL
      query {
        me
      }
GQL;

    $this->assertResults($query, [], ['me' => NULL]);
  }

  /**
   * @covers ::cond
   */
  public function testSimpleCond(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue([
      'name' => 'some tree',
      'id' => 5,
    ]));
    $this->mockResolver('Tree', 'name', $this->builder->cond([
      [
        $this->builder->fromValue(FALSE),
        $this->builder->fromValue('This should not be in the result.'),
      ],
      [
        $this->builder->fromValue(TRUE),
        $this->builder->fromValue('But this should.'),
      ],
      [
        $this->builder->fromValue(TRUE),
        $this->builder->fromValue('And this not, event though its true.'),
      ],
    ]));

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => 'But this should.']]);
  }

  /**
   * @covers ::cond
   */
  public function testDeferredCond(): void {
    $this->mockResolver('Query', 'tree', $this->builder->fromValue([
      'name' => 'some tree',
      'id' => 5,
    ]));
    $this->mockResolver('Tree', 'name', $this->builder->cond([
      [
        $this->builder->fromValue(FALSE),
        $this->builder->fromValue('This should not be in the result.'),
      ],
      [
        function () {
          return new Deferred(function () {
            return TRUE;
          });
        },
        $this->builder->fromValue('But this should.'),
      ],
      [
        $this->builder->fromValue(TRUE),
        $this->builder->fromValue('And this not, event though its true.'),
      ],
    ]));

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => 'But this should.']]);
  }

  /**
   * @covers ::cond
   */
  public function testParentCond(): void {
    $this->mockResolver('Query', 'tree', ['name' => 'some tree', 'id' => 5]);
    $this->mockResolver('Tree', 'name', $this->builder->cond([
      [
        $this->builder->fromValue(FALSE),
        $this->builder->fromValue('This should not be in the result.'),
      ],
      [
        $this->builder->fromParent(),
        $this->builder->fromValue('But this should.'),
      ],
      [
        $this->builder->fromValue(TRUE),
        $this->builder->fromValue('And this not, event though its true.'),
      ],
    ]));

    $query = <<<GQL
      query {
        tree {
          name
        }
      }
GQL;

    $this->assertResults($query, [], ['tree' => ['name' => 'But this should.']]);
  }

  /**
   * @covers ::defaultValue
   */
  public function testSimpleDefaultValue(): void {
    $this->mockResolver('Query', 'tree', ['name' => 'some tree', 'id' => 5]);
    $this->mockResolver('Tree', 'name', $this->builder->defaultValue(
      $this->builder->fromValue(NULL),
      $this->builder->fromValue('bar')
    ));

    $this->mockResolver('Tree', 'uri', $this->builder->defaultValue(
      $this->builder->fromValue('baz'),
      $this->builder->fromValue('bar')
    ));

    $query = <<<GQL
      query {
        tree(id: 1) {
          name
          uri
        }
      }
GQL;

    $this->assertResults($query, [], [
      'tree' => [
        'name' => 'bar',
        'uri' => 'baz',
      ],
    ]);
  }

  /**
   * Tests the composite default value resolver.
   */
  public function testCompositeDefaultValue(): void {

    $this->mockResolver('Query', 'tree', ['name' => 'some tree', 'id' => 5]);
    $this->mockResolver('Tree', 'name', $this->builder->defaultValue(
      $this->builder->compose(
        $this->builder->fromValue('baz'),
        $this->builder->fromValue(NULL)
      ),
      $this->builder->fromValue('bar')
    ));

    $this->mockResolver('Tree', 'uri', $this->builder->defaultValue(
      $this->builder->compose(
        $this->builder->fromValue(TRUE),
        $this->builder->fromValue('baz')
      ),
      $this->builder->fromValue('bar')
    ));

    $query = <<<GQL
      query {
        tree(id: 1) {
          name
          uri
        }
      }
GQL;

    $this->assertResults($query, [], [
      'tree' => [
        'name' => 'bar',
        'uri' => 'baz',
      ],
    ]);
  }

  /**
   * @covers ::defaultValue
   */
  public function testDeferredDefaultValue(): void {
    $this->mockResolver('Query', 'tree', ['name' => 'some tree', 'id' => 5]);
    $this->mockResolver('Tree', 'name', $this->builder->defaultValue(
      $this->builder->callback(function () {
        return new Deferred(function () {
          return NULL;
        });
      }),
      $this->builder->callback(function () {
        return new Deferred(function () {
          return 'bar';
        });
      })
    ));

    $this->mockResolver('Tree', 'uri', $this->builder->defaultValue(
      $this->builder->callback(function () {
        return new Deferred(function () {
          return 'baz';
        });
      }),
      $this->builder->callback(function () {
        return new Deferred(function () {
          return 'bar';
        });
      })
    ));

    $query = <<<GQL
      query {
        tree(id: 1) {
          name
          uri
        }
      }
GQL;

    $this->assertResults($query, [], [
      'tree' => [
        'name' => 'bar',
        'uri' => 'baz',
      ],
    ]);
  }

}
