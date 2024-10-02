<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Psr\Log\LoggerInterface;

/**
 * Test error logging.
 *
 * @group graphql
 */
class LoggerTest extends GraphQLTestBase implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * Loggers calls.
   *
   * @var array
   */
  protected $loggerCalls = [];

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
        resolvesToNull: String!
        throwsException: String!
        takesIntArgument(id: Int!): String
      }
GQL;

    $this->setUpSchema($schema);

    $this->mockResolver('Query', 'resolvesToNull', NULL);
    $this->mockResolver('Query', 'throwsException', function () {
      throw new \Exception('BOOM!');
    });
    $this->mockResolver('Query', 'takesIntArgument');

    $this->container->get('logger.factory')->addLogger($this);
  }

  /**
   * {@inheritdoc}
   *
   * @todo For some reason, PHPStan wants to see a type hint for the level here,
   * which is not correct.
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->loggerCalls[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
  }

  /**
   * Test if invariant violation errors are logged.
   */
  public function testInvariantViolationError(): void {
    $result = $this->query('query { resolvesToNull }');
    $this->assertSame(200, $result->getStatusCode());
    // Client should not see the actual error.
    $this->assertSame([
      'errors' => [
        [
          'message' => 'Internal server error',
          'extensions' => [
            'category' => 'internal',
          ],
          'locations' => [
            [
              'line' => 1,
              'column' => 9,
            ],
          ],
          'path' => [
            'resolvesToNull',
          ],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));
    // The error should be logged.
    $this->assertCount(1, $this->loggerCalls);
    $loggerCall = reset($this->loggerCalls);
    $details = json_decode($loggerCall['context']['details'], TRUE);
    $this->assertSame($details['$operation']['query'], 'query { resolvesToNull }');
    $this->assertSame($details['$operation']['variables'], []);
    $this->assertCount(1, $details['$result->errors']);
    $this->assertSame(
      $details['$result->errors'][0]['message'],
      'Cannot return null for non-nullable field "Query.resolvesToNull".'
    );
    $this->assertStringContainsString(
      'For error #0: GraphQL\Error\InvariantViolation: Cannot return null for non-nullable field "Query.resolvesToNull".',
      $loggerCall['context']['previous']
    );
  }

  /**
   * Test if exceptions thrown from resolvers are logged.
   */
  public function testException(): void {
    $result = $this->query('query { throwsException }');
    $this->assertSame(200, $result->getStatusCode());
    // Client should not see the actual error.
    $this->assertSame([
      'errors' => [
        [
          'message' => 'Internal server error',
          'extensions' => [
            'category' => 'internal',
          ],
          'locations' => [
            [
              'line' => 1,
              'column' => 9,
            ],
          ],
          'path' => [
            'throwsException',
          ],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));
    // The error should be logged.
    $this->assertCount(1, $this->loggerCalls);
    $loggerCall = reset($this->loggerCalls);
    $details = json_decode($loggerCall['context']['details'], TRUE);
    $this->assertSame($details['$operation']['query'], 'query { throwsException }');
    $this->assertSame($details['$operation']['variables'], []);
    $this->assertCount(1, $details['$result->errors']);
    $this->assertSame($details['$result->errors'][0]['message'], 'BOOM!');
    $this->assertStringContainsString(
      'For error #0: Exception: BOOM!',
      $loggerCall['context']['previous']
    );
  }

  /**
   * Test if client error are not logged.
   */
  public function testClientError(): void {
    $result = $this->query('query { takesIntArgument(id: "boom") }');
    $this->assertSame(200, $result->getStatusCode());
    // The error should be reported back to client.
    $this->assertSame([
      'errors' => [
        0 => [
          'message' => 'Field "takesIntArgument" argument "id" requires type Int!, found "boom".',
          'extensions' => [
            'category' => 'graphql',
          ],
          'locations' => [
            0 => [
              'line' => 1,
              'column' => 30,
            ],
          ],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));
    // The error should not be logged.
    $this->assertCount(0, $this->loggerCalls);
  }

}
