<?php

namespace Drupal\wse\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Defines the workspace revert event.
 *
 * @see \Drupal\wse\Event\WorkspaceEvents
 */
class WorkspaceRevertEvent extends Event {

  /**
   * The workspace being reverted.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $workspace;

  /**
   * An array of revision ID => entity ID pairs, keyed by entity type IDs.
   *
   * @var array
   */
  protected $revertToRevisions;

  /**
   * An array of revision ID => entity ID pairs, keyed by entity type IDs.
   *
   * @var array
   */
  protected $revertFromRevisions;

  /**
   * Constructs a new WorkspaceRevertEvent.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace.
   * @param array $revert_to_revisions
   *   An array of revision IDs which are being set as default revisions by the
   *   revert operation.
   * @param array $revert_from_revisions
   *   An array of revision IDs which were previously published by this
   *   workspace.
   */
  public function __construct(WorkspaceInterface $workspace, array $revert_to_revisions, array $revert_from_revisions) {
    $this->workspace = $workspace;
    $this->revertToRevisions = $revert_to_revisions;
    $this->revertFromRevisions = $revert_from_revisions;
  }

  /**
   * Gets the workspace.
   *
   * @return \Drupal\workspaces\WorkspaceInterface
   *   The workspace.
   */
  public function getWorkspace() {
    return $this->workspace;
  }

  /**
   * Gets an array of "revert to" revisions.
   *
   * @return array
   *   An array of revision ID => entity ID pairs, keyed by entity type IDs.
   */
  public function getRevertToRevisions() {
    return $this->revertToRevisions;
  }

  /**
   * Gets an array of "revert from" revisions.
   *
   * @return array
   *   An array of revision ID => entity ID pairs, keyed by entity type IDs.
   */
  public function getRevertFromRevisions() {
    return $this->revertFromRevisions;
  }

}
