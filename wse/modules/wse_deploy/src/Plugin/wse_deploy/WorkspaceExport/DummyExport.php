<?php

namespace Drupal\wse_deploy\Plugin\wse_deploy\WorkspaceExport;

use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse_deploy\WorkspaceExportBase;

/**
 * Defines a testing workspace export plugin.
 *
 * @WorkspaceExport(
 *   id = "dummy",
 *   label = @Translation("Dummy")
 * )
 */
class DummyExport extends WorkspaceExportBase {

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspaceExport(WorkspaceInterface $workspace, array $index_data, array $index_files) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspacePublish(WorkspaceInterface $workspace) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspaceRevert(WorkspaceInterface $workspace) {
    // Nothing to do here.
  }

}
