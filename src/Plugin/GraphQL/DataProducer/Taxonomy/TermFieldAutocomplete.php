<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Taxonomy;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets term items matching the given string in given field's vocabularies.
 *
 * @DataProducer(
 *   id = "term_field_autocomplete",
 *   name = @Translation("Term field autocomplete"),
 *   description = @Translation("Returns autocomplete items matched against given string for vocabularies in given field"),
 *   produces = @ContextDefinition("list",
 *     label = @Translation("List of term ids matching the string.")
 *   ),
 *   consumes = {
 *     "entity_type" = @ContextDefinition("string",
 *       label = @Translation("Entity type the searchable term field is attached to")
 *     ),
 *     "bundle" = @ContextDefinition("string",
 *       label = @Translation("Entity type the searchable term field is attached to")
 *     ),
 *     "field" = @ContextDefinition("string",
 *       label = @Translation("Field name to search the terms on")
 *     ),
 *     "match_string" = @ContextDefinition("string",
 *       label = @Translation("String to be matched"),
 *       required = FALSE
 *     ),
 *     "prioritize_start_with" = @ContextDefinition("boolean",
 *       label = @Translation("Whether terms which start with matching string should come first"),
 *       required = FALSE,
 *       default_value = TRUE
 *     ),
 *     "limit" = @ContextDefinition("integer",
 *       label = @Translation("Number of items to be returned"),
 *       required = FALSE,
 *       default_value = 10
 *     )
 *   }
 * )
 */
class TermFieldAutocomplete extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The default maximum number of items to be capped to prevent DDOS attacks.
   */
  const MAX_ITEMS = 100;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface|null
   */
  protected $termStorage;

  /**
   * The term type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|null
   */
  protected $termType;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Gets the term storage.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   The term storage.
   */
  protected function getTermStorage(): TermStorageInterface {
    if (!isset($this->termStorage)) {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $this->termStorage = $term_storage;
    }
    return $this->termStorage;
  }

  /**
   * Gets the term type.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface
   *   The term type.
   */
  protected function getTermType(): ContentEntityTypeInterface {
    if (!isset($this->termType)) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $term_type */
      $term_type = $this->entityTypeManager->getDefinition('taxonomy_term');
      $this->termType = $term_type;
    }
    return $this->termType;
  }

  /**
   * Gets the field config for given field of given entity type of given bundle.
   *
   * @param string $entity_type
   *   Entity type to get the field config for.
   * @param string $bundle
   *   Bundle to get the field config for.
   * @param string $field
   *   Field to get the field config for.
   *
   * @return \Drupal\field\FieldConfigInterface|null
   *   Field config for given field of given entity type of given bundle, or
   *   null if it does not exist.
   */
  protected function getFieldConfig(string $entity_type, string $bundle, string $field): ?FieldConfigInterface {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_config_storage */
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');

    /** @var \Drupal\field\FieldConfigInterface|null $field_config */
    $field_config = $field_config_storage->load($entity_type . '.' . $bundle . '.' . $field);

    return $field_config;
  }

  /**
   * Whether given field storage config is configured for term field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_config
   *   The field storage config to be examined.
   *
   * @return bool
   *   True if given field storage config is configured for term field.
   */
  public function isTermFieldStorageConfig(FieldStorageDefinitionInterface $field_storage_config): bool {
    // Term level field is allowed.
    $field_type = $field_storage_config->getType();
    if ($field_type == 'term_level') {
      return TRUE;
    }

    // And term reference fields are allowed as well. Must be type of entity
    // reference or entity reference revision and must target taxonomy terms.
    if ($field_type != 'entity_reference' && $field_type != 'entity_reference_revisions') {
      return FALSE;
    }
    if ($field_storage_config->getSetting('target_type') != 'taxonomy_term') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the vocabularies configured for given field if it is a term field.
   *
   * @param string $entity_type
   *   Entity type of the field to get the vocabularies for.
   * @param string $bundle
   *   Bundle of entity type of the field to get the vocabularies for.
   * @param string $field
   *   Field name to get the vocabularies for.
   *
   * @return string[]
   *   Vocabularies configured for the field in case it is a term field, null
   *   otherwise.
   */
  protected function getTermFieldVocabularies(string $entity_type, string $bundle, string $field): ?array {
    // Load field config of given entity type of given bundle. If not obtained,
    // bail out.
    if (!$field_config = $this->getFieldConfig($entity_type, $bundle, $field)) {
      return NULL;
    }

    // Make sure the field is configured for taxonomy terms.
    $field_storage_config = $field_config->getFieldStorageDefinition();
    if (!$this->isTermFieldStorageConfig($field_storage_config)) {
      return NULL;
    }

    // Make sure that target vocabularies are configured.
    $handler_settings = $field_config->getSetting('handler_settings');
    if (empty($handler_settings['target_bundles'])) {
      return NULL;
    }

    // Return list of vocabularies.
    return $handler_settings['target_bundles'];
  }

  /**
   * Gets term items matched against given query for given vocabulary.
   *
   * @param string $entity_type
   *   Entity type the searchable term field is attached to.
   * @param string $bundle
   *   Bundle the searchable term field is attached to.
   * @param string $field
   *   Field name to search the terms on.
   * @param string|null $match_string
   *   String to be matched.
   * @param bool $prioritize_start_with
   *   Whether terms which start with matching string should come first.
   * @param int $limit
   *   Number of items to be returned.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The caching context related to the current field.
   *
   * @return array
   *   List of matched terms.
   */
  public function resolve(
    string $entity_type,
    string $bundle,
    string $field,
    ?string $match_string,
    bool $prioritize_start_with,
    int $limit,
    FieldContext $context,
  ): ?array {
    if ($limit <= 0) {
      $limit = 10;
    }

    if ($limit > self::MAX_ITEMS) {
      $limit = self::MAX_ITEMS;
    }

    // Get configured vocabulary. If none is obtained, bail out.
    if (!$vocabularies = $this->getTermFieldVocabularies($entity_type, $bundle, $field)) {
      return NULL;
    }

    // Base of the query selecting term names and synonyms.
    $query = $this->database->select('taxonomy_term_field_data', 't');
    $query->fields('t', ['tid']);
    $query->condition('t.vid', $vocabularies, 'IN');
    $query->range(0, $limit);

    // Make condition matching name as OR condition group. This makes it
    // extendable if a module needs to cover a match in term name OR in some
    // other fields.
    $like_contains = '%' . $query->escapeLike($match_string) . '%';
    $name_condition_group = $query->orConditionGroup();
    $name_condition_group->condition('t.name', $like_contains, 'LIKE');

    // Additional query logic for matches.
    if ($match_string) {
      // Prioritize terms starting with matching string.
      if ($prioritize_start_with) {
        // Get calculated meta weight value where terms which match the string
        // at the start have higher meta weight value comparing to the terms
        // which match the string in between.
        $like_starts_with = $query->escapeLike($match_string) . '%';
        $query->addExpression(
          '(t.name LIKE :like_starts_with) * 1 + (t.name LIKE :like_contains) * 0.5',
          'meta_weight', [
            ':like_starts_with' => $like_starts_with,
            ':like_contains' => $like_contains,
          ]
        );

        // Order by calculated meta weight value as a first ordering criterion.
        $query->orderBy('meta_weight', 'DESC');
      }

      // Rest of ordering.
      $query->orderBy('t.weight', 'ASC');
      $query->orderBy('t.name', 'ASC');
    }

    // Allow modules to alter the term autocomplete query.
    $args = [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'field' => $field,
      'match_string' => $match_string,
      'prioritize_start_with' => $prioritize_start_with,
      'limit' => $limit,
    ];
    $this->moduleHandler->alter('graphql_term_autocomplete_query', $args, $query, $name_condition_group);

    // Add name OR condition group after query was altered. If added sooner then
    // condition group extensions done in alter hooks wouldn't be reflected.
    $query->condition($name_condition_group);

    // Handle access on query.
    $query->addTag('taxonomy_term_access');

    // Process the terms returned by term autocomplete query.
    $terms = [];
    foreach ($query->execute() as $match) {
      $terms[] = $match->tid;
      // Invalidate on term update, because it may change the name which does
      // not match the string anymore.
      $context->addCacheTags(['taxonomy_term:' . $match->tid]);
    }

    $context->addCacheTags($this->getTermType()->getListCacheTags());

    return $terms;
  }

}
