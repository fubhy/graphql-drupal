<?php

namespace Drupal\graphql\Cache\RequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains a request policy that prevents caching of GraphQL requests.
 */
class IsEndPoint implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request): ?string {
    if ($request->attributes->has('_graphql')) {
      return static::DENY;
    }
    return NULL;
  }

}
