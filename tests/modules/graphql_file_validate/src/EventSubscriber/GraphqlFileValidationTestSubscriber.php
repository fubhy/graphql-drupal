<?php

declare(strict_types=1);

namespace Drupal\graphql_file_validate\EventSubscriber;

use Drupal\file\Validation\FileValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a file validation listener for graphql file validate test.
 */
class GraphqlFileValidationTestSubscriber implements EventSubscriberInterface {

  /**
   * Handles the file validation event.
   *
   * @param \Drupal\file\Validation\FileValidationEvent $event
   *   The event.
   */
  public function onFileValidation(FileValidationEvent $event): void {
    graphql_file_validate('validate', [$event->file->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [FileValidationEvent::class => 'onFileValidation'];
  }

}
