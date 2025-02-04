<?php

namespace Drupal\wse_menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides the wse_menu implementation of the active menu trail service.
 */
class WseMenuActiveTrail extends MenuActiveTrail {

  /**
   * Constructs a WseMenuActiveTrail object.
   */
  public function __construct(
    MenuLinkManagerInterface $menu_link_manager,
    RouteMatchInterface $route_match,
    CacheBackendInterface $cache,
    LockBackendInterface $lock,
    protected WorkspaceManagerInterface $workspaceManager,
  ) {
    parent::__construct($menu_link_manager, $route_match, $cache, $lock);
  }

  /**
   * {@inheritdoc}
   *
   * @see ::getActiveTrailIds()
   */
  protected function getCid() {
    parent::getCid();

    if ($workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->cid = $workspace->id() . ':' . $this->cid;
    }

    return $this->cid;
  }

}
