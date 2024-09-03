# Custom data producers

Sometimes you have a niche scenario that requires some custom code to resolve. Either because using some contrib module that has no direct GraphQL support, or its just a very custom coded part that needs some massaging in order to get the exact data you want.

Custom data producers allow you essentially hook into any data of Drupal, because its a class and you can use services, request any kind of data.

Lets look at a custom Data producer that loads the current user (similar to the 3.x version of currentUser query).

The first step as seen before  is to add our query to the schema :

```
type Query {
  ...
  currentUser: User
  ...
}

type User {
    id: Int
    name: String
}
```

Now that we have this we need to make a resolver that actually loads this user, but for that first we need our own custom data producer "CurrentUser" :

```php
<?php

namespace Drupal\example\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets the ID of current user.
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
   * UserRegister constructor.
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
   * Returns current user id.
   *
   * @return int
   *   The current user id.
   */
  public function resolve() {
    return $this->currentUser->id();
  }

}

```

We are defining a custom data producer `current_user` that we can now use to resolve our query that we previously added to the schema.  Notice that our data producer returns only the user id and not the actual user object. However we can combine it with an entity_load which is already made very efficient with in the module (taking advantage of caching strategies using buffering) so we don't have to actually load the user here.

Lets see how we can consume our newly created data producer :

```php
$registry->addFieldResolver('Query', 'currentUser', $builder->compose(
  $builder->produce('current_user'),
  $builder->produce('entity_load')
    ->map('type', $builder->fromValue('user'))
    ->map('id', $builder->fromParent())
));
```

Notice how we combine our custom data producer with a built-in `entity_load` to make querying more performance and standardized across. We will look at `compose` in more detail in the next section.

In the end when we do a query like this :

```graphql
{
  currentUser {
    id
    name
  }
}
```

we get a result like this :

```json
{
  "data": {
    "currentUser": {
      "id": 1,
      "name": "admin"
    }
  }
}
```

For this to actually work we would need to add resolvers to the User object to resolve the `id` and `name` properties like so:
```php
$registry->addFieldResolver('User', 'id', $builder->callback(function ($account) {
  /** @var \Drupal\Core\Session\AccountProxyInterface $account */
  return $account->id();
}));

$registry->addFieldResolver('User', 'name', $builder->callback(function ($account) {
  /** @var \Drupal\Core\Session\AccountProxyInterface $account */
  return $account->getAccountName();
}));
```
