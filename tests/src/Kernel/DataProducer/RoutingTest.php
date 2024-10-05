<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Data producers Routing test class.
 *
 * @group graphql
 */
class RoutingTest extends GraphQLTestBase {

  /**
   * The redirect storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $redirectStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'path_alias',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
    $this->installConfig(['redirect']);

    $this->redirectStorage = $this->container->get('entity_type.manager')->getStorage('redirect');
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Routing\RouteLoad::resolve
   */
  public function testRouteLoad(): void {
    $result = $this->executeDataProducer('route_load', [
      'path' => '/user/logout',
    ]);

    $this->assertNotNull($result);
    $this->assertEquals('user.logout', $result->getRouteName());

    // Test route_load with redirect to an internal URL.
    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();
    $node = Node::create([
      'title' => 'Node',
      'type' => 'test',
    ]);
    $node->save();
    $nodeUrl = $node->toUrl()->toString();

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->container->get('entity_type.manager')->getStorage('redirect')->create();
    $redirect->setSource('internal-url');
    $redirect->setRedirect($nodeUrl);
    $redirect->save();

    /** @var \Drupal\Core\Url $result */
    $result = $this->executeDataProducer('route_load', [
      'path' => 'internal-url',
    ]);

    $this->assertNotNull($result);
    $this->assertEquals($nodeUrl, $result->toString());

    $redirect->setSource('external-url');
    $redirect->setRedirect('https://example.com');
    $redirect->save();

    $result = $this->executeDataProducer('route_load', [
      'path' => 'external-url',
    ]);

    $this->assertNull($result, 'Route to external URL should not be found.');
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Routing\RouteLoad::resolve
   */
  public function testRedirectRouteLoad(): void {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->redirectStorage->create();
    $redirect->setSource('redirect-url');
    $redirect->setRedirect('user/logout');
    $redirect->save();

    $result = $this->executeDataProducer('route_load', [
      'path' => '/redirect-url',
    ]);

    $this->assertNotNull($result);
    $this->assertEquals('user.logout', $result->getRouteName());

    $redirect->setLanguage('de');
    $redirect->save();

    $result = $this->executeDataProducer('route_load', [
      'path' => '/redirect-url',
      'language' => 'de',
    ]);

    $this->assertNotNull($result);
    $this->assertEquals('user.logout', $result->getRouteName());
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Routing\Url\UrlPath::resolve
   */
  public function testUrlPath(): void {
    $pathValidator = $this->container->get('path.validator');
    $url = $pathValidator->getUrlIfValidWithoutAccessCheck('/user/logout');

    $result = $this->executeDataProducer('url_path', [
      'url' => $url,
    ]);

    // Starting with Drupal 10.3 the URL has a token at the end, ignore that.
    $this->assertStringStartsWith('/user/logout', $result);
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Routing\Url\UrlPath::resolve
   */
  public function testUrlNotFound(): void {
    $result = $this->executeDataProducer('route_load', [
      // cspell:ignore idontexist
      'path' => '/idontexist',
    ]);

    // $this->assertContains('4xx-response', $metadata->getCacheTags());
    $this->assertNull($result);
  }

}
