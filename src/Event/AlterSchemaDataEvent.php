<?php

declare(strict_types=1);

namespace Drupal\graphql\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Represents an event that is triggered to alter schema data.
 */
class AlterSchemaDataEvent extends Event {

  /**
   * Event fired to alter schema data.
   *
   * @var string
   */
  const EVENT_NAME = 'graphql.sdl.alter_schema';

  /**
   * The schema array data.
   *
   * @var array
   */
  protected $schemaData;

  /**
   * AlterSchemaDataEvent constructor.
   *
   * @param array $schemaData
   *   The schema data reference.
   */
  public function __construct(array &$schemaData) {
    $this->schemaData = $schemaData;
  }

  /**
   * Returns the schema data.
   *
   * @return array
   *   The schema data.
   */
  public function getSchemaData(): array {
    return $this->schemaData;
  }

  /**
   * Sets the schema data.
   *
   * @param array $schemaData
   *   The schema data.
   */
  public function setSchemaData(array $schemaData): void {
    $this->schemaData = $schemaData;
  }

}
