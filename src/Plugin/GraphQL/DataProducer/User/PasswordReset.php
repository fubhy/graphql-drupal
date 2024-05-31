<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\User;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\graphql\GraphQL\Response\Response;
use Drupal\graphql\GraphQL\Response\ResponseInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\user\Controller\UserAuthenticationController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resets the user's password (mutation).
 *
 * @DataProducer(
 *   id = "password_reset",
 *   name = @Translation("Password reset"),
 *   description = @Translation("Allows to reset the password."),
 *   consumes = {
 *     "email" = @ContextDefinition("email",
 *       label = @Translation("Email")
 *     )
 *   }
 * )
 */
class PasswordReset extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $container->get('request_stack');
    /** @var \Drupal\Core\Logger\LoggerChannelInterface $logger */
    $logger = $container->get('logger.channel.graphql');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $request_stack->getCurrentRequest(),
      $logger
    );
  }

  /**
   * UserRegister constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    Request $current_request,
    LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRequest = $current_request;
    $this->logger = $logger;
  }

  /**
   * Creates an user.
   *
   * @param string $email
   *   The email address to reset the password for.
   *
   * @return \Drupal\graphql\GraphQL\Response\ResponseInterface
   *   Response for password reset mutation with violations in case of failure.
   */
  public function resolve(string $email): ResponseInterface {
    $content = [
      'mail' => $email,
    ];

    // Drupal does not have a user authentication service so we need to use the
    // authentication controller instead.
    $controller = UserAuthenticationController::create(\Drupal::getContainer());
    // Build up an authentication request for controller out of current request
    // but replace the request body with proper content. This way most of the
    // data are reused including the client's IP which is needed for flood
    // control. The request body is the only thing (besides client's IP) which
    // is pulled from the request within controller.
    $auth_request = new Request(
      $this->currentRequest->query->all(),
      $this->currentRequest->request->all(),
      $this->currentRequest->attributes->all(),
      $this->currentRequest->cookies->all(),
      $this->currentRequest->files->all(),
      $this->currentRequest->server->all(),
      json_encode($content)
    );
    $auth_request->setRequestFormat('json');

    $response = new Response();
    try {
      $controller_response = $controller->resetPassword($auth_request);
    }
    catch (\Exception $e) {
      // Show general error message so potential attacker cannot abuse endpoint
      // to eg check if some email exist or not. Log to watchdog for potential
      // further investigation.
      $this->logger->warning($e->getMessage());
      $response->addViolation($this->t('Unable to reset password, please try again later.'));
      return $response;
    }
    // Show general error message also in case of unexpected response. Log to
    // watchdog for potential further investigation.
    if ($controller_response->getStatusCode() !== 200) {
      $this->logger->warning("Unexpected response code @code during password reset.", ['@code' => $controller_response->getStatusCode()]);
      $response->addViolation($this->t('Unable to reset password, please try again later.'));
    }

    return $response;
  }

}
