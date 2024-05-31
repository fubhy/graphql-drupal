<?php

namespace Drupal\graphql\GraphQL\Execution;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\graphql\Entity\ServerInterface;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Server\OperationParams;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Context that is provided during resolving the GraphQL tree.
 */
class ResolveContext implements RefinableCacheableDependencyInterface {
  use RefinableCacheableDependencyTrait;

  /**
   * The GraphQL server configuration.
   *
   * @var \Drupal\graphql\Entity\ServerInterface
   */
  protected $server;

  /**
   * Configuration.
   *
   * @var array
   */
  protected $config;

  /**
   * List of available contexts keyed by path and context name.
   *
   * @var array
   */
  protected $contexts;

  /**
   * The operation parameters to perform.
   *
   * @var \GraphQL\Server\OperationParams
   */
  protected $operation;

  /**
   * The parsed schema document.
   *
   * @var \GraphQL\Language\AST\DocumentNode
   */
  protected $document;

  /**
   * Type.
   *
   * @var string
   */
  protected $type;

  /**
   * The context language.
   *
   * @var string
   */
  protected $language;

  /**
   * ResolveContext constructor.
   *
   * @param \Drupal\graphql\Entity\ServerInterface $server
   * @param \GraphQL\Server\OperationParams $operation
   * @param \GraphQL\Language\AST\DocumentNode $document
   * @param string $type
   * @param array $config
   */
  public function __construct(
    ServerInterface $server,
    OperationParams $operation,
    DocumentNode $document,
    $type,
    array $config,
  ) {
    $this->addCacheContexts(['user.permissions']);

    $this->server = $server;
    $this->config = $config;
    $this->operation = $operation;
    $this->document = $document;
    $this->type = $type;
  }

  /**
   * Returns the GraphQL server config entity.
   *
   * @return \Drupal\graphql\Entity\ServerInterface
   */
  public function getServer() {
    return $this->server;
  }

  /**
   * Returns the current operation parameters.
   *
   * @return \GraphQL\Server\OperationParams
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * Returns the parsed GraphQL schema.
   *
   * @return \GraphQL\Language\AST\DocumentNode
   */
  public function getDocument() {
    return $this->document;
  }

  /**
   * Returns the type.
   *
   * @return string
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Returns the current context language.
   *
   * @return string
   */
  public function getContextLanguage() {
    return $this->language;
  }

  /**
   * Sets the current context language.
   *
   * @param string $language
   *
   * @return $this
   */
  public function setContextLanguage($language) {
    $this->language = $language;
    return $this;
  }

  /**
   * Sets a contextual value for the current field and its descendants.
   *
   * Allows field resolvers to set contextual values which can be inherited by
   * their descendants.
   *
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The resolve info object.
   * @param string $name
   *   The name of the context.
   * @param mixed $value
   *   The value of the context.
   *
   * @return $this
   */
  public function setContextValue(ResolveInfo $info, $name, $value) {
    $key = implode('.', $info->path);
    $this->contexts[$key][$name] = $value;

    return $this;
  }

  /**
   * Get a contextual value for the current field.
   *
   * Allows field resolvers to inherit contextual values from their ancestors.
   *
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The resolve info object.
   * @param string $name
   *   The name of the context.
   *
   * @return mixed
   *   The current value of the given context or NULL if it's not set.
   */
  public function getContextValue(ResolveInfo $info, $name) {
    $path = $info->path;

    do {
      $key = implode('.', $path);
      if (isset($this->contexts[$key]) && array_key_exists($name, $this->contexts[$key])) {
        return $this->contexts[$key][$name];
      }

      array_pop($path);
    } while (count($path));

    return NULL;
  }

  /**
   * Checks whether contextual value for the current field exists.
   *
   * Also checks ancestors of the field.
   *
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The resolve info object.
   * @param string $name
   *   The name of the context.
   *
   * @return bool
   *   TRUE if the context exists, FALSE Otherwise.
   */
  public function hasContextValue(ResolveInfo $info, $name) {
    $path = $info->path;

    do {
      $key = implode('.', $path);
      if (isset($this->contexts[$key]) && array_key_exists($name, $this->contexts[$key])) {
        return TRUE;
      }

      array_pop($path);
    } while (count($path));

    return FALSE;
  }

}
