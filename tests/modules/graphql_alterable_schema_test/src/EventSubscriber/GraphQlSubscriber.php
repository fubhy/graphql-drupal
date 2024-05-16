<?php

declare(strict_types=1);

namespace Drupal\graphql_alterable_schema_test\EventSubscriber;

use Drupal\graphql\Event\AlterSchemaDataEvent;
use Drupal\graphql\Event\AlterSchemaExtensionDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the graphql schema alter events.
 */
class GraphQlSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      AlterSchemaExtensionDataEvent::EVENT_NAME => ['alterSchemaExtensionData'],
      AlterSchemaDataEvent::EVENT_NAME => ['alterSchemaData'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterSchemaExtensionData(AlterSchemaExtensionDataEvent $event): void {
    $schemaData = $event->getSchemaExtensionData();
    // I do not recommend direct replace, better user parsing or regex.
    // But this is an example of what you can do.
    $schemaData['graphql_alterable_schema_test'] = str_replace('position: Int', 'position: Int!', $schemaData['graphql_alterable_schema_test'] ?? '');

    // Test empty extensions can still extend the schema.
    // https://github.com/drupal-graphql/graphql/issues/1395
    if (empty($schemaData['graphql_alterable_schema_test'])) {
      $schemaData['graphql_alterable_schema_test'] = <<<GQL
        extend type Result {
          empty: Boolean!
        }
      GQL;
    }

    $event->setSchemaExtensionData($schemaData);
  }

  /**
   * {@inheritdoc}
   */
  public function alterSchemaData(AlterSchemaDataEvent $event): void {
    $schemaData = $event->getSchemaData();
    // It is not recommended direct replacement, better user parsing or regex.
    // But this is an example of what you can do.
    $schemaData[0] = str_replace('alterableQuery(id: Int): Result', 'alterableQuery(id: Int!): Result', $schemaData[0]);
    $event->setSchemaData($schemaData);
  }

}
