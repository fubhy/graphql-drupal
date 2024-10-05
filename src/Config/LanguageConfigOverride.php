<?php

namespace Drupal\graphql\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\language\LanguageNegotiationMethodManager;

/**
 * GraphQL config overrides.
 *
 * Enforce the GraphQL language negotiation always to be on top.
 */
class LanguageConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * The config storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $baseStorage;

  /**
   * The negotiator manager service.
   *
   * @var \Drupal\language\LanguageNegotiationMethodManager|null
   */
  protected $negotiatorManager;

  /**
   * GraphQLConfigOverrides constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The config storage service.
   * @param \Drupal\language\LanguageNegotiationMethodManager|null $negotiatorManager
   */
  public function __construct(StorageInterface $storage, ?LanguageNegotiationMethodManager $negotiatorManager = NULL) {
    $this->baseStorage = $storage;
    $this->negotiatorManager = $negotiatorManager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    // We can't use the static property of the negotiation method here because
    // the language module might not be enabled.
    $method = 'language-graphql-operation';

    if ($this->negotiatorManager && in_array('language.types', $names)) {
      if ($this->negotiatorManager->hasDefinition($method) && $config = $this->baseStorage->read('language.types')) {
        foreach (array_keys($config['negotiation']) as $type) {
          $config['negotiation'][$type]['enabled'][$method] = -999;
          asort($config['negotiation'][$type]['enabled']);
        }

        return ['language.types' => $config];
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'graphql';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    // The interface says we should return an object here, but we don't care and
    // this does not seem to break anything?
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

}
