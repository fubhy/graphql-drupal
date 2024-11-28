<?php

namespace Drupal\graphql\GraphQL\Buffers;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\graphql\SubRequestResponse;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * SubRequestBuffer class provided a buffer for sub-requests.
 */
class SubRequestBuffer extends BufferBase {

  /**
   * The http kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * SubrequestBuffer constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The http kernel service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(HttpKernelInterface $httpKernel, RequestStack $requestStack) {
    $this->httpKernel = $httpKernel;
    $this->requestStack = $requestStack;
  }

  /**
   * Add an item to the buffer.
   *
   * @param \Drupal\Core\Url $url
   *   The url object to run the subrequest on.
   * @param callable $extract
   *   The callback to run within the sub-request.
   *
   * @return \Closure
   *   The callback to invoke to load the result for this buffer item.
   */
  public function add(Url $url, callable $extract) {
    $item = new \ArrayObject([
      'url' => $url,
      'extract' => $extract,
    ]);

    return $this->createBufferResolver($item);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBufferId($item) {
    /** @var \Drupal\Core\GeneratedUrl $url */
    $url = $item['url']->toString(TRUE);

    return hash('sha256', json_encode([
      'url' => $url->getGeneratedUrl(),
      'tags' => $url->getCacheTags(),
      'contexts' => $url->getCacheContexts(),
      'age' => $url->getCacheMaxAge(),
    ]));
  }

  /**
   * Create a sub-request for the given url.
   *
   * @param \Symfony\Component\HttpFoundation\Request $current
   *   The current main request.
   * @param array $buffer
   *   The buffer.
   * @param string $url
   *   The url to run the subrequest on.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request object.
   */
  protected function createRequest(Request $current, array $buffer, string $url) {
    $request = Request::create(
      $url,
      'GET',
      $current->query->all(),
      $current->cookies->all(),
      $current->files->all(),
      $current->server->all()
    );

    $request->attributes->set('_graphql_subrequest', function () use ($buffer) {
      return array_map(function ($item) {
        return $item['extract']($item['url']);
      }, $buffer);
    });

    // Pass the current session to the sub request if it exists.
    try {
      $request->setSession($current->getSession());
    }
    catch (SessionNotFoundException) {
    }

    return $request;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveBufferArray(array $buffer) {
    /** @var \Drupal\Core\GeneratedUrl $url */
    $url = reset($buffer)['url']->toString(TRUE);

    $current = $this->requestStack->getCurrentRequest();
    $target = $url->getGeneratedUrl();
    $request = $this->createRequest($current, $buffer, $target);

    /** @var \Drupal\graphql\SubRequestResponse $response */
    $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    while ($response instanceof LocalRedirectResponse) {
      $target = $response->getTargetUrl();
      $request = $this->createRequest($current, $buffer, $target);
      $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    }

    if (!($response instanceof SubRequestResponse)) {
      return array_fill_keys(array_keys($buffer), NULL);
    }

    // @todo Remove the request stack manipulation once the core issue described at
    // https://www.drupal.org/node/2613044 is resolved.
    while ($this->requestStack->getCurrentRequest() !== $current) {
      $this->requestStack->pop();
    }

    if ($url instanceof CacheableDependencyInterface) {
      $response->addCacheableDependency($url);
    }

    return $response->getResult();
  }

}
