# Validating mutations

The module exposes a `ResponseInterface` type which can be used to return error messages in the case of mutations or queries. Here we will look at what steps are needed to add an `errors` property to our `createArticle` mutation we implemented before so that if a user can't create an article an error message is returned.

## Creating a response

In the previous implementation we had an `Article` being returned from the mutation directly :

```
...
type Mutation {
    createArticle(data: ArticleInput): Article
}
...
```

But when we implement error handling properly it makes sense to have a response that contains a property to handle errors and a article field of type `Article` so something more like this :

```
...
type ArticleResponse {
  errors: [Violation]
  article: Article
}

scalar Violation
...
```

and in our extension we change our mutation to return the response

```
...
extend type Mutation {
   createArticle(data: ArticleInput): ArticleResponse
}
...
```

Let's analyze what we are doing here because there are a couple of things that might not be 100% obvious at first, but they can be extremely useful in the long run to prepare your types to handle more than just the first use case you have.

### ArticleResponse

we first make the mutation return a new type `ArticleResponse`. We do this because in our response we want to separate errors from the actual content we are returning (the article in this case)

### Violation scalar

We define a Violation scalar which will just hold the error messages that will be returned from when a user tries to do something which is not allowed (will look at how we can actually get those resolved in our mutation in just a second).

## Create the ArticleResponse class

Because we need additional content inside our Response we make a class that implements the module's ResponseInterface. Inside it will have a `article` property (like we saw before). This will be added in the `src/Wrappers/Response` folder and we will call it `ArticleResponse.php`

```php

<?php

declare(strict_types = 1);

namespace Drupal\graphql_composable\Wrappers\Response;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\graphql\GraphQL\Response\Response;

/**
 * Type of response used when an article is returned.
 */
class ArticleResponse extends Response {

  /**
   * The article to be served.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface|null
   */
  protected $article;

  /**
   * Sets the content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $article
   *   The article to be served.
   */
  public function setArticle(?ContentEntityInterface $article): void {
    $this->article = $article;
  }

  /**
   * Gets the article to be served.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The article to be served.
   */
  public function article(): ?ContentEntityInterface {
    return $this->article;
  }

}
```

## Adapt the mutation code

Now we will make the mutation return a type that the module exposes which is the "Response" type we mentioned earlier.

```php
<?php

namespace Drupal\graphql_composable\Plugin\GraphQL\DataProducer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_composable\GraphQL\Response\ArticleResponse;
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
   * @return \Drupal\graphql_composable\GraphQL\Response\ArticleResponse
   *   The newly created article.
   *
   * @throws \Exception
   */
  public function resolve(array $data) {
    $response = new ArticleResponse();
    if ($this->currentUser->hasPermission("create article content")) {
      $values = [
        'type' => 'article',
        'title' => $data['title'],
        'body' => $data['description'],
      ];
      $node = Node::create($values);
      $node->save();
      $response->setArticle($node);
    }
    else {
      $response->addViolation(
        $this->t('You do not have permissions to create articles.')
      );
    }
    return $response;
  }

}

```

We have added a new type that is returned `$response` where we call the `setArticle` method and if there are some validation errors we call the `addValidation` method to register in the errors property. Next we will resolve both these fields.

## Resolve errors and article

To resolve our fields similar to before we go to our schema implementation again and add the resolvers for the
`ArticleResponse` we created (what the mutation now returns back):

```php
/**
 * {@inheritdoc}
 */
public function registerResolvers(ResolverRegistryInterface $registry) {
  ...
  $registry->addFieldResolver('ArticleResponse', 'article',
    $builder->callback(function (ArticleResponse $response) {
      return $response->article();
    })
  );

  $registry->addFieldResolver('ArticleResponse', 'errors',
    $builder->callback(function (ArticleResponse $response) {
      return $response->getViolations();
    })
  );
  ...
  return $registry;
}
```

And that's it if we now call this mutation for example as an anonymous user (if we set arbitrary queries enabled in the permissions for the module) we should get an error :

```json
{
  "data": {
    "createArticle": {
      "errors": [
        {
          "message": "You do not have permissions to create articles."
        }
      ],
      "article": null
    }
  }
}
```
