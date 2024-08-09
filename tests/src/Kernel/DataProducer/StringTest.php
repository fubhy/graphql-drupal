<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Data producers String test class.
 *
 * @group graphql
 */
class StringTest extends GraphQLTestBase {

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\String\Uppercase::resolve
   *
   * @dataProvider uppercaseProvider
   */
  public function testUppercase(string $input, string $expected): void {
    $result = $this->executeDataProducer('uppercase', [
      'string' => $input,
    ]);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests the upper case data producer.
   */
  public static function uppercaseProvider(): array {
    return [
      ['test', 'TEST'],
      ['123 ..!!', '123 ..!!'],
      ['test123', 'TEST123'],
    ];
  }

}
