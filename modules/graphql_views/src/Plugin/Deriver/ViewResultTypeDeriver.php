<?php

namespace Drupal\graphql_views\Plugin\Deriver;

use Drupal\graphql\Utility\StringHelper;
use Drupal\views\Views;

/**
 * Derive fields from configured views.
 */
class ViewResultTypeDeriver extends ViewDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    foreach (Views::getApplicableViews('graphql_display') as list($viewId, $displayId)) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      $id = implode('-', [$viewId, $displayId, 'result']);

      $this->derivatives[$id] = [
        'id' => $id,
        'name' => StringHelper::camelCase([$viewId, $displayId, 'result']),
        'view' => $viewId,
        'display' => $displayId,
      ] + $basePluginDefinition;
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

}
