<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the APQ plugin in combination with dynamic page cache.
 *
 * @group graphql
 */
class AutomaticPersistedQueriesDynamicPageCacheTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
  ];

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
    $this->configureCachePolicy();

    $schema = <<<GQL
      schema {
        query: Query
      }
      type Query {
        node(id: String): Node
      }

      type Node {
        title: String!
        id: Int!
      }
GQL;
    $this->setUpSchema($schema);
    $this->mockResolver('Query', 'node',
      $this->builder->produce('entity_load')
        ->map('type', $this->builder->fromValue('node'))
        ->map('id', $this->builder->fromArgument('id'))
    );

    $this->mockResolver('Node', 'title',
      $this->builder->produce('entity_label')
        ->map('entity', $this->builder->fromParent())
    );

    $this->mockResolver('Node', 'id',
      $this->builder->produce('entity_id')
        ->map('entity', $this->builder->fromParent())
    );

    /** @var \Drupal\graphql\Plugin\DataProducerPluginManager $manager */
    $manager = $this->container->get('plugin.manager.graphql.persisted_query');

    $this->pluginApq = $manager->createInstance('automatic_persisted_query');
  }

  /**
   * Test APQ with dynamic page cache.
   *
   * Tests that cache context for different variables parameter is correctly
   * added to the dynamic page cache entries.
   */
  public function testPageCacheWithDifferentVariables(): void {
    // Before adding the persisted query plugins to the server, we want to make
    // sure that there are no existing plugins already there.
    $this->server->removeAllPersistedQueryInstances();
    $this->server->addPersistedQueryInstance($this->pluginApq);
    $this->server->save();

    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();

    $node = Node::create([
      'nid' => 1,
      'title' => 'Node 1',
      'type' => 'test',
    ]);
    $node->save();

    $node = Node::create([
      'nid' => 2,
      'title' => 'Node 2',
      'type' => 'test',
    ]);
    $node->save();

    $titleQuery = 'query($id: String!) { node(id: $id) { title } }';
    $idQuery = 'query($id: String!) { node(id: $id) { id } }';

    // Post query to endpoint to register the query hashes.
    $extensions['persistedQuery']['sha256Hash'] = hash('sha256', $titleQuery);
    $variables = ['id' => '2'];
    $result = $this->query($titleQuery, $this->server, $variables, $extensions, FALSE, Request::METHOD_POST);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['node' => ['title' => 'Node 2']]], json_decode($result->getContent(), TRUE));

    $extensions['persistedQuery']['sha256Hash'] = hash('sha256', $idQuery);
    $variables = ['id' => '2'];
    $result = $this->query($idQuery, $this->server, $variables, $extensions, FALSE, Request::METHOD_POST);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['node' => ['id' => 2]]], json_decode($result->getContent(), TRUE));

    // Execute apq call.
    $variables = ['id' => '1'];
    $result = $this->query(NULL, $this->server, $variables, $extensions);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['node' => ['id' => 1]]], json_decode($result->getContent(), TRUE));

    // Execute apq call with different variables.
    $variables = ['id' => '2'];
    $result = $this->query(NULL, $this->server, $variables, $extensions);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['node' => ['id' => 2]]], json_decode($result->getContent(), TRUE));

    // Execute apq call with same parameters, but different query.
    $extensions['persistedQuery']['sha256Hash'] = hash('sha256', $titleQuery);
    $variables = ['id' => '2'];
    $result = $this->query(NULL, $this->server, $variables, $extensions);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['data' => ['node' => ['title' => 'Node 2']]], json_decode($result->getContent(), TRUE));
  }

}
