<?php

namespace Drupal\graphql_menu_links_test\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access callback for the /graphql-protected test route.
 */
class TestAccessCheck {

  /**
   * Access callback that only returns TRUE for the super_admin user.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if ($account->getAccountName() === 'super_admin') {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden()->cachePerUser();
  }

}
