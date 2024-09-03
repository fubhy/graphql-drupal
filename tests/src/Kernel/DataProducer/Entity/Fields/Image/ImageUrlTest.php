<?php

namespace Drupal\Tests\graphql\Kernel\DataProducer\Entity\Fields\Image;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\file\FileInterface;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Test class for the ImageUrl data producer.
 *
 * @group graphql
 */
class ImageUrlTest extends GraphQLTestBase {

  /**
   * The file entity mock.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * A file entity mock that returns FALSE on access checking.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $fileNotAccessible;

  /**
   * The generated file URI.
   *
   * @var string
   */
  protected $fileUri;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->fileUri = \Drupal::service('file_url_generator')->generateAbsoluteString('public://test.jpg');

    $this->file = $this->getMockBuilder(FileInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->file->method('getFileUri')->willReturn($this->fileUri);
    $this->file->method('access')->willReturn((new AccessResultAllowed())->addCacheTags(['test_tag']));

    $this->fileNotAccessible = $this->getMockBuilder(FileInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->fileNotAccessible->method('access')->willReturn((new AccessResultForbidden())->addCacheTags(['test_tag_forbidden']));
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\Entity\Fields\Image\ImageUrl::resolve
   */
  public function testImageUrl(): void {
    // Test that we get a file we have access to.
    $result = $this->executeDataProducer('image_url', [
      'entity' => $this->file,
    ]);

    $this->assertEquals($this->fileUri, $result);

    // @todo Add cache checks.
    // $this->assertContains('test_tag', $metadata->getCacheTags());
    // Test that we do not get a file we don't have access to, but the cache
    // tags are still added.
    $result = $this->executeDataProducer('image_url', [
      'entity' => $this->fileNotAccessible,
    ]);

    $this->assertNull($result);

    // @todo Add cache checks.
    // $this->assertContains('test_tag_forbidden',
    // $metadata->getCacheTags());
  }

}
