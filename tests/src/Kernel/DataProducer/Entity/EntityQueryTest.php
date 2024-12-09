<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer\Entity;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use GraphQL\Error\UserError;

/**
 * Test class for the entity_query data producer.
 *
 * @group graphql
 */
class EntityQueryTest extends GraphQLTestBase {

  /**
   * Test that invalid filter operator are rejected.
   */
  public function testInvalidFilterOperator(): void {
    $this->expectException(UserError::class);
    $this->expectExceptionMessage("Invalid condition operator 'invalid' for field 'title'.");
    $this->executeDataProducer('entity_query', [
      'type' => 'node',
      'conditions' => [
        [
          'field' => 'title',
          'value' => 'test',
          'operator' => 'invalid',
        ],
      ],
      'allowed_filters' => ['title'],
    ]);
  }

}
