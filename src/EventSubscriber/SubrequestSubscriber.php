<?php

namespace Drupal\graphql\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the language on subrequests.
 */
class SubrequestSubscriber implements EventSubscriberInterface {

  use CurrentLanguageResetTrait;

  /**
   * Constructs a SubrequestSubscriber object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   * @param \Drupal\language\LanguageNegotiatorInterface $languageNegotiator
   */
  public function __construct(LanguageManagerInterface $languageManager, TranslatorInterface $translator, AccountInterface $currentUser, ?LanguageNegotiatorInterface $languageNegotiator = NULL) {
    $this->languageManager = $languageManager;
    $this->translator = $translator;
    $this->currentUser = $currentUser;
    $this->languageNegotiator = $languageNegotiator;
  }

  /**
   * Handle kernel request events.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel event object.
   */
  public function onKernelRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$request->attributes->has('_graphql_subrequest')) {
      return;
    }

    $request->attributes->set('_controller', '\Drupal\graphql\Controller\SubrequestExtractionController:extract');
    $this->resetLanguageContext();
  }

  /**
   * Handle kernel request finished events.
   *
   * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
   *   The kernel event object.
   */
  public function onKernelRequestFinished(FinishRequestEvent $event): void {
    $request = $event->getRequest();
    if (!$request->attributes->has('_graphql_subrequest')) {
      return;
    }

    $this->resetLanguageContext();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => 'onKernelRequest',
      KernelEvents::FINISH_REQUEST => 'onKernelRequestFinished',
    ];
  }

}
