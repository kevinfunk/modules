<?php

namespace Drupal\wse_deploy\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse\WorkspaceReverter;
use Drupal\wse_deploy\WorkspaceImporter;
use Drush\Commands\DrushCommands;

/**
 * Drush commands to handle workspace deployment flow.
 */
class WseDeployCommands extends DrushCommands {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The workspace importer.
   *
   * @var \Drupal\wse_deploy\WorkspaceImporter
   */
  protected WorkspaceImporter $workspaceImporter;

  /**
   * The workspace reverter.
   *
   * @var \Drupal\wse\WorkspaceReverter
   */
  protected WorkspaceReverter $workspaceReverter;

  /**
   * WseDeployCommands constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceImporter $workspace_importer, WorkspaceReverter $workspace_reverter) {
    parent::__construct();

    $this->workspaceImporter = $workspace_importer;
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceReverter = $workspace_reverter;
  }

  /**
   * Imports the specified workspace.
   *
   * @param string $path
   *   The path of the workspace export.
   *
   * @command wse-deploy-workspace-import
   * @description Imports the specified workspace.
   * @aliases wse-wi
   * @bootstrap full
   */
  public function importWorkspace(string $path): void {
    $this->workspaceImporter->importWorkspace($path);
  }

  /**
   * Publishes the specified workspace.
   *
   * @param string $id
   *   The workspace ID.
   *
   * @command wse-deploy-workspace-publish
   * @description Publishes the specified workspace.
   * @aliases wse-wp
   * @bootstrap full
   */
  public function publishWorkspace(string $id): void {
    $workspace = $this->entityTypeManager
      ->getStorage('workspace')
      ->load($id);

    if ($workspace instanceof WorkspaceInterface) {
      try {
        $workspace->publish();
      }
      catch (\Exception $e) {
        $this->logger()->error('Workspace publication failed.');
      }
    }
    else {
      $this->logger()->error('Workspace not found.');
    }
  }

  /**
   * Reverts the specified workspace.
   *
   * @param string $id
   *   The workspace ID.
   *
   * @command wse-deploy-workspace-revert
   * @description Reverts the specified workspace.
   * @aliases wse-wr
   * @bootstrap full
   */
  public function revertWorkspace(string $id): void {
    $workspace = $this->entityTypeManager
      ->getStorage('workspace')
      ->load($id);

    if ($workspace instanceof WorkspaceInterface) {
      try {
        $import_path = \Drupal::config('wse_deploy.settings')->get('deploy_path') . '/import/' . $workspace->id();
        $this->workspaceImporter->revertImportedWorkspace($workspace, $import_path);
      }
      catch (\Exception $e) {
        $this->logger()->error('Workspace revert failed.');
      }
    }
    else {
      $this->logger()->error('Workspace not found.');
    }
  }

}
