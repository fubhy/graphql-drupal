<?php

namespace Drupal\graphql\Plugin\GraphQL\Traits;

trait DescribablePluginTrait {

  /**
   * @param $definition
   *
   * @return null|string
   */
  protected function buildDescription($definition) {
    if (!empty($definition['description']) && !is_null($definition['description'])) {
      return (string) ($definition['description']);
    }

    return NULL;
  }

}
