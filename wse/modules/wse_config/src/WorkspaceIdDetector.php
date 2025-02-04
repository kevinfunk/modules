<?php

namespace Drupal\wse_config;

/**
 * Provides a lightweight service for detecting the current workspace ID.
 */
class WorkspaceIdDetector {

  /**
   * The currently active workspace ID.
   */
  protected ?string $activeWorkspaceId = NULL;

  /**
   * Retrieves the active workspace ID.
   *
   * @return string|null
   *   The ID of the active workspace, or NULL if there is none.
   */
  public function getActiveWorkspaceId() {
    return $this->activeWorkspaceId;
  }

  /**
   * Sets the active workspace ID.
   *
   * @param string|null $workspace_id
   *   The active workspace ID.
   */
  public function setActiveWorkspaceId(?string $workspace_id) {
    $this->activeWorkspaceId = $workspace_id;
  }

}
