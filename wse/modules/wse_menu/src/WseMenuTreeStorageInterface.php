<?php

namespace Drupal\wse_menu;

use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Defines an interface for storing workspace-specific menu trees.
 */
interface WseMenuTreeStorageInterface extends MenuTreeStorageInterface {

  /**
   * Creates and populates the workspace-specific menu tree.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace for which to rebuild the menu tree.
   * @param bool $replay_changes
   *   (optional) Whether to replay the menu tree changes. Defaults to TRUE.
   */
  public function rebuildWorkspaceMenuTree(WorkspaceInterface $workspace, bool $replay_changes = TRUE): void;

  /**
   * Deletes the workspace-specific menu tree.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace for which to delete the menu tree.
   */
  public function cleanupWorkspaceMenuTree(WorkspaceInterface $workspace): void;

  /**
   * Gets the ID of all workspaces that have a workspace-specific menu tree.
   *
   * @return string[]
   *   An array of workspace IDs.
   */
  public function getAllWorkspacesWithMenuTreeOverrides(): array;

}
