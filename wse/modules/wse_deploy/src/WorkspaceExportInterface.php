<?php

namespace Drupal\wse_deploy;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Defines an interface for workspace export plugins.
 *
 * A workspace export provides a way to get the contents of a workspace from a
 * source (usually the current site) to a destination.
 *
 * @see \Drupal\wse_deploy\Annotation\WorkspaceExport
 * @see \Drupal\wse_deploy\WorkspaceExportBase
 * @see \Drupal\wse_deploy\WorkspaceExportPluginManager
 * @see plugin_api
 */
interface WorkspaceExportInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * Verifies whether the export plugin can be used.
   *
   * @return bool
   *   TRUE if the plugin can be used, FALSE otherwise.
   */
  public static function isAvailable();

  /**
   * Exports the contents of a workspace to a remote destination.
   */
  public function onWorkspaceExport(WorkspaceInterface $workspace, array $index_data, array $index_files);

  /**
   * Acts when a workspace is published on the source.
   */
  public function onWorkspacePublish(WorkspaceInterface $workspace);

  /**
   * Acts when a workspace is reverted on the source.
   */
  public function onWorkspaceRevert(WorkspaceInterface $workspace);

}
