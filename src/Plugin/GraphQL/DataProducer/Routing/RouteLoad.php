<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Routing;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the URL of the given path.
 *
 * @todo Fix the type of the output context.
 *
 * @DataProducer(
 *   id = "route_load",
 *   name = @Translation("Load route"),
 *   description = @Translation("Loads a route."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Route")
 *   ),
 *   consumes = {
 *     "path" = @ContextDefinition("string",
 *       label = @Translation("Path")
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language"),
 *       required = FALSE,
 *       default_value = "und"
 *     )
 *   }
 * )
 */
class RouteLoad extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Optional redirect module repository.
   *
   * @var \Drupal\redirect\RedirectRepository|null
   */
  protected $redirectRepository;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.validator'),
      $container->get('redirect.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * Route constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path validator service.
   * @param \Drupal\redirect\RedirectRepository|null $redirectRepository
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    PathValidatorInterface $pathValidator,
    ?RedirectRepository $redirectRepository = NULL,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->pathValidator = $pathValidator;
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * Resolver.
   *
   * @param string $path
   * @param string|null $language
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *
   * @return \Drupal\Core\Url|null
   */
  public function resolve($path, ?string $language, RefinableCacheableDependencyInterface $metadata) {
    $language = $language ?? Language::LANGCODE_NOT_SPECIFIED;
    $redirect = $this->redirectRepository ? $this->redirectRepository->findMatchingRedirect($path, [], $language) : NULL;
    if ($redirect !== NULL) {
      $url = $redirect->getRedirectUrl();
    }
    else {
      $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($path);
    }

    if ($url && $url->isRouted() && $url->access()) {
      return $url;
    }

    $metadata->addCacheTags(['4xx-response']);
    return NULL;
  }

}
