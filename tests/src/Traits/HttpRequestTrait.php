<?php

namespace Drupal\Tests\graphql\Traits;

use Drupal\graphql\Entity\Server;
use Drupal\graphql\Entity\ServerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test trait for the GraphQL HTTP interface.
 */
trait HttpRequestTrait {

  /**
   * The default server.
   *
   * @var \Drupal\graphql\Entity\Server
   */
  protected $server;

  /**
   * Issue a simple query over http.
   *
   * @param string|null $query
   *   The query string. Can be omitted when testing auto persisted queries.
   * @param \Drupal\graphql\Entity\Server|null $server
   *   The server instance.
   * @param array $variables
   *   Query variables.
   * @param array $extensions
   *   The query extensions.
   * @param bool $persisted
   *   Flag if the query is actually the identifier of a persisted query.
   * @param string $method
   *   Method, GET or POST.
   * @param string $operationName
   *   Optional operation name if $query contains multiple operations.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response object.
   */
  protected function query(
    ?string $query,
    ?Server $server = NULL,
    array $variables = [],
    array $extensions = [],
    bool $persisted = FALSE,
    string $method = Request::METHOD_GET,
    string $operationName = '',
  ) {
    $server = $server ?: $this->server;
    $endpoint = $this->server->get('endpoint');
    $extensions = !empty($extensions) ? ['extensions' => $extensions] : [];
    $data = [
      'variables' => $variables,
    ] + $extensions;
    if (!empty($query)) {
      // If the persisted flag is true, then instead of sending the full query
      // to the server we only send the query id.
      $query_key = $persisted ? 'queryId' : 'query';
      $data[$query_key] = $query;
    }
    if ($operationName) {
      $data['operationName'] = $operationName;
    }
    if ($method === Request::METHOD_GET) {
      $request = Request::create($endpoint, $method, $data);
    }
    else {
      $request = Request::create($endpoint, $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
    }

    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * Simulate batched queries over http.
   *
   * @param array[] $queries
   *   A set of queries to be executed in one go.
   * @param \Drupal\graphql\Entity\ServerInterface $server
   *   The server instance.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response object.
   */
  protected function batchedQueries(array $queries, ServerInterface $server = NULL) {
    $server = $server ?: $this->server;
    if (!($server instanceof Server)) {
      throw new \LogicException('Invalid server.');
    }

    $queries = json_encode($queries);
    $endpoint = $this->server->get('endpoint');
    $request = Request::create($endpoint, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $queries);
    return $this->container->get('http_kernel')->handle($request);
  }

}
