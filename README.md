# GraphQL for Drupal

[![Build Status](https://travis-ci.org/fubhy/graphql-drupal.svg?branch=8.x-3.x)](https://travis-ci.org/fubhy/graphql-drupal)
[![Code Coverage](https://codecov.io/gh/fubhy/graphql-drupal/branch/8.x-3.x/graph/badge.svg)](https://codecov.io/gh/fubhy/graphql-drupal)

This module lets you craft and expose a [GraphQL] schema for [Drupal 8].

It is is built around https://github.com/Youshido/GraphQL. As such, it supports
the full official GraphQL specification with all its features.

You can use this module as a foundation for building your own schema through
custom code or you can use and extend the generated schema using the plugin
architecture of the contained sub-modules.

For ease of development, it includes the [GraphiQL] interface at
/graphql/explorer. Make sure to __enable__ the GraphiQL module.

[Drupal 8]: https://www.drupal.org/8
[GraphQL]: http://graphql.org/
[GraphiQL]: https://github.com/graphql/graphiql/

## Built-in generated schema

The `modules` directory contains a set of modules that help to automatically
create a schema from Drupal data structures and components. By enabling these
sub-modules you can expose much of the Drupal data graph without writing a
single line of code.

Please refer to `modules/README.md` for more information.

## Example implementation

Check out https://github.com/fubhy/drupal-decoupled-app for a complete example
of a fully decoupled React and GraphQL application. Feel free to use that
repository as a starting point for your own decoupled application.

## Documentation

Please note that our documentation is outdated and in dire need of rewriting.
This is due to the vast amount of improvements and additional features we've
added to the module recently. As we are finishing up the 3.x version of this
module we will be re-doing the documentation and record a series of screencasts.

For now we recommend to read these blog posts to learn what is graphQL module and how to use it :
https://www.amazeelabs.com/en/blog/graphql-introduction
https://www.amazeelabs.com/en/blog/drupal-graphql-batteries-included
https://www.amazeelabs.com/en/blog/extending-graphql-part1-fields
https://www.amazeelabs.com/en/blog/extending-graphql-part-2
