<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test the test framework.
 *
 * @group graphql
 */
class TestFrameworkTest extends GraphQLTestBase {

  /**
   * Test mocked fields.
   */
  public function testFieldMock(): void {
    $schema = <<<GQL
      type Query {
        root: String
      }
GQL;

    $this->setUpSchema($schema);

    $cacheable = $this->getMockBuilder(CacheableDependencyInterface::class)
      ->onlyMethods(['getCacheTags', 'getCacheMaxAge', 'getCacheContexts'])
      ->getMock();

    $cacheable->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(['my_tag']);

    $cacheable->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(42);

    $cacheable->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);

    $this->mockResolver('Query', 'root',
      $this->builder->compose(
        $this->builder->fromValue($cacheable),
        $this->builder->fromValue('test')
      )
    );

    $metadata = $this->defaultCacheMetaData();
    $metadata->setCacheMaxAge(42);
    $metadata->addCacheTags([
      'my_tag',
    ]);

    $this->assertResults('{ root }', [], [
      'root' => 'test',
    ], $metadata);
  }

  /**
   * Test result error assertions.
   */
  public function testErrorAssertion(): void {
    // cspell:ignore wrongname
    $schema = <<<GQL
      type Query {
        wrongname: String
      }
GQL;

    $this->setUpSchema($schema);

    $metadata = new CacheableMetadata();
    $metadata->setCacheMaxAge(0);

    $this->assertErrors('{ root }', [], [
      'Cannot query field "root" on type "Query".',
    ], $metadata);

    $this->assertErrors('{ root }', [], [
      '/root.*Query/',
    ], $metadata);
  }

  /**
   * Test mutation mocking.
   */
  public function testMutationMock(): void {
    $schema = <<<GQL
      schema {
        query: Query
        mutation: Mutation
      }

      type Query {
        root: Boolean
      }

      type Mutation {
        addUser(user: User!): Boolean
      }

      enum Gender {
        Female
        Male
      }

      input User {
        name: String
        age: Int
        gender: Gender
      }
GQL;

    $this->setUpSchema($schema);

    $this->mockResolver('Query', 'root', TRUE);

    $this->mockResolver('Mutation', 'addUser',
      function ($parent, $args) {
        return $args['user']['age'] > 50 && $args['user']['gender'] === 'Male';
      }
    );

    $metadata = $this->defaultMutationCacheMetaData();
    $this->assertResults('mutation ($user: User!) { addUser(user: $user) }', [
      'user' => [
        'name' => 'John Doe',
        'age' => 52,
        'gender' => 'Male',
      ],
    ], ['addUser' => TRUE], $metadata);
  }

  /**
   * Test interface mocking.
   */
  public function testInterfaceMock(): void {
    $schema = <<<GQL
      schema {
        query: Query
      }

      type Query {
        root: [Token]
      }

      interface Token {
        id: Int
      }

      type Number implements Token {
        id: Int
        value: Int
      }

      type Word implements Token {
        id: Int
        value: String
      }
GQL;

    $this->setUpSchema($schema);

    $this->mockTypeResolver('Token', function ($value) {
      return is_int($value['value']) ? 'Number' : 'Word';
    });

    $this->mockResolver('Query', 'root', [
      ['value' => 42],
      ['value' => 'GraphQL'],
    ]);

    $query = <<<GQL
      query {
        root {
          ... on Number {
            number: value
          }

          ... on Word {
            word:value
          }
        }
      }
GQL;

    $this->assertResults($query, [], [
      'root' => [
        0 => ['number' => 42],
        1 => ['word' => 'GraphQL'],
      ],
    ]);
  }

  /**
   * Test union mocks.
   */
  public function testUnionMock(): void {
    $schema = <<<GQL
      schema {
        query: Query
      }

      type Query {
        root: [Token]
      }

      union Token = Number | Word

      type Number implements Token {
        value: Int
      }

      type Word implements Token {
        value: String
      }
GQL;

    $this->setUpSchema($schema);

    $this->mockTypeResolver('Token', function ($value) {
      return is_int($value['value']) ? 'Number' : 'Word';
    });

    $this->mockResolver('Query', 'root', [
      ['value' => 42],
      ['value' => 'GraphQL'],
    ]);

    $this->assertResults('{ root { ... on Number { number:value } ... on Word { word:value }  } }', [], [
      'root' => [
        0 => ['number' => 42],
        1 => ['word' => 'GraphQL'],
      ],
    ]);
  }

}
