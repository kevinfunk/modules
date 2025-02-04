<?php

namespace Drupal\wse_menu;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Ensures that toolbar tree actions are always executed outside a workspace.
 */
class WseToolbarMenuLinkTree implements MenuLinkTreeInterface {

  /**
   * Constructs a WseToolbarMenuLinkTree object.
   */
  public function __construct(
    protected MenuLinkTreeInterface $inner,
    protected WorkspaceManagerInterface $workspaceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCurrentRouteMenuTreeParameters($menu_name) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($menu_name) {
      return $this->inner->getCurrentRouteMenuTreeParameters($menu_name);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function load($menu_name, MenuTreeParameters $parameters) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($menu_name, $parameters) {
      return $this->inner->load($menu_name, $parameters);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function transform(array $tree, array $manipulators) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($tree, $manipulators) {
      return $this->inner->transform($tree, $manipulators);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $tree) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($tree) {
      return $this->inner->build($tree);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function maxDepth() {
    return $this->workspaceManager->executeOutsideWorkspace(function () {
      return $this->inner->maxDepth();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtreeHeight($id) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($id) {
      return $this->inner->getSubtreeHeight($id);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getExpanded($menu_name, array $parents) {
    return $this->workspaceManager->executeOutsideWorkspace(function () use ($menu_name, $parents) {
      return $this->inner->getExpanded($menu_name, $parents);
    });
  }

}
