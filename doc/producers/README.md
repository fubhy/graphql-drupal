# Data Producers

The 4.x version of the Drupal GraphQL module is built on top of a concept called "Data Producers". These help making data available to a schema by using in part some built-in producers that come with the module but you can also make your own custom producers to make more complex data structures available.

A data producer is more or less a function that takes arguments (either from an end user query or static) and resolves these into some other data, for example taking an entity (such as a node) and giving back a particular field.

Lets imagine we want to make a custom field available for a schema, in this case we have an `author` field in the "Article" content type. For this field we can have a producer that takes an entity and returns or resolves the creator field. Let's apply this to our custom schema which already defines an "Article" type.

## Add the field to the schema

In your `.graphqls` file add the schema definition

```
type Article {
    id: Int!
    title: String!
    author: String
}
```

We are telling the schema that we have a new field on the "Article" type called "author". This is how a user will consume the API so we are not yet telling how to get that data. For now this would return NULL as we don't yet have a resolver in place.

## Add the resolver

The following code is an example of how a data producer for an `author` field on the Article type can be implemented in code. Like mentioned previously this is done inside the `GraphqlSchemaExtension` plugin inside `src/Plugin/SchemaExtension` in your module. You can check the example
module which already implements this as an example.

```php

// Initialize builder which is used to build the resolving logic for the
// fields. This includes the output data which is going to be produced,
// the inputs required for resolving them (resolver arguments, other static
// or dynamic values, or even values produced by parent resolver), then the
// contexts which the resolver can be aware of (eg language), and other
// essentials.
$builder = new ResolverBuilder();

$registry->addFieldResolver('Article', 'author',
  $builder->compose(
    $builder->produce('entity_owner')
      ->map('entity', $builder->fromParent()),
    $builder->produce('entity_label')
      ->map('entity', $builder->fromParent())
  )
);
```
Now you can make a sample article (as a user) and if you now go over to your graphql explorer and run the following query :

```
{
  article(id: 1) {
    id
    title
    author
  }
}
```

You should get a response in the same format e.g. :

```json
{
  "data": {
    "article": {
      "id": 1,
      "title": "Testing article",
      "author": "admin"
    }
  }
}
```

### Resolver builder

You need to initialize the `ResolverBuilder` once inside the `registerResolvers` method (or `getResolverRegistry` if you do not want to use schema extensions) in order to start registering resolvers. This is what will give you access to all the data producers by calling the `produce` method which takes as a parameter the data producer id.

Essentially calling the `produce` method with the data producer id is what you need to do every time you want to make a field available in the schema. We tell Drupal where and how to get the data and specify where this maps to.

This particular resolver uses the `property_path` data producer that comes with the GraphQL module. It's one of the most common ones and you will find yourself using it often to resolve any kind of property on an entity. The module includes a lot more which we will see in the "Built in Data Producers" section.

## Notes

You can find a list of all Data Producers provided by the module inside `src/Plugin/GraphQL/DataProducer` folder.

The 4.x module leverages the advantages of custom schemas and data producers, where you can create your own API structure. In the 3.x module if you had a field named `field_article_creator` the API would expose this field as `fieldArticleCreator`. This means that the API consumer needs to have knowledge about how Drupal structures its data internally.
In the 4.x module you can (and also need to) define your own custom schemas (and data producers) and therefore create your own structure so that someone that uses the API does not need to know how Drupal structures the data.
