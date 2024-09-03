<?php

namespace Drupal\graphql\Routing;

use Asm89\Stack\CorsService;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\graphql\GraphQL\Utility\JsonHelper;
use GraphQL\Server\Helper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Route;

/**
 * Adds GraphQL operation information to the Symfony route being resolved.
 */
class QueryRouteEnhancer implements EnhancerInterface {

  /**
   * The CORS options for Origin header checking.
   *
   * @var array
   */
  protected $corsOptions;

  /**
   * Constructor.
   */
  public function __construct(array $corsOptions) {
    $this->corsOptions = $corsOptions;
  }

  /**
   * Returns whether the enhancer runs on the current route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The current route.
   *
   * @return bool
   */
  public function applies(Route $route) {
    return $route->hasDefault('_graphql');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GraphQL\Server\RequestError
   */
  public function enhance(array $defaults, Request $request) {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    if (!$this->applies($route)) {
      return $defaults;
    }

    if ($request->getMethod() === "POST") {
      $this->assertValidPostRequestHeaders($request);
    }

    $helper = new Helper();
    $method = $request->getMethod();
    $body = $this->extractBody($request);
    $query = $this->extractQuery($request);
    $operations = $helper->parseRequestParams($method, $body, $query);

    return $defaults + ['operations' => $operations];
  }

  /**
   * Ensures that the headers for a POST request have triggered a preflight.
   *
   * POST requests must be submitted with content-type headers that properly
   * trigger a cross-origin preflight request. In case content-headers are used
   * that would trigger a "simple" request then custom headers must be provided.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to check.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *    In case the headers indicated a preflight was not performed.
   */
  protected function assertValidPostRequestHeaders(Request $request) : void {
    $content_type = $request->headers->get('content-type');
    if ($content_type === NULL) {
      throw new BadRequestHttpException("GraphQL requests must specify a valid content type header.");
    }

    // application/graphql is a non-standard header that's supported by our
    // server implementation and triggers CORS.
    if ($content_type === "application/graphql") {
      return;
    }

    // @phpstan-ignore-next-line
    $content_format = method_exists($request, 'getContentTypeFormat') ? $request->getContentTypeFormat() : $request->getContentType();
    if ($content_format === NULL) {
      // Symfony before 5.4 does not detect "multipart/form-data", check for it
      // manually.
      if (stripos($content_type, 'multipart/form-data') === 0) {
        $content_format = 'form';
      }
      else {
        throw new BadRequestHttpException("The content type '$content_type' is not supported.");
      }
    }

    // JSON requests provide a non-standard header that trigger CORS.
    if ($content_format === "json") {
      return;
    }

    // The form content types are considered simple requests and don't trigger
    // CORS pre-flight checks, so these require a separate header to prevent
    // CSRF. We need to support "form" for file uploads.
    if ($content_format === "form") {
      // If the client set a custom header then we can be sure CORS was
      // respected.
      $custom_headers = [
        'Apollo-Require-Preflight',
        'X-Apollo-Operation-Name',
        'x-graphql-yoga-csrf',
      ];
      foreach ($custom_headers as $custom_header) {
        if ($request->headers->has($custom_header)) {
          return;
        }
      }
      // 1. Allow requests that have set no Origin header at all, for example
      // server-to-server requests.
      // 2. Allow requests where the Origin matches the site's domain name.
      $origin = $request->headers->get('Origin');
      if ($origin === NULL || $request->getSchemeAndHttpHost() === $origin) {
        return;
      }
      // Allow other origins as configured in the CORS policy.
      if (!empty($this->corsOptions['enabled'])) {
        $cors_service = new CorsService($this->corsOptions);
        if ($cors_service->isOriginAllowed($request)) {
          return;
        }
      }
      throw new BadRequestHttpException("Form requests must include a Apollo-Require-Preflight HTTP header or the Origin HTTP header value needs to be in the allowedOrigins in the CORS settings.");
    }

    throw new BadRequestHttpException("The content type '$content_type' is not supported.");
  }

  /**
   * Extracts the query parameters from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request object.
   *
   * @return array
   *   The normalized query parameters.
   */
  protected function extractQuery(Request $request) {
    return JsonHelper::decodeParams($request->query->all());
  }

  /**
   * Extracts the body parameters from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request object.
   *
   * @return array
   *   The normalized body parameters.
   */
  protected function extractBody(Request $request) {
    $values = [];

    // Extract the request content.
    if ($content = json_decode($request->getContent(), TRUE)) {
      $values = array_merge($values, JsonHelper::decodeParams($content));
    }

    if (stripos($request->headers->get('content-type', ''), 'multipart/form-data') !== FALSE) {
      return $this->extractMultipart($request, $values);
    }

    return $values;
  }

  /**
   * Handles file uploads from multipart/form-data requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $values
   *   The request body values.
   *
   * @return array
   *   The query parameters with added file uploads.
   */
  protected function extractMultipart(Request $request, array $values) {
    // The request body parameters might contain file upload mutations. We treat
    // them according to the graphql multipart request specification.
    //
    // @see https://github.com/jaydenseric/graphql-multipart-request-spec#server
    if ($body = JsonHelper::decodeParams($request->request->all())) {
      // Flatten the operations array if it exists.
      $operations = isset($body['operations']) && is_array($body['operations']) ? $body['operations'] : [];
      $values = array_merge($values, $body, $operations);
    }

    // According to the graphql multipart request specification, uploaded files
    // are referenced to variable placeholders in a map. Here, we resolve this
    // map by assigning the uploaded files to the corresponding variables.
    if (!empty($values['map']) && is_array($values['map']) && $files = $request->files->all()) {
      foreach ($files as $key => $file) {
        if (!isset($values['map'][$key])) {
          continue;
        }

        $paths = (array) $values['map'][$key];
        foreach ($paths as $path) {
          $path = explode('.', $path);

          if (NestedArray::keyExists($values, $path)) {
            NestedArray::setValue($values, $path, $file);
          }
        }
      }
    }

    return $values;
  }

}
