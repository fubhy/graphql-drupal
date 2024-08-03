<?php

namespace Drupal\graphql\Plugin;

/**
 * Defines a cacheable data producer plugins.
 */
interface DataProducerPluginCachingInterface extends DataProducerPluginInterface {

  /**
   * Calculates a cache prefix.
   *
   * @return string|null
   */
  public function edgeCachePrefix();

}
