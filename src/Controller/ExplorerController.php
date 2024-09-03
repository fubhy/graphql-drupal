<?php

namespace Drupal\graphql\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\graphql\Entity\ServerInterface;
use Drupal\graphql\GraphQL\Utility\Introspection;
use Drupal\graphql\Plugin\SchemaPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the GraphiQL query builder IDE.
 *
 * @codeCoverageIgnore
 */
class ExplorerController implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The URL generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The introspection service.
   *
   * @var \Drupal\graphql\GraphQL\Utility\Introspection
   */
  protected $introspection;

  /**
   * The schema plugin manager.
   *
   * @var \Drupal\graphql\Plugin\SchemaPluginManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('url_generator'),
      $container->get('graphql.introspection'),
      $container->get('plugin.manager.graphql.schema')
    );
  }

  /**
   * ExplorerController constructor.
   *
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator service.
   * @param \Drupal\graphql\GraphQL\Utility\Introspection $introspection
   *   The introspection service.
   * @param \Drupal\graphql\Plugin\SchemaPluginManager $pluginManager
   *   The schema plugin manager.
   *
   * @codeCoverageIgnore
   */
  public function __construct(UrlGeneratorInterface $urlGenerator, Introspection $introspection, SchemaPluginManager $pluginManager) {
    $this->urlGenerator = $urlGenerator;
    $this->introspection = $introspection;
    $this->pluginManager = $pluginManager;
  }

  /**
   * Controller for the GraphiQL query builder IDE.
   *
   * @param \Drupal\graphql\Entity\ServerInterface $graphql_server
   *   The server.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The render array.
   */
  public function viewExplorer(ServerInterface $graphql_server, Request $request) {
    $url = $this->urlGenerator->generate("graphql.query.{$graphql_server->id()}");
    $introspectionData = $this->introspection->introspect($graphql_server);

    if ($graphql_server->get('disable_introspection')) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Introspection is disabled for this server, enable it to use the Explorer.'),
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => '<div id="graphql-explorer"></div>',
      '#attached' => [
        'library' => ['graphql/explorer'],
        'drupalSettings' => [
          'graphqlRequestUrl' => $url,
          'graphqlIntrospectionData' => $introspectionData,
          'graphqlQuery' => $request->get('query'),
          'graphqlVariables' => $request->get('variables'),
        ],
      ],
    ];
  }

}
