<?php

namespace Drupal\wse_menu;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Decorates the menu link manager to provide workspace-specific operations.
 */
class WseMenuLinkManager implements MenuLinkManagerInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected MenuLinkManagerInterface $inner,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WseMenuTreeStorageInterface $treeStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return $this->inner->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild() {
    $this->inner->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->inner->getDefinition($plugin_id, $exception_on_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $this->inner->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->inner->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->inner->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLinksInMenu($menu_name) {
    $this->inner->deleteLinksInMenu($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function removeDefinition($id, $persist = TRUE) {
    $this->inner->removeDefinition($id, $persist);

    // When a menu item is deleted, ensure that it is also removed from all the
    // possible menu trees.
    $this->workspaceManager->executeOutsideWorkspace(function () use ($id) {
      $this->inner->removeDefinition($id, FALSE);
    });
    foreach ($this->treeStorage->getAllWorkspacesWithMenuTreeOverrides() as $workspace_id) {
      $this->workspaceManager->executeInWorkspace($workspace_id, function () use ($id) {
        $this->inner->removeDefinition($id, FALSE);
      });
    }
  }

  /**
   * {@inheritdoc}
   */
  public function menuNameInUse($menu_name) {
    return $this->inner->menuNameInUse($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function countMenuLinks($menu_name = NULL) {
    return $this->inner->countMenuLinks($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentIds($id) {
    return $this->inner->getParentIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildIds($id) {
    return $this->inner->getChildIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadLinksByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    return $this->inner->loadLinksByRoute($route_name, $route_parameters, $menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function addDefinition($id, array $definition) {
    return $this->inner->addDefinition($id, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function updateDefinition($id, array $new_definition_values, $persist = TRUE) {
    return $this->inner->updateDefinition($id, $new_definition_values, $persist);
  }

  /**
   * {@inheritdoc}
   */
  public function resetLink($id) {
    return $this->inner->resetLink($id);
  }

  /**
   * {@inheritdoc}
   */
  public function resetDefinitions() {
    $this->inner->resetDefinitions();
  }

}
