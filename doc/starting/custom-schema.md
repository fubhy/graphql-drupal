# Creating a schema

The best way to start making a new schema is to take the example schema provided by the graphql_composable example module in `/graphql/examples/` and copy all the files from the example module to a custom module of your own. By doing this you can then start adapting the schema to your needs including your own content types and making them available in the schema.

## Enable the new schema

Each schema is associated to a server. A server defines the URL where a schema is accessible (e.g. `/graphql`) as well as other options like if query batching or caching are enabled.

Go to the list of servers under `/admin/config/graphql` to create a new server for your custom schema. When creating the server choose the "Composable Example schema" as the schema and make sure to check the `Composable Example extension` checkbox. After saving click "Explorer". This will take you to the "GraphiQL" page, where you can explore your custom schema.

## Adapt module to your needs

Inside the `/graphql` folder of the module you will find some files that are a common `.graphqls` file that your editor will likely pick up as GraphQL files already. They include the schema definition for your custom schema.

### composable_extension.base.graphqls

This is the main entry point for your schema. You can insert new types, inputs and fields into them here as needed for your use case. Simply adding new types and fields here will make these available in your API but will not resolve anything just yet, as we didn't implement any resolvers yet.

### composable_extension.extension.graphqls

For now all you need to know about this file is that it extends the base file. What this will allow is to better organize resolvers into different modules where each module might expose different things to the schema. The only existing type by default is `Query` and so to define new queries you have to add them here in the extension.graphqls file.

For more information about composable schemas go to [Advanced section](./../advanced/composable-schemas.md) when talking about splitting schemas so that you can make certain modules enable new functionalities as they are enabled.

### Plugins

The module also includes some Plugins which are required inside the folder `src/Plugin/GraphQL/Schema` and optionally `src/Plugin/GraphQL/SchemaExtension`:

- ComposableSchemaExample.php : This file will define the schema itself. You can register default resolvers and also regular resolvers here. If you don't have a particular need you don't really need anything more than the annotation for your schema at first. Later with more complex examples we will show how it can be useful to add some base functionality (automatic resolvers or default resolvers).
- ComposableGraphQLSchemaExtension.php : This file will be used to implement resolvers in a way that is composable (recommended). We recommend having at least one of these, but you can also implement resolvers across multiple modules by including several schema extensions in each module that exposes certain functionality to the schema when enabled. See the [Advanced section](./../advanced/composable-schemas.md) when talking about splitting schemas.

#### Note

Technically you can also just have everything inside the `src/Plugin/GraphQL/Schema` by defining resolvers directly inside the `getResolverRegistry` method.

## Start implementing resolvers

Now that we have a schema available that we can access we need these types and fields to return actual data that lives in Drupal content types and fields and for this we need to implement what are called [GraphQL resolvers](https://graphql.org/learn/execution/). In this module this is done through a concept of "Data producers" which are helpers to return data from common Drupal entities and other Drupal objects. In the next chapter we will go through what are "Data producers" and how to use them.
