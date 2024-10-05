<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Data producers Entity multiple test class.
 *
 * @group graphql
 */
class EntityMultipleTest extends GraphQLTestBase {

  /**
   * @var \Drupal\graphql\Plugin\DataProducerPluginManager
   */
  protected $dataProducerManager;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node1;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node2;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node3;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $content_type = NodeType::create([
      'type' => 'lorem',
      'name' => 'ipsum',
      'translatable' => TRUE,
      'display_submitted' => FALSE,
    ]);
    $content_type->save();

    $this->node1 = Node::create([
      'title' => 'Dolor',
      'type' => 'lorem',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->node1->save();

    $this->node2 = Node::create([
      'title' => 'Dolor',
      'type' => 'lorem',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->node2->save();

    $this->node3 = Node::create([
      'title' => 'Dolor',
      'type' => 'lorem',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->node3->save();
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Entity\EntityLoadMultiple::resolve
   */
  public function testResolveEntityLoadMultiple(): void {
    $result = $this->executeDataProducer('entity_load_multiple', [
      'type' => $this->node1->getEntityTypeId(),
      'bundles' => [$this->node1->bundle(), $this->node2->bundle()],
      'ids' => [$this->node1->id(), $this->node2->id(), $this->node3->id()],
    ]);

    $nids = array_values(array_map(function (NodeInterface $item) {
      return $item->id();
    }, $result));

    // All entity is loaded through entity load should match the initial values.
    // Hidden entity (node 3) is not include
    // because access checking will not return it.
    $this->assertEquals([
      $this->node1->id(),
      $this->node2->id(),
    ], $nids);
  }

  /**
   * Make sure that passing a NULL id does not produce any warnings.
   */
  public function testResolveEntityLoadWithNullId(): void {
    $result = $this->executeDataProducer('entity_load_multiple', [
      'type' => $this->node1->getEntityTypeId(),
      'ids' => [NULL],
    ]);

    $this->assertSame([], $result);
  }

}
