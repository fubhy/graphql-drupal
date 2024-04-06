<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the automatic persisted query plugin.
 *
 * @group graphql
 */
class AutomaticPersistedQueriesTest extends GraphQLTestBase {

  /**
   * Test plugin.
   *
   * @var \Drupal\graphql\Plugin\PersistedQueryPluginInterface
   */
  protected $pluginApq;

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
        field_one: String
      }
  GQL;

    $this->setUpSchema($schema);
    $this->mockResolver('Query', 'field_one', 'this is the field one');

    /** @var \Drupal\graphql\Plugin\DataProducerPluginManager $manager */
    $manager = $this->container->get('plugin.manager.graphql.persisted_query');

    $this->pluginApq = $manager->createInstance('automatic_persisted_query');
  }

  /**
   * Test the automatic persisted queries plugin.
   */
  public function testAutomaticPersistedQueries(): void {
    // Before adding the persisted query plugins to the server, we want to make
    // sure that there are no existing plugins already there.
    $this->server->removeAllPersistedQueryInstances();
    $this->server->addPersistedQueryInstance($this->pluginApq);
    $this->server->save();

    $query = 'query { field_one } ';
    $extensions = ['persistedQuery' => ['sha256Hash' => 'some random hash']];

    // Check we get PersistedQueryNotFound.
    $result = $this->query('', $this->server, [], $extensions);

    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame([
      'errors' => [
        [
          'message' => 'PersistedQueryNotFound',
          'extensions' => ['category' => 'request'],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));

    // Post query to endpoint with a not matching hash.
    $result = $this->query($query, $this->server, [], $extensions, FALSE, Request::METHOD_POST);

    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame([
      'errors' => [
        [
          'message' => 'Provided sha does not match query',
          'extensions' => ['category' => 'graphql'],
        ],
      ],
    ], json_decode($result->getContent(), TRUE));

    // Post query to endpoint to get the result and cache it.
    $extensions['persistedQuery']['sha256Hash'] = hash('sha256', $query);
    $result = $this->query($query, $this->server, [], $extensions, FALSE, Request::METHOD_POST);

    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['field_one' => 'this is the field one']], json_decode($result->getContent(), TRUE));

    // Execute first GET request again.
    $result = $this->query($query, $this->server, [], $extensions);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['field_one' => 'this is the field one']], json_decode($result->getContent(), TRUE));
  }

}
