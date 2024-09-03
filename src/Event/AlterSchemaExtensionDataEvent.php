<?php

declare(strict_types=1);

namespace Drupal\graphql\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Represents an event that is triggered to alter schema extension data.
 */
class AlterSchemaExtensionDataEvent extends Event {

  /**
   * Event fired to alter schema extension data.
   *
   * @var string
   */
  const EVENT_NAME = 'graphql.sdl.alter_schema_extension';

  /**
   * The schema array data.
   *
   * @var array
   */
  protected $schemaExtensionData;

  /**
   * AlterSchemaExtensionDataEvent constructor.
   *
   * @param array $schemaExtensionData
   *   The schema extension data.
   */
  public function __construct(array $schemaExtensionData) {
    $this->schemaExtensionData = $schemaExtensionData;
  }

  /**
   * Returns the schema extension data.
   *
   * @return array
   *   The schema extension data.
   */
  public function getSchemaExtensionData(): array {
    return $this->schemaExtensionData;
  }

  /**
   * Returns the schema extension data.
   *
   * @param array $schemaExtensionData
   *   The schema extension data.
   */
  public function setSchemaExtensionData(array $schemaExtensionData): void {
    $this->schemaExtensionData = $schemaExtensionData;
  }

}
