<?php

namespace Drupal\wse_deploy\Event;

use Drupal\workspaces\WorkspaceInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines the workspace export event.
 *
 * @see \Drupal\wse_deploy\Event\WorkspaceDeployEvents
 */
class WorkspaceExportEvent extends Event {

  /**
   * The workspace being exported.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $workspace;

  /**
   * A list of information about the entities that are exported.
   *
   * See \Drupal\wse_deploy\WorkspaceExporter::exportToJson() for the structure.
   *
   * @var array
   */
  protected $indexData;

  /**
   * A list of information about the files that are exported.
   *
   * See \Drupal\wse_deploy\WorkspaceExporter::exportToJson() for the structure.
   *
   * @var array
   */
  protected $indexFiles;

  /**
   * Constructs a new WorkspaceExportEvent.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace.
   * @param array $index_data
   *   A list of information about the entities that are exported.
   * @param array $index_files
   *   A list of information about the files that are exported.
   */
  public function __construct(WorkspaceInterface $workspace, array $index_data, array $index_files) {
    $this->workspace = $workspace;
    $this->indexData = $index_data;
    $this->indexFiles = $index_files;
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
   * Gets the list of information about the entities that have been exported.
   *
   * @return array
   *   Information about the entities.
   */
  public function getIndexData() {
    return $this->indexData;
  }

  /**
   * Gets the list of information about the files that have been exported.
   *
   * @return array
   *   Information about the files.
   */
  public function getIndexFiles() {
    return $this->indexFiles;
  }

}
