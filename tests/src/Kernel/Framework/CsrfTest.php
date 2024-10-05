<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\graphql\Routing\QueryRouteEnhancer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test CSRF protection on mutations.
 *
 * @group graphql
 */
class CsrfTest extends GraphQLTestBase {

  /**
   * Helper state variable that will be flipped when the test mutation executes.
   *
   * @var bool
   */
  protected $mutationTriggered = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $schema = <<<GQL
      schema {
        mutation: Mutation
      }

      type Mutation {
        write: Boolean
      }
GQL;
    $this->setUpSchema($schema);
    $this->mockResolver('Mutation', 'write',
      function () {
        $this->mutationTriggered = TRUE;
        return TRUE;
      }
    );
  }

  /**
   * Tests that a simple request from an evil origin is not executed.
   *
   * @dataProvider provideSimpleContentTypes
   */
  public function testEvilOrigin(string $content_type): void {
    $request = Request::create('https://example.com/graphql/test', 'POST', [], [], [], [
      'CONTENT_TYPE' => $content_type,
      'HTTP_ORIGIN' => 'https://evil.example.com',
    ], '{ "query": "mutation { write }" }');

    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertFalse($this->mutationTriggered, 'Mutation was triggered');
    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * Data provider for testContentTypeCsrf().
   */
  public static function provideSimpleContentTypes(): array {
    // Three content types that can be sent with simple no-cors POST requests.
    return [
      ['text/plain'],
      ['application/x-www-form-urlencoded'],
      ['multipart/form-data'],
    ];
  }

  /**
   * Tests that a simple multipart form data no-cors request is not executed.
   */
  public function testMultipartFormDataCsrf(): void {
    $request = Request::create('https://example.com/graphql/test', 'POST',
      [
        'operations' => '[{ "query": "mutation { write }" }]',
      ],
      [],
      [],
      [
        'CONTENT_TYPE' => 'multipart/form-data',
        'HTTP_ORIGIN' => 'https://evil.example.com',
      ]
    );

    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertFalse($this->mutationTriggered, 'Mutation was triggered');
    $this->assertSame(400, $response->getStatusCode());
    $result = json_decode($response->getContent());
    $this->assertSame("Form requests must include a Apollo-Require-Preflight HTTP header or the Origin HTTP header value needs to be in the allowedOrigins in the CORS settings.", $result->message);
  }

  /**
   * Test that the JSON content types always work, cannot be forged with CSRF.
   *
   * @dataProvider provideAllowedJsonHeaders
   */
  public function testAllowedJsonRequests(array $headers): void {
    $request = Request::create('https://example.com/graphql/test', 'POST', [], [], [],
      $headers, '{ "query": "mutation { write }" }');

    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertTrue($this->mutationTriggered, 'Mutation was triggered');
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Data provider for testAllowedJsonRequests().
   */
  public static function provideAllowedJsonHeaders(): array {
    return [
      [['CONTENT_TYPE' => 'application/json']],
      [['CONTENT_TYPE' => 'application/graphql']],
    ];
  }

  /**
   * Test that a form request with the correct headers against CSRF are allowed.
   *
   * @dataProvider provideAllowedFormRequests
   */
  public function testAllowedFormRequests(array $headers, array $allowedDomains = []): void {
    $request = Request::create('https://example.com/graphql/test', 'POST',
      [
        'operations' => '[{ "query": "mutation { write }" }]',
      ], [], [], $headers);

    if (!empty($allowedDomains)) {
      // Replace the QueryRouteEnhancer to inject CORS config we want to test.
      $this->container->set('graphql.route_enhancer.query', new QueryRouteEnhancer([
        'enabled' => TRUE,
        'allowedOrigins' => $allowedDomains,
      ]));
    }
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertTrue($this->mutationTriggered, 'Mutation was triggered');
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Data provider for testAllowedFormRequests().
   */
  public static function provideAllowedFormRequests(): array {
    return [
      // Omitting the Origin and Apollo-Require-Preflight is allowed.
      [['CONTENT_TYPE' => 'multipart/form-data']],
      // The custom Apollo-Require-Preflight header overrules any evil Origin
      // header.
      [
        [
          'CONTENT_TYPE' => 'multipart/form-data',
          'HTTP_APOLLO_REQUIRE_PREFLIGHT' => 'test',
          'HTTP_ORIGIN' => 'https://evil.example.com',
        ],
      ],
      // The Origin header alone with the correct domain is allowed.
      [
        [
          'CONTENT_TYPE' => 'multipart/form-data',
          'HTTP_ORIGIN' => 'https://example.com',
        ],
      ],
      // The Origin header with an allowed domain.
      [
        [
          'CONTENT_TYPE' => 'multipart/form-data',
          'HTTP_ORIGIN' => 'https://allowed.example.com',
        ],
        ['https://allowed.example.com'],
      ],
      // The Origin header with any allowed domain.
      [
        [
          'CONTENT_TYPE' => 'multipart/form-data',
          'HTTP_ORIGIN' => 'https://allowed.example.com',
        ],
        ['*'],
      ],
    ];
  }

}
