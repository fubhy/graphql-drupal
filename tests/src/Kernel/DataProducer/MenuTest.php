<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Data producers Menu test class.
 *
 * @group graphql
 */
class MenuTest extends GraphQLTestBase {

  /**
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Test menu.
   *
   * @var \Drupal\system\Entity\Menu
   */
  protected $menu;

  /**
   * Test link tree array.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeElement[]
   */
  protected $linkTree;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');
    $this->menuLinkManager = $this->container->get('plugin.manager.menu.link');

    $this->menu = Menu::create([
      'id' => 'menu_test',
      'label' => 'Test menu',
      'description' => 'Description text',
    ]);

    $this->menu->save();

    $base_options = [
      'title' => 'Menu link test',
      'provider' => 'graphql',
      'menu_name' => 'menu_test',
    ];

    $parent = $base_options + [
      'link' => [
        'uri' => 'internal:/menu-test/hierarchy/parent',
        'options' => [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ],
      'description' => 'Test description',
    ];
    $link = MenuLinkContent::create($parent);
    $link->save();
    $links['parent'] = $link->getPluginId();

    $child_1 = $base_options + [
      'link' => ['uri' => 'internal:/menu-test/hierarchy/parent/child'],
      'parent' => $links['parent'],
    ];
    $link = MenuLinkContent::create($child_1);
    $link->save();
    $links['child-1'] = $link->getPluginId();

    $child_1_1 = $base_options + [
      'link' => ['uri' => 'internal:/menu-test/hierarchy/parent/child2/child'],
      'parent' => $links['child-1'],
    ];
    $link = MenuLinkContent::create($child_1_1);
    $link->save();
    $links['child-1-1'] = $link->getPluginId();

    $child_1_2 = $base_options + [
      'link' => ['uri' => 'internal:/menu-test/hierarchy/parent/child2/child'],
      'parent' => $links['child-1'],
    ];
    $link = MenuLinkContent::create($child_1_2);
    $link->save();
    $links['child-1-2'] = $link->getPluginId();

    $child_2 = $base_options + [
      'link' => ['uri' => 'internal:/menu-test/hierarchy/parent/child'],
      'parent' => $links['parent'],
    ];
    $link = MenuLinkContent::create($child_2);
    $link->save();
    $links['child-2'] = $link->getPluginId();

    $menuLinkTree = $this->container->get('menu.link_tree');
    $this->linkTree = $menuLinkTree->load('menu_test', new MenuTreeParameters());
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLinks::resolve
   */
  public function testMenuLinks(): void {
    $result = $this->executeDataProducer('menu_links', [
      'menu' => $this->menu,
    ]);

    $count = 0;
    foreach ($result as $link_tree) {
      $this->assertInstanceOf(MenuLinkTreeElement::class, $link_tree);
      $count += $link_tree->count();
    }

    $this->assertEquals(5, $count);
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuTree\MenuTreeLink::resolve
   */
  public function testMenuTreeLink(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_tree_link', [
        'element' => $link_tree,
      ]);

      $this->assertEquals($link_tree->link, $result);
    }
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuTree\MenuTreeSubtree::resolve
   */
  public function testMenuTreeSubtree(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_tree_subtree', [
        'element' => $link_tree,
      ]);

      $this->assertEquals($link_tree->subtree, $result);
    }
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink\MenuLinkAttribute::resolve
   */
  public function testMenuLinkAttribute(): void {
    $attribute = 'target';
    $assert_happened = FALSE;
    foreach ($this->linkTree as $link_tree) {
      $options = $link_tree->link->getOptions();
      if (!empty($options['attributes'][$attribute])) {
        $result = $this->executeDataProducer('menu_link_attribute', [
          'link' => $link_tree->link,
          'attribute' => 'target',
        ]);

        $this->assertEquals($options['attributes'][$attribute], $result);
        $assert_happened = TRUE;
      }
    }
    $this->assertTrue($assert_happened, 'At least one menu attribute was tested');
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink\MenuLinkDescription::resolve
   */
  public function testMenuLinkDescription(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_link_description', [
        'link' => $link_tree->link,
      ]);

      $this->assertEquals($link_tree->link->getDescription(), $result);
    }
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink\MenuLinkExpanded::resolve
   */
  public function testMenuLinkExpanded(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_link_expanded', [
        'link' => $link_tree->link,
      ]);

      $this->assertEquals($link_tree->link->isExpanded(), $result);
    }
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink\MenuLinkLabel::resolve
   */
  public function testMenuLinkLabel(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_link_label', [
        'link' => $link_tree->link,
      ]);

      $this->assertEquals($link_tree->link->getTitle(), $result);
    }
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink\MenuLinkUrl::resolve
   */
  public function testMenuLinkUrl(): void {
    foreach ($this->linkTree as $link_tree) {
      $result = $this->executeDataProducer('menu_link_url', [
        'link' => $link_tree->link,
      ]);

      $this->assertEquals($link_tree->link->getUrlObject(), $result);
    }
  }

}
