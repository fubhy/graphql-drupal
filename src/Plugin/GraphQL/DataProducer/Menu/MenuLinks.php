<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Menu;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Return the menu links of a menu.
 *
 * @todo Fix output context type.
 *
 * @DataProducer(
 *   id = "menu_links",
 *   name = @Translation("Menu links"),
 *   description = @Translation("Returns the menu links of a menu."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Menu link"),
 *     multiple = TRUE
 *   ),
 *   consumes = {
 *     "menu" = @ContextDefinition("entity:menu",
 *       label = @Translation("Menu")
 *     )
 *   }
 * )
 */
class MenuLinks extends DataProducerPluginBase implements ContainerFactoryPluginInterface {
  use DependencySerializationTrait;

  /**
   * The menu link tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('menu.link_tree')
    );
  }

  /**
   * MenuItems constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   *   The menu link tree service.
   *
   * @codeCoverageIgnore
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, MenuLinkTreeInterface $menuLinkTree) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->menuLinkTree = $menuLinkTree;
  }

  /**
   * Resolver.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu to load links for.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The context to add caching information to.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The list of menu links that are enabled and accessible.
   */
  public function resolve(MenuInterface $menu, FieldContext $context) {
    // Ensure the cache is invalidated when the menu changes.
    $context->addCacheableDependency($menu);
    $tree = $this->menuLinkTree->load($menu->id(), new MenuTreeParameters());

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    return array_filter($this->menuLinkTree->transform($tree, $manipulators), function (MenuLinkTreeElement $item) use ($context) {
      $context->addCacheableDependency($item->access);
      return $item->link instanceof MenuLinkInterface && $item->link->isEnabled() && $item->access->isAllowed();
    });
  }

}
