<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer\Routing;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test class for the RouteEntity data producer.
 *
 * @group graphql
 */
class RouteEntityTest extends GraphQLTestBase {

  /**
   * Published test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $publishedNode;

  /**
   * French translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationFrPublished;

  /**
   * German translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationDePublished;

  /**
   * Unpublished test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $unpublishedNode;

  /**
   * French translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationFrUnpublished;

  /**
   * German translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationDeUnpublished;

  /**
   * Unpublished test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $unpublishedToPublishedNode;

  /**
   * Published french translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationFrUnpublishedToPublished;

  /**
   * Published German translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationDeUnpublishedToPublished;

  /**
   * Published test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $publishedToUnpublishedNode;

  /**
   * Unpublished french translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationFrPublishedToUnpublished;

  /**
   * Unpublished German translation of test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $translationDePublishedToUnpublished;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $content_type = NodeType::create([
      'type' => 'event',
      'name' => 'Event',
      'translatable' => TRUE,
      'display_submitted' => FALSE,
    ]);
    $content_type->save();

    // Published node and published translations.
    $this->publishedNode = Node::create([
      'title' => 'Test Event',
      'type' => 'event',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->publishedNode->save();

    $this->translationFrPublished = $this->publishedNode->addTranslation('fr', ['title' => 'Test Event FR']);
    $this->translationFrPublished->save();

    $this->translationDePublished = $this->publishedNode->addTranslation('de', ['title' => 'Test Event DE']);
    $this->translationDePublished->save();

    // Unpublished node and unpublished translations.
    $this->unpublishedNode = Node::create([
      'title' => 'Test Unpublished Event',
      'type' => 'event',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->unpublishedNode->save();

    $this->translationFrUnpublished = $this->unpublishedNode->addTranslation('fr', ['title' => 'Test Unpublished Event FR']);
    $this->translationFrUnpublished->setUnpublished();
    $this->translationFrUnpublished->save();

    $this->translationDeUnpublished = $this->unpublishedNode->addTranslation('de', ['title' => 'Test Unpublished Event DE']);
    $this->translationDeUnpublished->setUnpublished();
    $this->translationDeUnpublished->save();

    // Unpublished node to published translations.
    $this->unpublishedToPublishedNode = Node::create([
      'title' => 'Test Unpublished to Published Event',
      'type' => 'event',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->unpublishedToPublishedNode->save();

    $this->translationFrUnpublishedToPublished = $this->unpublishedToPublishedNode->addTranslation('fr', ['title' => 'Test Unpublished to Published Event FR']);
    $this->translationFrUnpublishedToPublished->setPublished();
    $this->translationFrUnpublishedToPublished->save();

    $this->translationDeUnpublishedToPublished = $this->unpublishedToPublishedNode->addTranslation('de', ['title' => 'Test Unpublished to Published Event DE']);
    $this->translationDeUnpublishedToPublished->setPublished();
    $this->translationDeUnpublishedToPublished->save();

    // Published node to unpublished translations.
    $this->publishedToUnpublishedNode = Node::create([
      'title' => 'Test Published to Unpublished Event',
      'type' => 'event',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->publishedToUnpublishedNode->save();

    $this->translationFrPublishedToUnpublished = $this->publishedToUnpublishedNode->addTranslation('fr', ['title' => 'Test Published to Unpublished Event FR']);
    $this->translationFrPublishedToUnpublished->setUnpublished();
    $this->translationFrPublishedToUnpublished->save();

    $this->translationDePublishedToUnpublished = $this->publishedToUnpublishedNode->addTranslation('de', ['title' => 'Test Published to Unpublished Event DE']);
    $this->translationDePublishedToUnpublished->setUnpublished();
    $this->translationDePublishedToUnpublished->save();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'event', TRUE);
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Routing\RouteEntity::resolve
   */
  public function testRouteEntity(): void {
    // Published node to published translations.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $this->publishedNode->id()]);

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
    ]);

    $this->assertEquals($this->publishedNode->id(), $result->id());
    $this->assertEquals($this->publishedNode->label(), $result->label());

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
      'language' => 'fr',
    ]);

    $this->assertEquals($this->translationFrPublished->id(), $result->id());
    $this->assertEquals($this->translationFrPublished->label(), $result->label());

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
      'language' => 'de',
    ]);

    $this->assertEquals($this->translationDePublished->id(), $result->id());
    $this->assertEquals($this->translationDePublished->label(), $result->label());

    // Unpublished node to unpublished translations. Make sure we are not
    // allowed to get the unpublished nodes or translations.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $this->unpublishedNode->id()]);
    foreach ([NULL, 'fr', 'de'] as $lang) {
      $result = $this->executeDataProducer('route_entity', [
        'url' => $url,
        'language' => $lang,
      ]);

      $this->assertNull($result);
    }

    // Unpublished node to published translations. Make sure we are not able to
    // get unpublished source, but we are able to get published translations.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $this->unpublishedToPublishedNode->id()]);

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
    ]);

    $this->assertNull($result);

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
      'language' => 'fr',
    ]);

    $this->assertEquals($this->translationFrUnpublishedToPublished->id(), $result->id());
    $this->assertEquals($this->translationFrUnpublishedToPublished->label(), $result->label());

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
      'language' => 'de',
    ]);

    $this->assertEquals($this->translationDeUnpublishedToPublished->id(), $result->id());
    $this->assertEquals($this->translationDeUnpublishedToPublished->label(), $result->label());

    // Published node to unpublished translations. Make sure we are able to get
    // published source, but we are not able to get unpublished translations.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $this->publishedToUnpublishedNode->id()]);

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
    ]);

    $this->assertEquals($this->publishedToUnpublishedNode->id(), $result->id());
    $this->assertEquals($this->publishedToUnpublishedNode->label(), $result->label());

    foreach (['fr', 'de'] as $lang) {
      $result = $this->executeDataProducer('route_entity', [
        'url' => $url,
        'language' => $lang,
      ]);

      $this->assertNull($result);
    }

    // Test with something which is not a URL.
    $this->assertNull($this->executeDataProducer('route_entity', [
      'url' => 'not_a_url',
    ]));

    // Test the 4xx response.
    $temp_node = Node::create([
      'title' => 'Temp node',
      'type' => 'event',
      'status' => NodeInterface::PUBLISHED,
    ]);

    $temp_node->save();
    $url = Url::fromRoute('entity.node.canonical', ['node' => $temp_node->id()]);
    $temp_node->delete();

    $result = $this->executeDataProducer('route_entity', [
      'url' => $url,
    ]);

    // The result has to be null and the metadata has to contain the node_list
    // and the 4xx-response cache tags.
    $this->assertNull($result);

    // @todo Add cache checks.
    // $this->assertContains('node_list', $metadata->getCacheTags());
    // $this->assertContains('4xx-response', $metadata->getCacheTags());
  }

}
