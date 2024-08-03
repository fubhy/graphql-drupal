# Composing data producers

Often you will find that you need to call multiple producers in a sequence in order to get the data you actually want. Maybe because you call a producer that only returns an `id` and then you need an `entity_load` producer to use that `id` to return the actual entity, or maybe a route that returns a URL Object and then you want to take that URL can get the entity out of it using the `route_entity` data producer.

This can be accomplished using some of the built-in helpers inside the `$builder` object called `compose`. given our example for the previous example `current_user`, this is how it works :

```php
$registry->addFieldResolver('Query', 'currentUser', $builder->compose(
  $builder->produce('current_user'),
  $builder->produce('entity_load')
    ->map('type', $builder->fromValue('user'))
    ->map('id', $builder->fromParent())
));
```

We are chaining the two data producers together here, one after the other and calling `fromParent` will give us the result that was returned in the previous step.

## Custom steps

What if we need to do some massaging but not necessarily using any data producer? The `$builder` object includes a callback property as well that we can use for this :

```php
$registry->addFieldResolver('Query', 'currentUser', $builder->compose(
  $builder->produce('current_user'),
  $builder->produce('entity_load')
    ->map('type', $builder->fromValue('user'))
    ->map('id', $builder->fromParent()),
  $builder->callback(function ($entity) {
    // Here we can do anything we want to the data. We get as a parameter anything that was returned
    // in the previous step.
  })
));
```

## Debugging producers

Note that you can always easily tap into the chain and e.g. use xdebug to debug the values:

```php
  $builder->compose(
      $builder->tap($builder->callback(function ($parent, $args) {
          // YOU CAN SET A XDEBUG BREAKPOINT IN THESE CALLBACKS TO CHECK THE VALUES.
          $compose_step = 0;
      })),
      // Load the file object from the field.
      $builder->produce('property_path')
        ->map('type', $builder->fromValue('entity:YOUR_ENTITY_TYPE'))
        ->map('value', $builder->fromParent())
        ->map('path', $builder->fromValue('YOUR_FIELD_NAME.YOUR_FIELD_PROPERTY')),
      $builder->tap($builder->callback(function ($parent, $args) {
          // YOU CAN SET A XDEBUG BREAKPOINT IN THESE CALLBACKS TO CHECK THE VALUES.
          $compose_step = 1;
      })),
      // Load the image style derivative of the file.
      $builder->produce('image_derivative')
        ->map('entity', $builder->fromParent())
        ->map('style', $builder->fromValue('YOUR_IMAGE_STYLE')),
      $builder->tap($builder->callback(function ($parent, $args) {
          // YOU CAN SET A XDEBUG BREAKPOINT IN THESE CALLBACKS TO CHECK THE VALUES.
          $compose_step = 2;
      })),
      // Retrieve the url of the generated image.
      $builder->produce('image_style_url')
        ->map('derivative', $builder->fromParent()),
      $builder->tap($builder->callback(function ($parent, $args) {
          // YOU CAN SET A XDEBUG BREAKPOINT IN THESE CALLBACKS TO CHECK THE VALUES.
          $compose_step = 3;
      }))
    )
  );
```
