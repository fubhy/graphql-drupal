<?php

declare(strict_types=1);

namespace Drupal\graphql\GraphQL\Response;

use Drupal\file\FileInterface;

/**
 * A response that either has a file entity or some violations.
 */
class FileUploadResponse extends Response {

  /**
   * The file entities in case of successful file upload.
   *
   * @var \Drupal\file\FileInterface[]
   */
  protected $fileEntities = [];

  /**
   * Sets file entity.
   *
   * @param \Drupal\file\FileInterface $fileEntity
   *   File entity.
   */
  public function setFileEntity(FileInterface $fileEntity): void {
    $this->fileEntities[] = $fileEntity;
  }

  /**
   * Sets file entities.
   *
   * @param \Drupal\file\FileInterface[] $fileEntities
   *   File entities.
   */
  public function setFileEntities(array $fileEntities): void {
    $this->fileEntities = $fileEntities;
  }

  /**
   * Get the first file entity if there is one.
   *
   * @return \Drupal\file\FileInterface|null
   *   First file entity or NULL.
   */
  public function getFileEntity(): ?FileInterface {
    return $this->fileEntities[0] ?? NULL;
  }

  /**
   * Get the file entities.
   *
   * @return \Drupal\file\FileInterface[]
   *   File entities.
   */
  public function getFileEntities(): array {
    return $this->fileEntities;
  }

}
