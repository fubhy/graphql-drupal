<?php

namespace Drupal\Tests\graphql\Kernel\TypeResolver;

use Drupal\graphql\GraphQL\DecoratableTypeResolver;
use Drupal\node\NodeInterface;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test the pages type resolver.
 */
class DecoratableTypeResolverTest extends GraphQLTestBase {

  /**
   * The type resolver.
   *
   * @var \Drupal\graphql\GraphQL\DecoratableTypeResolverInterface
   */
  protected $resolver;

  /**
   * The decorated type resolver.
   *
   * @var \Drupal\graphql\GraphQL\DecoratableTypeResolverInterface
   */
  protected $decoratedResolver;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->resolver = $this->getMockForAbstractClass(DecoratableTypeResolver::class, [NULL]);
    $this->resolver->method('resolve')
      ->willReturnCallback(function ($object) {
        return ucfirst($object->bundle());
      });

    $this->decoratedResolver = $this->getMockForAbstractClass(DecoratableTypeResolver::class, [$this->resolver]);
    $this->decoratedResolver->method('resolve')
      ->willReturnCallback(function ($object) {
        if ($object->bundle(
          ) === 'article') {
            return 'DecoratedArticle';
        }
        return NULL;
      });

  }

  /**
   * Test the decoration.
   */
  public function testDecoration(): void {
    $newsNode = $this->createMock(NodeInterface::class);
    $newsNode->method('bundle')
      ->willReturn('news');

    $articleNode = $this->createMock(NodeInterface::class);
    $articleNode->method('bundle')
      ->willReturn('article');

    $this->assertEquals('News', $this->resolver->__invoke($newsNode));
    $this->assertEquals('Article', $this->resolver->__invoke($articleNode));

    $this->assertEquals('News', $this->decoratedResolver->__invoke($newsNode));
    $this->assertEquals('DecoratedArticle', $this->decoratedResolver->__invoke($articleNode));
  }

}
