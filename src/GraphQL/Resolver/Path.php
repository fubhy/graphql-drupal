<?php

namespace Drupal\graphql\GraphQL\Resolver;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\typed_data\DataFetcherTrait;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Resolves a property path.
 *
 * @todo Delete this resolver. This is a plugin already.
 */
class Path implements ResolverInterface {

  use TypedDataTrait;
  use DataFetcherTrait;

  /**
   * Name of the context.
   *
   * @var string|\Drupal\Core\TypedData\DataDefinitionInterface
   */
  protected $type;

  /**
   * Source resolver.
   *
   * @var mixed
   */
  protected $path;

  /**
   * Resolver.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Path constructor.
   *
   * @param string $type
   * @param mixed $path
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface|null $value
   */
  public function __construct($type, $path, ?ResolverInterface $value = NULL) {
    $this->type = $type;
    $this->path = $path;
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    $value = $this->value ?? new ParentValue();
    $value = $value->resolve($value, $args, $context, $info, $field);
    $metadata = new BubbleableMetadata();

    $type = $this->type instanceof DataDefinitionInterface ? $this->type : DataDefinition::create($this->type);
    $data = $this->getTypedDataManager()->create($type, $value);
    $output = $this->getDataFetcher()->fetchDataByPropertyPath($data, $this->path, $metadata)->getValue();

    $context->addCacheableDependency($metadata);
    if ($output instanceof CacheableDependencyInterface) {
      $context->addCacheableDependency($output);
    }

    return $output;
  }

}
