<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\DataProducerPluginInterface;

/**
 * Base class for data producers that resolve fields for queries or mutations.
 */
abstract class DataProducerPluginBase extends PluginBase implements DataProducerPluginInterface {
  use DataProducerPluginCachingTrait;
  use ContextAwarePluginTrait;
  use TypedDataTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    $definition = $this->getPluginDefinition();
    return !empty($definition['consumes']) ? $definition['consumes'] : [];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function getContextDefinition($name) {
    $definitions = $this->getContextDefinitions();
    if (!empty($definitions[$name])) {
      return $definitions[$name];
    }

    throw new ContextException(sprintf("The %s context is not a valid context.", $name));
  }

  /**
   * {@inheritdoc}
   */
  public function resolveField(FieldContext $field) {
    if (!method_exists($this, 'resolve')) {
      throw new \LogicException('Missing data producer resolve method.');
    }
    $populateDefaultValues = $this->configuration['dataproducer_populate_default_values'] ?? TRUE;
    $context = $populateDefaultValues ? $this->getContextValuesWithDefaults() : $this->getContextValues();
    return call_user_func_array(
      [$this, 'resolve'],
      array_values(array_merge($context, [$field]))
    );
  }

  /**
   * Initializes all contexts and populates default values.
   *
   * We cannot use ::getContextValues() here because it does not work with
   * default_value.
   */
  public function getContextValuesWithDefaults(): array {
    $values = [];
    foreach ($this->getContextDefinitions() as $name => $definition) {
      $values[$name] = $this->getContext($name)->getContextValue();
    }

    return $values;
  }

}
