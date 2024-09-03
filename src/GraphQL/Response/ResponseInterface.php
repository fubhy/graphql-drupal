<?php

declare(strict_types=1);

namespace Drupal\graphql\GraphQL\Response;

/**
 * Response interface used for GraphQL responses.
 */
interface ResponseInterface {

  /**
   * Adds the violation.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   Violation message.
   * @param array $properties
   *   Other properties related to the violation.
   */
  public function addViolation($message, array $properties = []): void;

  /**
   * Adds multiple violations.
   *
   * @param string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   Violation messages.
   * @param array $properties
   *   Other properties related to the violation.
   */
  public function addViolations(array $messages, array $properties = []): void;

  /**
   * Gets the violations.
   *
   * @return array
   *   Violations.
   */
  public function getViolations(): array;

  /**
   * Adds the violations from another response to this response.
   */
  public function mergeViolations(ResponseInterface $source): void;

}
