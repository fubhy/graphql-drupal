<?php

declare(strict_types=1);

namespace Drupal\graphql_composable\Wrappers\Response;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\GraphQL\Response\Response;

/**
 * Type of response used when an article is returned.
 */
class ArticleResponse extends Response {

  /**
   * The article to be served.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $article;

  /**
   * Sets the content.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $article
   *   The article to be served.
   */
  public function setArticle(?EntityInterface $article): void {
    $this->article = $article;
  }

  /**
   * Gets the article to be served.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The article to be served.
   */
  public function article(): ?EntityInterface {
    return $this->article;
  }

}
