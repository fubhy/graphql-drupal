<?php

namespace Drupal\Tests\graphql\Traits;

use Drupal\graphql\Entity\ServerInterface;
use GraphQL\Utils\SchemaPrinter;

/**
 * Trait to get printed version of the schema.
 */
trait SchemaPrinterTrait {

  /**
   * Gets printed version of the schema.
   *
   * @param \Drupal\graphql\Entity\ServerInterface $server
   *   The server id.
   *
   * @return string
   *   The printed version of the schema.
   */
  protected function getPrintedSchema(?ServerInterface $server = NULL) {
    $server = $server ?? $this->server;
    /** @var \GraphQL\Server\ServerConfig $config */
    $config = $server->configuration();
    $schema = $config->getSchema();
    return SchemaPrinter::doPrint($schema);
  }

}
