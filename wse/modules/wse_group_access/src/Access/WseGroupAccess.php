<?php

namespace Drupal\wse_group_access\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks whether the current user has group access to a workspace.
 *
 * Requirements key: '_wse_group_access'.
 */
class WseGroupAccess implements AccessInterface {

  /**
   * Checks workspace group access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $workspace = $route_match->getParameter('workspace');

    $requirement = filter_var($route->getRequirement('_wse_group_access'), FILTER_VALIDATE_BOOLEAN);
    $actual_status = wse_group_access_check($workspace, $account);

    return AccessResult::allowedIf($requirement === $actual_status)
      ->addCacheableDependency($workspace)
      ->cachePerUser();
  }

}
