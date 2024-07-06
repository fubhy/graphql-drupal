# Mutations

In version 4 of Drupal GraphQL `Mutations` work a lot more similar to queries than they do in 3.x. Mutations are called using also Data producers which we already looked at.

Let's make a mutation that creates a new article. In this case it takes a data parameter that can have a `title` and a `description` in order to set these fields when creating the new article if they have been provided.

Similar to queries we can start by adding the necessary schema information, not only to register our new mutation but also provide type safety on all parameters as well. This mutation will return the newly created "Article".

The code with all the demo queries and mutations in these docs can be found in the same `graphql_composable` example module.

## Add the schema declaration

Adapt your base schema file to something like this where we include a new type called `Mutation` and we also create a new input called `ArticleInput` which we will use as the type for our mutation argument.

```
type Mutation

scalar Violation

type Article {
  id: Int!
  title: String!
  author: String
}

input ArticleInput {
  title: String!
  description: String
}
```

And now in our `.extends.graphqls` file we will extend the Mutation type to add our new mutation. This is so that in the future other modules can also themselves extend this type with new mutations keeping things organized.

```
extend type Mutation {
   createArticle(data: ArticleInput): Article
}
```

We can now see we have a Mutation called `createArticle` which takes a data parameter, and because GraphQL is heavily typed we know everything we can and must include in the new Article (`ArticleInput`) like the title which is mandatory in this case.

## Implement the custom data producer (mutation)

We now need to implement the actual mutation, in the file `src/Plugin/GraphQL/DataProducer` we include the following file `CreateArticle.php` :

```php
<?php

namespace Drupal\graphql_composable\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a new article entity.
 *
 * @DataProducer(
 *   id = "create_article",
 *   name = @Translation("Create Article"),
 *   description = @Translation("Creates a new article."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Article")
 *   ),
 *   consumes = {
 *     "data" = @ContextDefinition("any",
 *       label = @Translation("Article data")
 *     )
 *   }
 * )
 */
class CreateArticle extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
   * CreateArticle constructor.
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
   * Creates an article.
   *
   * @param array $data
   *   The title of the job.
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface
   *   The newly created article.
   *
   * @throws \Exception
   */
  public function resolve(array $data) {
    if ($this->currentUser->hasPermission("create article content")) {
      $values = [
        'type' => 'article',
        'title' => $data['title'],
        'body' => $data['description'],
      ];
      $node = Node::create($values);
      $node->save();
      return $node;
    }
    return NULL;
  }

}
```

### Important note

One thing to notice when creating mutations like this is that Access checking needs to be done in the mutation, for queries this usually is done in the
data producer directly (e.g. `entity_load` has access checking built-in) but because we are programmatically creating
things we need to check the user actually has access to do the operation.

## Calling the mutation

To add the resolvers for the `createArticle` mutation we go to our schema implementation and call the created data producer `create_article` inside the `registerResolvers` method.

```php
/**
 * {@inheritdoc}
 */
public function registerResolvers(ResolverRegistryInterface $registry) {

  ...
  // Create article mutation.
  $registry->addFieldResolver('Mutation', 'createArticle',
    $builder->produce('create_article')
      ->map('data', $builder->fromArgument('data'))
  );

  ...
  return $registry;
}
```

This mutation can now be called like this :

```graphql
mutation {
  createArticle(data: { title: "Hello GraphQl 2" }) {
    ... on Article {
      id
      title
    }
  }
}
```

and should return something like :

```json
{
  "data": {
    "createArticle": {
      "id": 2,
      "title": "Hello GraphQl 2"
    }
  }
}
```

## Validating mutations

Now that we have our mutation in place one way we can improve this is by adding some validation so that if someone is not to create an article they get a nice error back (technically in Drupal these are called Violations) so that it can be printed to the user in whichever app this is called. In the next chapter we will look at how we can improve this code to add some validation to it.
