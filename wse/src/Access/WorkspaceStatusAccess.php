<?php

namespace Drupal\wse\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks whether a workspace status is either 'open' or 'closed'.
 *
 * Requirements key: '_workspace_status'.
 */
class WorkspaceStatusAccess implements AccessInterface {

  /**
   * Checks workspace status access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match) {
    $requirement = $route->getRequirement('_workspace_status');
    $workspace = $route_match->getParameter('workspace');

    return AccessResult::allowedIf(wse_workspace_get_status($workspace) === $requirement);
  }

}
