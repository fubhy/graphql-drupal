<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Tests that menu links cache metadata is correct.
 *
 * @group graphql
 */
class MenuLinksCacheTest extends GraphQLTestBase {

  /**
   * Test menu.
   *
   * @var \Drupal\system\Entity\Menu
   */
  protected $menu;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['graphql_menu_links_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');

    $this->menu = Menu::create([
      'id' => 'access_test',
      'label' => 'Access test menu',
      'description' => 'Description text',
    ]);

    $this->menu->save();

    $link_options = [
      'title' => 'Menu link test',
      'provider' => 'graphql',
      'menu_name' => 'access_test',
      'link' => [
        // Only accessible by a user with name "super_admin".
        'uri' => 'internal:/graphql-protected',
      ],
      'description' => 'Test description',
    ];
    $link = MenuLinkContent::create($link_options);
    $link->save();
  }

  /**
   * Tests that the cache context is correctly set for different users.
   */
  public function testAccessCacheContext(): void {
    $manager = $this->container->get('plugin.manager.graphql.data_producer');

    /** @var \Drupal\graphql\Plugin\DataProducerPluginInterface $plugin */
    $plugin = $manager->createInstance('menu_links');
    $plugin->setContextValue('menu', $this->menu);

    // Test as anonymous user, list of links must be empty.
    $field_context = new TestFieldContext();
    $result = $plugin->resolveField($field_context);
    $this->assertEmpty($result);
    $this->assertSame('user', $field_context->getCacheContexts()[0]);

    // Test as super_admin user, list of links must contain the test link.
    $super_admin = $this->createUser(['access content'], 'super_admin');
    $this->setCurrentUser($super_admin);
    $field_context = new TestFieldContext();
    $result = $plugin->resolveField($field_context);
    $menu_item = reset($result);
    $this->assertSame('Menu link test', $menu_item->link->getTitle());
    $this->assertSame('user', $field_context->getCacheContexts()[0]);
  }

}

/**
 * Helper class for mocking during this test.
 */
class TestFieldContext extends FieldContext {

  /**
   * Empty constructor override, we don't need it.
   */
  public function __construct() {}

}
