<?php

namespace Drupal\graphql\GraphQL;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Contains all the mappings how to resolve a GraphQL request.
 */
class ResolverRegistry implements ResolverRegistryInterface {

  /**
   * Nested list of field resolvers.
   *
   * Contains a nested list of callables, keyed by type and field name.
   *
   * @var callable[]
   */
  protected $fieldResolvers = [];

  /**
   * List of type resolvers for abstract types.
   *
   * Contains a list of callables keyed by the name of the abstract type.
   *
   * @var callable[]
   */
  protected $typeResolvers = [];

  /**
   * The default field resolver.
   *
   * Used as a fallback if a specific field resolver can't be found.
   *
   * @var callable
   */
  protected $defaultFieldResolver;

  /**
   * The default type resolver.
   *
   * Used as a fallback if a specific type resolver can't be found.
   *
   * @var callable
   */
  protected $defaultTypeResolver;

  /**
   * ResolverRegistry constructor.
   *
   * @param callable|null $defaultFieldResolver
   * @param callable|null $defaultTypeResolver
   */
  public function __construct(callable $defaultFieldResolver = NULL, callable $defaultTypeResolver = NULL) {
    $this->defaultFieldResolver = $defaultFieldResolver ?: [
      $this,
      'resolveFieldDefault',
    ];
    $this->defaultTypeResolver = $defaultTypeResolver ?: [
      $this,
      'resolveTypeDefault',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolveField($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    // First, check if there is a resolver registered for this field.
    if ($resolver = $this->getRuntimeFieldResolver($value, $args, $context, $info)) {
      if (!$resolver instanceof ResolverInterface) {
        throw new \LogicException(sprintf('Field resolver for field %s on type %s is not callable.', $info->fieldName, $info->parentType->name));
      }

      return $resolver->resolve($value, $args, $context, $info, $field);
    }

    return call_user_func($this->defaultFieldResolver, $value, $args, $context, $info, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveType($value, ResolveContext $context, ResolveInfo $info) {
    // First, check if there is a resolver registered for this abstract type.
    if ($resolver = $this->getRuntimeTypeResolver($value, $context, $info)) {
      if (!is_callable($resolver)) {
        throw new \LogicException(sprintf('Type resolver for type %s is not callable.', $info->parentType->name));
      }

      if (($type = $resolver($value, $context, $info)) !== NULL) {
        return $type;
      }
    }

    return call_user_func($this->defaultTypeResolver, $value, $context, $info);
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldResolver($type, $field, ResolverInterface $resolver) {
    $this->fieldResolvers[$type][$field] = $resolver;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldResolver($type, $field) {
    return $this->fieldResolvers[$type][$field] ?? NULL;
  }

  /**
   * Return all field resolvers in the registry.
   *
   * @return callable[]
   *   A nested list of callables, keyed by type and field name.
   *
   * @todo This should be added to ResolverRegistryInterface in 5.0.0.
   */
  public function getAllFieldResolvers() : array {
    return $this->fieldResolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function addTypeResolver($abstract, callable $resolver) {
    $this->typeResolvers[$abstract] = $resolver;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeResolver($type) {
    return $this->typeResolvers[$type] ?? NULL;
  }

  /**
   * Get a field resolver for the type or any of the interfaces it implements.
   *
   * This allows common functionality (such as for Edge's or Connections) to be
   * implemented for an interface and re-used on any concrete type that extends
   * it.
   *
   * This should be used instead of `getFieldResolver` unless you're certain you
   * want the resolver only for the specific type.
   *
   * @param \GraphQL\Type\Definition\Type $type
   *   The type to find a resolver for.
   * @param string $fieldName
   *   The name of the field to find a resolver for.
   *
   * @return \Drupal\graphql\GraphQL\Resolver\ResolverInterface|null
   *   The defined resolver for the field or NULL if none exists.
   *
   * @todo This should be added to ResolverRegistryInterface in 5.0.0.
   */
  public function getFieldResolverWithInheritance(Type $type, string $fieldName) : ?ResolverInterface {
    if ($resolver = $this->getFieldResolver($type->name, $fieldName)) {
      return $resolver;
    }

    if (!$type instanceof ImplementingType) {
      return NULL;
    }

    // Go through the interfaces implemented for the type on which this field is
    // resolved and check if they lead to a field resolution.
    foreach ($type->getInterfaces() as $interface) {
      if ($resolver = $this->getFieldResolverWithInheritance($interface, $fieldName)) {
        return $resolver;
      }
    }

    return NULL;
  }

  /**
   * Returns the field resolver that should be used at runtime.
   *
   * @param mixed $value
   * @param string $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *
   * @return callable|null
   */
  protected function getRuntimeFieldResolver($value, $args, ResolveContext $context, ResolveInfo $info) {
    return $this->getFieldResolverWithInheritance($info->parentType, $info->fieldName);
  }

  /**
   * Resolves a default value for a field.
   *
   * @param mixed $value
   * @param mixed $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed|null
   */
  protected function resolveFieldDefault($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    return Executor::defaultFieldResolver($value, $args, $context, $info);
  }

  /**
   * Returns the type resolver that should be used on runtime.
   *
   * @param mixed $value
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *
   * @return callable|null
   */
  protected function getRuntimeTypeResolver($value, ResolveContext $context, ResolveInfo $info) {
    return $this->getTypeResolver(Type::getNamedType($info->returnType)->name);
  }

  /**
   * Returns NULL as default type.
   *
   * @param mixed $value
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *
   * @return null
   */
  protected function resolveTypeDefault($value, ResolveContext $context, ResolveInfo $info) {
    return NULL;
  }

}
