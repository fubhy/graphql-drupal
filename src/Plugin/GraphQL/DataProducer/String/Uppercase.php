<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\String;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Transforms a string to uppercase.
 *
 * @DataProducer(
 *   id = "uppercase",
 *   name = @Translation("Uppercase"),
 *   description = @Translation("Transforms a string to uppercase."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Uppercase converted string")
 *   ),
 *   consumes = {
 *     "string" = @ContextDefinition("string",
 *       label = @Translation("String")
 *     )
 *   }
 * )
 */
class Uppercase extends DataProducerPluginBase {

  /**
   * Value resolver.
   *
   * @param string $string
   *
   * @return string
   */
  public function resolve($string) {
    return strtoupper($string);
  }

}
