<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\graphql\GraphQL\Buffers\BufferBase;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use GraphQL\Deferred;

/**
 * Test batched field resolving.
 *
 * @group graphql
 */
class BufferedFieldTest extends GraphQLTestBase {

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
        users(uids: [String]): [User]
      }

      type User {
        name: String
        friends: [User]
        foe: User
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * Test if the schema is created properly.
   */
  public function testBatchedFields(): void {
    $buffer = $this->getMockBuilder(BufferBase::class)
      ->onlyMethods(['resolveBufferArray'])
      ->getMock();

    $users = [
      'a' => [
        'name' => 'A',
        'friends' => ['c', 'd'],
      ],
      'b' => [
        'name' => 'B',
        'foe' => 'e',
      ],
      'c' => ['name' => 'C'],
      'd' => ['name' => 'D'],
      'e' => ['name' => 'E'],
    ];

    $buffer->expects(static::exactly(2))
      ->method('resolveBufferArray')
      ->willReturnCallback(function ($items) use ($users) {
        return array_map(function ($item) use ($users) {
          return $users[$item['uid']];
        }, $items);
      });

    $this->mockResolver('Query', 'users',
      function ($parent, $args) use ($buffer) {
        $resolvers = array_map(function ($uid) use ($buffer) {
          return $buffer->createBufferResolver(new \ArrayObject(['uid' => $uid]));
        }, $args['uids']);

        return new Deferred(function () use ($resolvers) {
          $result = [];
          foreach ($resolvers as $resolver) {
            $result[] = $resolver();
          }

          return $result;
        });
      }
    );

    $this->mockResolver('User', 'name',
      function ($parent) {
        return $parent['name'];
      }
    );

    $this->mockResolver('User', 'friends',
      function ($parent) use ($buffer) {
        $resolvers = array_map(function ($uid) use ($buffer) {
          return $buffer->createBufferResolver(new \ArrayObject(['uid' => $uid]));
        }, $parent['friends']);

        return new Deferred(function () use ($resolvers) {
          return array_map(function ($resolver) {
            return $resolver();
          }, $resolvers);
        });
      }
    );

    $this->mockResolver('User', 'foe',
      function ($parent) use ($buffer) {
        $resolver = $buffer->createBufferResolver(new \ArrayObject(['uid' => $parent['foe']]));

        return new Deferred(function () use ($resolver) {
          return $resolver();
        });
      }
    );

    $query = $this->getQueryFromFile('batched.gql');
    $metadata = $this->defaultCacheMetaData();
    $this->assertResults($query, [], [
      'a' => [
        [
          'name' => 'A',
          'friends' => [
            ['name' => 'C'],
            ['name' => 'D'],
          ],
        ],
      ],
      'b' => [
        [
          'name' => 'B',
          'foe' => ['name' => 'E'],
        ],
      ],
    ], $metadata);
  }

}
