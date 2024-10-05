<?php

namespace Drupal\graphql\GraphQL\Resolver;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Resolves by setting the value as context with the given name.
 */
class SourceContext implements ResolverInterface {

  /**
   * Name of the context.
   *
   * @var string
   */
  protected $name;

  /**
   * Source resolver.
   *
   * @var mixed
   */
  protected $source;

  /**
   * SourceContext constructor.
   *
   * @param string $name
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface|null $source
   */
  public function __construct($name, ?ResolverInterface $source = NULL) {
    $this->name = $name;
    $this->source = $source;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    $source = $this->source ?? new ParentValue();
    $context = $source->resolve($value, $args, $context, $info, $field);
    $field->setContextValue($this->name, $context);
    return $context;
  }

}
