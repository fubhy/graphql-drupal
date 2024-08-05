<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\User;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets the current user.
 *
 * @DataProducer(
 *   id = "current_user",
 *   name = @Translation("Current user"),
 *   description = @Translation("Current logged in user."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Current user")
 *   )
 * )
 */
class CurrentUser extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * Constructs a new CurrentUser data producer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * Returns the current user.
   *
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field_context
   *   Field context.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  public function resolve(FieldContext $field_context): AccountInterface {
    // Response must be cached per user so that information from previously
    // logged in users will not leak to newly logged in users.
    $field_context->addCacheContexts(['user']);
    return $this->currentUser;
  }

}
