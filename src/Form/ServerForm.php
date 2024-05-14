<?php

namespace Drupal\graphql\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\graphql\Plugin\SchemaPluginManager;
use GraphQL\Error\DebugFlag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form to set up a GraphQL server configuration entity.
 *
 * @package Drupal\graphql\Form
 *
 * @codeCoverageIgnore
 */
class ServerForm extends EntityForm {

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The schema plugin manager.
   *
   * @var \Drupal\graphql\Plugin\SchemaPluginManager
   */
  protected $schemaManager;

  /**
   * ServerForm constructor.
   *
   * @param \Drupal\graphql\Plugin\SchemaPluginManager $schemaManager
   *   The schema plugin manager.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The request context.
   *
   * @codeCoverageIgnore
   */
  public function __construct(SchemaPluginManager $schemaManager, RequestContext $requestContext) {
    $this->requestContext = $requestContext;
    $this->schemaManager = $schemaManager;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.graphql.schema'),
      $container->get('router.request_context')
    );
  }

  /**
   * Ajax callback triggered by the type schema select element.
   *
   * @param array $form
   *   The form array.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function ajaxSchemaConfigurationForm(array $form) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#edit-schema-configuration-plugin-wrapper', $form['schema_configuration']));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $formState): array {
    $form = parent::form($form, $formState);
    /** @var \Drupal\graphql\Entity\ServerInterface $server */
    $server = $this->entity;
    $schemas = array_map(function ($definition) {
      return $definition['name'] ?? $definition['id'];
    }, $this->schemaManager->getDefinitions());
    $schema_keys = array_keys($schemas);

    $input = $formState->getUserInput();
    // Use the schema selected by the user, the one configured, or fall back to
    // the first schema that is defined.
    $schema = ($input['schema'] ?? $server->get('schema')) ?: reset($schema_keys);

    if ($this->operation == 'create') {
      $form['#title'] = $this->t('Add server');
    }
    else {
      $form['#title'] = $this->t('Edit %label server', ['%label' => $server->label()]);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $server->label(),
      '#description' => $this->t('The human-readable name of this server.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#default_value' => $server->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => ['Drupal\graphql\Entity\Server', 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this server. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['schema'] = [
      '#title' => $this->t('Schema'),
      '#type' => 'select',
      '#options' => $schemas,
      '#default_value' => $schema,
      '#description' => $this->t('The schema to use with this server.'),
      '#ajax' => [
        'callback' => '::ajaxSchemaConfigurationForm',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating schema configuration form.'),
        ],
      ],
    ];

    $form['schema_configuration'] = [
      '#type' => 'container',
      '#prefix' => '<div id="edit-schema-configuration-plugin-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    /** @var \Drupal\graphql\Plugin\SchemaPluginInterface $instance */
    $instance = $schema ? $this->schemaManager->createInstance($schema) : NULL;
    if ($instance instanceof PluginFormInterface && $instance instanceof ConfigurableInterface) {
      $instance->setConfiguration($server->get('schema_configuration')[$schema] ?? []);

      $form['schema_configuration'][$schema] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Schema configuration'),
        '#tree' => TRUE,
      ];

      $form['schema_configuration'][$schema] += $instance->buildConfigurationForm([], $formState);
    }

    $form['endpoint'] = [
      '#title' => $this->t('Endpoint'),
      '#type' => 'textfield',
      '#default_value' => $server->get('endpoint'),
      '#description' => $this->t('The endpoint for http queries. Has to start with a forward slash. For example "/graphql".'),
      '#required' => TRUE,
      '#size' => 30,
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];

    $form['batching'] = [
      '#title' => $this->t('Allow query batching'),
      '#type' => 'checkbox',
      '#default_value' => !!$server->get('batching'),
      '#description' => $this->t('Whether batched queries are allowed.'),
    ];

    $form['caching'] = [
      '#title' => $this->t('Enable caching'),
      '#type' => 'checkbox',
      '#default_value' => !!$server->get('caching'),
      '#description' => $this->t('Whether caching of queries and partial results is enabled.'),
    ];

    $form['validation'] = [
      '#title' => $this->t('Validation rules'),
      '#type' => 'fieldset',
    ];

    $form['validation']['disable_introspection'] = [
      '#title' => $this->t('Disable introspection'),
      '#type' => 'checkbox',
      '#default_value' => $server->get('disable_introspection'),
      '#description' => $this->t('Security rule: Whether introspection should be disabled.'),
    ];

    $form['validation']['query_depth'] = [
      '#title' => $this->t('Max query depth'),
      '#type' => 'number',
      '#default_value' => $server->get('query_depth'),
      '#description' => $this->t('Security rule: The maximum allowed depth of nested queries. Leave empty to set unlimited.'),
    ];

    $form['validation']['query_complexity'] = [
      '#title' => $this->t('Max query complexity'),
      '#default_value' => $server->get('query_complexity'),
      '#type' => 'number',
      '#description' => $this->t('Security rule: The maximum allowed complexity of a query. Leave empty to set unlimited.'),
    ];

    $debug_flags = $server->get('debug_flag') ?? 0;
    $form['debug_flag'] = [
      '#title' => $this->t('Debug settings'),
      '#type' => 'checkboxes',
      '#options' => [
        DebugFlag::INCLUDE_DEBUG_MESSAGE => $this->t("Add debugMessage key containing the exception message to errors."),
        DebugFlag::INCLUDE_TRACE => $this->t("Include the formatted original backtrace in errors."),
        DebugFlag::RETHROW_INTERNAL_EXCEPTIONS => $this->t("Rethrow the internal GraphQL exceptions"),
        DebugFlag::RETHROW_UNSAFE_EXCEPTIONS => $this->t("Rethrow unsafe GraphQL exceptions, these are exceptions that have not been marked as safe to expose to clients."),
      ],
      '#default_value' => array_keys(array_filter([
        DebugFlag::INCLUDE_DEBUG_MESSAGE => (bool) ($debug_flags & DebugFlag::INCLUDE_DEBUG_MESSAGE),
        DebugFlag::INCLUDE_TRACE => (bool) ($debug_flags & DebugFlag::INCLUDE_TRACE),
        DebugFlag::RETHROW_INTERNAL_EXCEPTIONS => (bool) ($debug_flags & DebugFlag::RETHROW_INTERNAL_EXCEPTIONS),
        DebugFlag::RETHROW_UNSAFE_EXCEPTIONS => (bool) ($debug_flags & DebugFlag::RETHROW_UNSAFE_EXCEPTIONS),
      ])),
      '#description' => $this->t("It is recommended to disable all debugging in production. During development you can enable the information that you need above."),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    // Translate the debug flag from individual checkboxes to the enum value
    // that the GraphQL library expects.
    $debug_flag = $form_state->getValue('debug_flag');
    if (is_array($debug_flag)) {
      $form_state->setValue('debug_flag', array_sum($debug_flag));
    }
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    $endpoint = &$formState->getValue('endpoint');

    // Trim the submitted value of whitespace and slashes. Ensure to not trim
    // the slash on the left side.
    $endpoint = rtrim(trim(trim($endpoint), ''), "\\/");
    if ($endpoint[0] !== '/') {
      $formState->setErrorByName('endpoint', 'The endpoint path has to start with a forward slash.');
    }
    elseif (!UrlHelper::isValid($endpoint)) {
      $formState->setErrorByName('endpoint', 'The endpoint path contains invalid characters.');
    }

    $schema = $formState->getValue('schema');
    /** @var \Drupal\graphql\Plugin\SchemaPluginInterface $instance */
    $instance = $this->schemaManager->createInstance($schema);
    if (!empty($form['schema_configuration'][$schema]) && $instance instanceof PluginFormInterface && $instance instanceof ConfigurableInterface) {
      $state = SubformState::createForSubform($form['schema_configuration'][$schema], $form, $formState);
      $instance->validateConfigurationForm($form['schema_configuration'][$schema], $state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    $schema = $formState->getValue('schema');
    /** @var \Drupal\graphql\Plugin\SchemaPluginInterface $instance */
    $instance = $this->schemaManager->createInstance($schema);
    if ($instance instanceof PluginFormInterface && $instance instanceof ConfigurableInterface) {
      $state = SubformState::createForSubform($form['schema_configuration'][$schema], $form, $formState);
      $instance->submitConfigurationForm($form['schema_configuration'][$schema], $state);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function save(array $form, FormStateInterface $formState) {
    $save_result = parent::save($form, $formState);

    $this->messenger()->addMessage($this->t('Saved the %label server.', [
      '%label' => $this->entity->label(),
    ]));

    $formState->setRedirect('entity.graphql_server.collection');
    return $save_result;
  }

}
