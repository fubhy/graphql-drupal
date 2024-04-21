<?php

namespace Drupal\graphql\GraphQL\Utility;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\graphql\GraphQL\Response\FileUploadResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Service to manage file uploads within GraphQL mutations.
 *
 * This service handles file validations like max upload size.
 */
class FileUpload {

  use StringTranslationTrait;

  /**
   * The file storage where we will create new file entities from.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The mime type guesser service.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * GraphQL logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The token replacement instance for tokens in file directory paths.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The lock service to prevent duplicate file uploads to the same destination.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The file system configuration to determine if we allow insecure uploads.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $systemFileConfig;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    MimeTypeGuesserInterface $mimeTypeGuesser,
    FileSystemInterface $fileSystem,
    LoggerChannelInterface $logger,
    Token $token,
    LockBackendInterface $lock,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
    EventDispatcherInterface $eventDispatcher,
    ImageFactory $image_factory
  ) {
    /** @var \Drupal\file\FileStorageInterface $file_storage */
    $file_storage = $entityTypeManager->getStorage('file');
    $this->fileStorage = $file_storage;
    $this->currentUser = $currentUser;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileSystem = $fileSystem;
    $this->logger = $logger;
    $this->token = $token;
    $this->lock = $lock;
    $this->systemFileConfig = $config_factory->get('system.file');
    $this->renderer = $renderer;
    $this->eventDispatcher = $eventDispatcher;
    $this->imageFactory = $image_factory;
  }

  /**
   * Gets max upload size.
   *
   * @param array $settings
   *   The file field settings.
   *
   * @return float
   *   Max upload size.
   */
  protected function getMaxUploadSize(array $settings): float {
    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toNumber(Environment::getUploadMaxSize());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toNumber($settings['max_filesize']));
    }
    return $max_filesize;
  }

  /**
   * Validates an uploaded file, saves it and returns a file upload response.
   *
   * Based on several file upload handlers, see
   * _file_save_upload_single()
   * \Drupal\file\Plugin\Field\FieldType\FileItem
   * \Drupal\file\Plugin\rest\resource\FileUploadResource.
   *
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile $uploaded_file
   *   The file entity to upload.
   * @param array $settings
   *   File settings as specified in regular file field config. Contains keys:
   *   - file_directory: Where to upload the file
   *   - uri_scheme: Uri scheme to upload the file to (eg public://, private://)
   *   - file_extensions: List of valid file extensions (eg [xml, pdf])
   *   - max_filesize: Maximum allowed size of uploaded file.
   *
   * @return \Drupal\graphql\GraphQL\Response\FileUploadResponse
   *   The file upload response containing file entity or list of violations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \RuntimeException
   */
  public function saveFileUpload(UploadedFile $uploaded_file, array $settings): FileUploadResponse {
    $response = new FileUploadResponse();

    // Check for file upload errors and return FALSE for this file if a lower
    // level system error occurred.
    // @see http://php.net/manual/features.file-upload.errors.php.
    switch ($uploaded_file->getError()) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        // @todo Drupal 10.1 compatibility, needs to be converted to
        // ByteSizeMarkup later.
        // @phpstan-ignore-next-line
        $maxUploadSize = format_size($this->getMaxUploadSize($settings));
        $response->addViolation($this->t('The file @file could not be saved because it exceeds @maxsize, the maximum allowed size for uploads.', [
          '@file' => $uploaded_file->getClientOriginalName(),
          '@maxsize' => $maxUploadSize,
        ]));
        return $response;

      case UPLOAD_ERR_PARTIAL:
      case UPLOAD_ERR_NO_FILE:
        $response->addViolation($this->t('The file "@file" could not be saved because the upload did not complete.', [
          '@file' => $uploaded_file->getClientOriginalName(),
        ]));
        return $response;

      case UPLOAD_ERR_OK:
        // Final check that this is a valid upload, if it isn't, use the
        // default error handler.
        if ($uploaded_file->isValid()) {
          break;
        }

      default:
        $response->addViolation($this->t('Unknown error while uploading the file "@file".', ['@file' => $uploaded_file->getClientOriginalName()]));
        $this->logger->error('Error while uploading the file "@file" with an error code "@code".', [
          '@file' => $uploaded_file->getFilename(),
          '@code' => $uploaded_file->getError(),
        ]);
        return $response;
    }

    if (empty($settings['uri_scheme']) || empty($settings['file_directory'])) {
      throw new \RuntimeException('uri_scheme or file_directory missing in settings');
    }

    $destination = $this->getUploadLocation($settings);

    // Check the destination file path is writable.
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      $response->addViolation($this->t('Unknown error while uploading the file "@file".', ['@file' => $uploaded_file->getClientOriginalName()]));
      $this->logger->error('Could not create directory "@upload_directory".', ["@upload_directory" => $destination]);
      return $response;
    }

    $validators = $this->getUploadValidators($settings);

    $prepared_filename = $this->prepareFilename($uploaded_file->getClientOriginalName(), $validators);

    // Create the file.
    $file_uri = "{$destination}/{$prepared_filename}";

    $temp_file_path = $uploaded_file->getRealPath();

    $file_uri = $this->fileSystem->getDestinationFilename($file_uri, FileSystemInterface::EXISTS_RENAME);

    // Lock based on the prepared file URI.
    $lock_id = $this->generateLockIdFromFileUri($file_uri);

    if (!$this->lock->acquire($lock_id)) {
      $response->addViolation($this->t('Unknown error while uploading the file "@file".', ['@file' => $uploaded_file->getClientOriginalName()]));
      return $response;
    }

    try {
      // Begin building file entity.
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->fileStorage->create([]);
      $file->setOwnerId($this->currentUser->id());
      $file->setFilename($prepared_filename);
      $file->setMimeType($this->mimeTypeGuesser->guessMimeType($prepared_filename));
      $file->setFileUri($temp_file_path);
      // Set the size. This is done in File::preSave() but we validate the file
      // before it is saved.
      $file->setSize(@filesize($temp_file_path));

      // Validate against file_validate() first with the temporary path.
      // @todo Drupal 10.1 compatibility, needs to be converted to file validate
      // service later.
      // @phpstan-ignore-next-line
      $errors = file_validate($file, $validators);
      $maxResolution = $settings['max_resolution'] ?? 0;
      $minResolution = $settings['min_resolution'] ?? 0;
      if (!empty($maxResolution) || !empty($minResolution)) {
        $errors += $this->validateFileImageResolution($file, $maxResolution, $minResolution);
      }

      if (!empty($errors)) {
        $response->addViolations($errors);
        return $response;
      }

      $file->setFileUri($file_uri);
      // Move the file to the correct location after validation. Use
      // FileSystemInterface::EXISTS_ERROR as the file location has already been
      // determined above in FileSystem::getDestinationFilename().
      try {
        $this->fileSystem->move($temp_file_path, $file_uri, FileSystemInterface::EXISTS_ERROR);
      }
      catch (FileException $e) {
        $response->addViolation($this->t('Unknown error while uploading the file "@file".', [
          '@file' => $uploaded_file->getClientOriginalName(),
        ]));
        $this->logger->error('Unable to move file from "@file" to "@destination".', [
          '@file' => $uploaded_file->getRealPath(),
          '@destination' => $file->getFileUri(),
        ]);
        return $response;
      }

      // Validate the file entity against entity-level validation now after the
      // file has moved.
      if (!$this->validate($file, $validators, $response)) {
        return $response;
      }

      $file->save();

      $response->setFileEntity($file);
      return $response;
    }
    finally {
      // This will always be executed before any return statement or exception
      // in the try {} block.
      $this->lock->release($lock_id);
    }
  }

  /**
   * Validates uploaded files, saves them and returns a file upload response.
   *
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $uploaded_files
   *   The file entities to upload.
   * @param array $settings
   *   File settings as specified in regular file field config. Contains keys:
   *   - file_directory: Where to upload the file
   *   - uri_scheme: Uri scheme to upload the file to (eg public://, private://)
   *   - file_extensions: List of valid file extensions (eg [xml, pdf])
   *   - max_filesize: Maximum allowed size of uploaded file.
   *
   * @return \Drupal\graphql\GraphQL\Response\FileUploadResponse
   *   The file upload response containing file entities or list of violations.
   */
  public function saveMultipleFileUploads(array $uploaded_files, array $settings): FileUploadResponse {
    $response = new FileUploadResponse();
    foreach ($uploaded_files as $uploaded_file) {
      if (!$uploaded_file instanceof UploadedFile) {
        continue;
      }
      $file_upload_response = $this->saveFileUpload($uploaded_file, $settings);
      $file_entity = $file_upload_response->getFileEntity();
      if ($file_entity) {
        $response->setFileEntity($file_entity);
      }
      else {
        // If one file upload fails we need to delete any other uploaded files
        // before that. Avoids file orphans that don't belong to any entity.
        foreach ($response->getFileEntities() as $saved_file_entity) {
          $saved_file_entity->delete();
        }
        // Reset list of file entities as this is a violation response.
        $response->setFileEntities([]);
        $response->mergeViolations($file_upload_response);
        return $response;
      }
    }
    return $response;
  }

  /**
   * Validates the file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity to validate.
   * @param array $validators
   *   An array of upload validators to pass to file_validate().
   * @param \Drupal\graphql\GraphQL\Response\FileUploadResponse $response
   *   The response where validation errors will be added.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown when there are file validation errors.
   *
   * @return bool
   *   TRUE if validation was successful, FALSE otherwise.
   */
  protected function validate(FileInterface $file, array $validators, FileUploadResponse $response): bool {
    $violations = $file->validate();
    if ($violations->count()) {
      foreach ($violations as $violation) {
        $response->addViolation($violation->getMessage());
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Copy of file_validate_image_resolution() without creating messages.
   *
   * Verifies that image dimensions are within the specified maximum and
   * minimum.
   *
   * Non-image files will be ignored. If an image toolkit is available the image
   * will be scaled to fit within the desired maximum dimensions.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file entity. This function may resize the file affecting its size.
   * @param string|int $maximum_dimensions
   *   (optional) A string in the form WIDTHxHEIGHT; for example, '640x480' or
   *   '85x85'. If an image toolkit is installed, the image will be resized down
   *   to these dimensions. A value of zero (the default) indicates no
   *   restriction on size, so no resizing will be attempted.
   * @param string|int $minimum_dimensions
   *   (optional) A string in the form WIDTHxHEIGHT. This will check that the
   *   image meets a minimum size. A value of zero (the default) indicates that
   *   there is no restriction on size.
   *
   * @return array
   *   An empty array if the file meets the specified dimensions, was resized
   *   successfully to meet those requirements or is not an image. If the image
   *   does not meet the requirements or an attempt to resize it fails, an array
   *   containing the error message will be returned.
   */
  protected function validateFileImageResolution(FileInterface $file, $maximum_dimensions = 0, $minimum_dimensions = 0): array {
    $errors = [];

    // Check first that the file is an image.
    /** @var \Drupal\Core\Image\ImageInterface $image */
    $image = $this->imageFactory->get($file->getFileUri());

    if ($image->isValid()) {
      $scaling = FALSE;
      if ($maximum_dimensions) {
        // Check that it is smaller than the given dimensions.
        [$width, $height] = explode('x', $maximum_dimensions);
        if ($image->getWidth() > $width || $image->getHeight() > $height) {
          // Try to resize the image to fit the dimensions.
          if ($image->scale((int) $width, (int) $height)) {
            $scaling = TRUE;
            $image->save();
          }
          else {
            $errors[] = $this->t('The image exceeds the maximum allowed dimensions and an attempt to resize it failed.');
          }
        }
      }

      if ($minimum_dimensions) {
        // Check that it is larger than the given dimensions.
        [$width, $height] = explode('x', $minimum_dimensions);
        if ($image->getWidth() < $width || $image->getHeight() < $height) {
          if ($scaling) {
            $errors[] = $this->t('The resized image is too small. The minimum dimensions are %dimensions pixels and after resizing, the image size will be %widthx%height pixels.',
              [
                '%dimensions' => $minimum_dimensions,
                '%width' => $image->getWidth(),
                '%height' => $image->getHeight(),
              ]);
          }
          else {
            $errors[] = $this->t('The image is too small. The minimum dimensions are %dimensions pixels and the image size is %widthx%height pixels.',
              [
                '%dimensions' => $minimum_dimensions,
                '%width' => $image->getWidth(),
                '%height' => $image->getHeight(),
              ]);
          }
        }
      }
    }

    return $errors;
  }

  /**
   * Prepares the filename to strip out any malicious extensions.
   *
   * @param string $filename
   *   The file name.
   * @param array $validators
   *   The array of upload validators.
   *
   * @return string
   *   The prepared/munged filename.
   */
  protected function prepareFilename(string $filename, array &$validators): string {
    // Don't rename if 'allow_insecure_uploads' evaluates to TRUE.
    if (!$this->systemFileConfig->get('allow_insecure_uploads')) {
      if (!empty($validators['file_validate_extensions'][0])) {
        // If there is a file_validate_extensions validator and a list of
        // valid extensions, munge the filename to protect against possible
        // malicious extension hiding within an unknown file type. For example,
        // "filename.html.foo".
        $event = new FileUploadSanitizeNameEvent($filename, $validators['file_validate_extensions'][0]);
        $this->eventDispatcher->dispatch($event);
        $filename = $event->getFilename();
      }

      // Rename potentially executable files, to help prevent exploits (i.e.
      // will rename filename.php.foo and filename.php to filename._php._foo.txt
      // and filename._php.txt, respectively).
      if (preg_match(FileSystemInterface::INSECURE_EXTENSION_REGEX, $filename)) {
        // If the file will be rejected anyway due to a disallowed extension, it
        // should not be renamed; rather, we'll let file_validate_extensions()
        // reject it below.
        $passes_validation = FALSE;
        if (!empty($validators['file_validate_extensions'][0])) {
          /** @var \Drupal\file\FileInterface $file */
          $file = $this->fileStorage->create([]);
          $file->setFilename($filename);
          // @todo Drupal 10.1 compatibility, needs to be converted to file
          // validator service later.
          // @phpstan-ignore-next-line
          $passes_validation = empty(file_validate_extensions($file, $validators['file_validate_extensions'][0]));
        }
        if (empty($validators['file_validate_extensions'][0]) || $passes_validation) {
          if ((substr($filename, -4) != '.txt')) {
            // The destination filename will also later be used to create the
            // URI.
            $filename .= '.txt';
          }

          $event = new FileUploadSanitizeNameEvent($filename, $validators['file_validate_extensions'][0] ?? '');
          $this->eventDispatcher->dispatch($event);
          $filename = $event->getFilename();

          // The .txt extension may not be in the allowed list of extensions. We
          // have to add it here or else the file upload will fail.
          if (!empty($validators['file_validate_extensions'][0])) {
            $validators['file_validate_extensions'][0] .= ' txt';
          }
        }
      }
    }
    return $filename;
  }

  /**
   * Determines the URI for a file field.
   *
   * @param array $settings
   *   The array of field settings.
   *
   * @return string
   *   An un-sanitized file directory URI with tokens replaced. The result of
   *   the token replacement is then converted to plain text and returned.
   */
  protected function getUploadLocation(array $settings): string {
    $destination = trim($settings['file_directory'], '/');

    // Replace tokens first. This might produce cacheable metadata if tokens
    // are used in the path. As this service is intended to be used in mutations
    // which are not cached at all, it's enough to just catch leaked metadata
    // and skip including them in current GraphQL field's context.
    $context = new RenderContext();
    $destination = $this->renderer->executeInRenderContext($context, function () use ($destination): string {
      return $this->token->replace($destination, []);
    });

    // As the tokens might contain HTML we convert it to plain text.
    $destination = PlainTextOutput::renderFromHtml($destination);
    return $settings['uri_scheme'] . '://' . $destination;
  }

  /**
   * Retrieves the upload validators for the given file field settings.
   *
   * This is copied from \Drupal\file\Plugin\Field\FieldType\FileItem as there
   * is no entity instance available here that a FileItem would exist for.
   *
   * @param array $settings
   *   The file field settings.
   *
   * @return array
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  protected function getUploadValidators(array $settings): array {
    $validators = [
      // Add in our check of the file name length.
      'file_validate_name_length' => [],
    ];

    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toNumber(Environment::getUploadMaxSize());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toNumber($settings['max_filesize']));
    }

    // There is always a file size limit due to the PHP server limit.
    $validators['file_validate_size'] = [$max_filesize];

    // Add the extension check if necessary.
    if (!empty($settings['file_extensions'])) {
      $validators['file_validate_extensions'] = [$settings['file_extensions']];
    }

    return $validators;
  }

  /**
   * Generates a lock ID based on the file URI.
   *
   * @param string $file_uri
   *   The file URI.
   *
   * @return string
   *   The generated lock ID.
   */
  protected static function generateLockIdFromFileUri(string $file_uri): string {
    return 'file:rest:' . Crypt::hashBase64($file_uri);
  }

}
