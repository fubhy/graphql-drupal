<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\graphql\GraphQL\Utility\FileUpload;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests file uploads that should be mapped to a field in a resolver.
 *
 * @group graphql
 */
class UploadFileServiceTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file', 'graphql_file_validate'];

  /**
   * The FileUpload object we want to test, gets prepared in setUp().
   *
   * @var \Drupal\graphql\GraphQL\Utility\FileUpload
   */
  protected $uploadService;

  /**
   * Gets the file path of the source file.
   *
   * @param string $filename
   *   Filename of the source file to be get the file path for.
   *
   * @return string
   *   File path of the source file.
   */
  protected function getSourceTestFilePath(string $filename): string {
    $file_system = $this->container->get('file_system');
    // Create dummy file, since symfony will test if it exists.
    $filepath = $file_system->getTempDirectory() . '/' . $filename;
    touch($filepath);
    return $filepath;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->uploadService = $this->container->get('graphql.file_upload');
  }

  /**
   * Ensure that a correct file upload works.
   */
  public function testSuccess(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK);

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $file_entity = $file_upload_response->getFileEntity();

    $this->assertSame('public://test/test.txt', $file_entity->getFileUri());
    $this->assertFileExists($file_entity->getFileUri());
  }

  /**
   * Tests that a too large file returns a violation.
   */
  public function testFileTooLarge(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_INI_SIZE);

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $violations = $file_upload_response->getViolations();

    $this->assertStringMatchesFormat(
      'The file test.txt could not be saved because it exceeds %d %s, the maximum allowed size for uploads.',
      $violations[0]['message']
    );
  }

  /**
   * Tests that a partial file returns a violation.
   */
  public function testPartialFile(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_PARTIAL);

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $violations = $file_upload_response->getViolations();

    $this->assertStringMatchesFormat(
      'The file "test.txt" could not be saved because the upload did not complete.',
      $violations[0]['message']
    );
  }

  /**
   * Tests that missing settings keys throw an exception.
   */
  public function testMissingSettings(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK);

    $this->expectException(\RuntimeException::class);
    $this->uploadService->saveFileUpload($uploadFile, []);
  }

  /**
   * Tests that the file must not be larger than the file size limit.
   */
  public function testSizeValidation(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 4);

    // Create a file with 4 bytes.
    file_put_contents($uploadFile->getRealPath(), 'test');

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // Only allow 1 byte.
      'max_filesize' => 1,
    ]);
    $violations = $file_upload_response->getViolations();

    // @todo Do we want HTML tags in our violations or not?
    $this->assertStringMatchesFormat(
      'The file is <em class="placeholder">4 bytes</em> exceeding the maximum file size of <em class="placeholder">1 byte</em>.',
      $violations[0]['message']
    );
  }

  /**
   * Tests that a larger image is resized to maximum dimensions.
   */
  public function testDimensionTooLargeValidation(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 4);

    $image = file_get_contents(\Drupal::service('extension.list.module')->getPath('graphql') . '/tests/files/image/10x10.png');

    // Create a file with 4 bytes.
    file_put_contents($uploadFile->getRealPath(), $image);

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // Only allow maximum 5x5 dimension.
      'max_resolution' => '5x5',
    ]);
    $file_entity = $file_upload_response->getFileEntity();
    $image = \Drupal::service('image.factory')->get($file_entity->getFileUri());
    $this->assertEquals(5, $image->getWidth());
    $this->assertEquals(5, $image->getHeight());
  }

  /**
   * Tests that a image that is too small returns a violation.
   */
  public function testDimensionTooSmallValidation(): void {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 4);

    $image = file_get_contents(\Drupal::service('extension.list.module')->getPath('graphql') . '/tests/files/image/10x10.png');

    // Create a file with 4 bytes.
    file_put_contents($uploadFile->getRealPath(), $image);

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // Only allow minimum dimension 15x15.
      'min_resolution' => '15x15',
    ]);
    $violations = $file_upload_response->getViolations();

    $this->assertStringMatchesFormat(
      'The image is too small. The minimum dimensions are <em class="placeholder">15x15</em> pixels and the image size is <em class="placeholder">10</em>x<em class="placeholder">10</em> pixels.',
      $violations[0]['message']
    );
  }

  /**
   * Tests that the uploaded file extension is renamed to txt.
   */
  public function testExtensionRenaming(): void {
    // Evil php file extension!
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test.php');

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $file_entity = $file_upload_response->getFileEntity();

    $this->assertSame('public://test/test.php_.txt', $file_entity->getFileUri());
    $this->assertFileExists($file_entity->getFileUri());
  }

  /**
   * Tests that the uploaded file extension is validated.
   */
  public function testExtensionValidation(): void {
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test.txt');

    $file_upload_response = $this->uploadService->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // We only allow odt files, so validation must fail.
      'file_extensions' => 'odt',
    ]);
    $violations = $file_upload_response->getViolations();

    // @todo Do we want HTML tags in our violations or not?
    $this->assertStringMatchesFormat(
      'Only files with the following extensions are allowed: <em class="placeholder">odt</em>.',
      $violations[0]['message']
    );
  }

  /**
   * Tests that the file lock is released on validation errors.
   */
  public function testLockReleased(): void {
    // Mock the lock system to check that the lock is released.
    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire(Argument::any())->willReturn(TRUE);
    // This is our only assertion - that the lock release method is called.
    $lock->release(Argument::any())->shouldBeCalled();

    $upload_service = new FileUpload(
      \Drupal::service('entity_type.manager'),
      \Drupal::service('current_user'),
      \Drupal::service('file.mime_type.guesser'),
      \Drupal::service('file_system'),
      \Drupal::service('logger.channel.graphql'),
      \Drupal::service('token'),
      $lock->reveal(),
      \Drupal::service('config.factory'),
      \Drupal::service('renderer'),
      \Drupal::service('event_dispatcher'),
      \Drupal::service('image.factory'),
    );

    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = $this->getUploadedFile(UPLOAD_ERR_OK, 4);

    // Create a file with 4 bytes.
    file_put_contents($uploadFile->getRealPath(), 'test');

    $upload_service->saveFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // Only allow 1 byte.
      'max_filesize' => 1,
    ]);
  }

  /**
   * Tests successful scenario with multiple file uploads.
   */
  public function testSuccessWithMultipleFileUploads(): void {
    $uploadFiles = [
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test1.txt', 'graphql_upload_test1.txt'),
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test2.txt', 'graphql_upload_test2.txt'),
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test3.txt', 'graphql_upload_test3.txt'),
    ];

    $file_upload_response = $this->uploadService->saveMultipleFileUploads($uploadFiles, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      'file_extensions' => 'txt',
    ]);

    // There must be no violations.
    $violations = $file_upload_response->getViolations();
    $this->assertEmpty($violations);

    // There must be three file entities.
    $file_entities = $file_upload_response->getFileEntities();
    $this->assertCount(3, $file_entities);
    foreach ($file_entities as $index => $file_entity) {
      $this->assertSame('public://test/test' . ($index + 1) . '.txt', $file_entity->getFileUri());
      $this->assertFileExists($file_entity->getFileUri());
    }
  }

  /**
   * Tests unsuccessful scenario with multiple file uploads.
   */
  public function testUnsuccessWithMultipleFileUploads(): void {
    $uploadFiles = [
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test1.txt', 'graphql_upload_test1.txt'),
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test2.txt', 'graphql_upload_test2.txt'),
      $this->getUploadedFile(UPLOAD_ERR_OK, 0, 'test3.jpg', 'graphql_upload_test3.jpg'),
    ];

    $file_upload_response = $this->uploadService->saveMultipleFileUploads($uploadFiles, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      'file_extensions' => 'txt',
    ]);

    // There must be violation regarding forbidden file extension.
    $violations = $file_upload_response->getViolations();
    $this->assertStringMatchesFormat(
      'Only files with the following extensions are allowed: <em class="placeholder">txt</em>.',
      $violations[0]['message']
    );

    // There must be no file entities.
    $file_entities = $file_upload_response->getFileEntities();
    $this->assertEmpty($file_entities);
  }

  /**
   * Helper method to prepare the UploadedFile depending on core version.
   *
   * Drupal core uses different Symfony versions where we have a different
   * UploadedFile constructor signature.
   */
  protected function getUploadedFile(
    int $error_status,
    int $size = 0,
    string $dest_filename = 'test.txt',
    string $source_filename = 'graphql_upload_test.txt',
  ): UploadedFile {

    $source_filepath = $this->getSourceTestFilePath($source_filename);
    return new UploadedFile($source_filepath, $dest_filename, 'text/plain', $error_status, TRUE);
  }

}
