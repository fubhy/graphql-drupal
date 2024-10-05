<?php

namespace Drupal\graphql\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\graphql\Event\OperationEvent;
use Drupal\graphql\Plugin\LanguageNegotiation\OperationLanguageNegotiation;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the language before/after a GraphQL operation.
 */
class OperationSubscriber implements EventSubscriberInterface {

  use CurrentLanguageResetTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a OperationSubscriber object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   * @param \Drupal\language\LanguageNegotiatorInterface $languageNegotiator
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, LanguageManagerInterface $languageManager, TranslatorInterface $translator, AccountInterface $currentUser, ?LanguageNegotiatorInterface $languageNegotiator = NULL) {
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManager;
    $this->translator = $translator;
    $this->currentUser = $currentUser;
    $this->languageNegotiator = $languageNegotiator;
  }

  /**
   * Handle operation start events.
   *
   * @param \Drupal\graphql\Event\OperationEvent $event
   *   The kernel event object.
   */
  public function onBeforeOperation(OperationEvent $event): void {
    if ($this->moduleHandler->moduleExists('language') && !empty($this->languageNegotiator)) {
      OperationLanguageNegotiation::setContext($event->getContext());
    }

    $this->resetLanguageContext();
  }

  /**
   * Handle operation end events.
   *
   * @param \Drupal\graphql\Event\OperationEvent $event
   *   The kernel event object.
   */
  public function onAfterOperation(OperationEvent $event): void {
    if ($this->moduleHandler->moduleExists('language') && !empty($this->languageNegotiator)) {
      OperationLanguageNegotiation::setContext($event->getContext());
    }

    $this->resetLanguageContext();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OperationEvent::GRAPHQL_OPERATION_BEFORE => 'onBeforeOperation',
      OperationEvent::GRAPHQL_OPERATION_AFTER => 'onAfterOperation',
    ];
  }

}
