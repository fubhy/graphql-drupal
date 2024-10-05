<?php

namespace Drupal\graphql\Plugin\LanguageNegotiation;

use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language based on a graphql query.
 *
 * @LanguageNegotiation(
 *   id = Drupal\graphql\Plugin\LanguageNegotiation\OperationLanguageNegotiation::METHOD_ID,
 *   weight = -999,
 *   name = @Translation("GraphQL operation context"),
 *   description = @Translation("Determines the language in the context of an operation.")
 * )
 */
class OperationLanguageNegotiation extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-graphql-operation';

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current execution context.
   *
   * @var \Drupal\graphql\GraphQL\Execution\ResolveContext|null
   */
  protected static $context;

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    if (!empty(static::$context)) {
      return static::$context->getContextLanguage();
    }

    return FALSE;
  }

  /**
   * Set the current resolve context statically which contains the language.
   *
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   */
  public static function setContext(?ResolveContext $context = NULL): void {
    static::$context = $context;
  }

}
