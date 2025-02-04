<?php

namespace Drupal\wse_deploy\Event;

/**
 * Defines events for the WSE deploy module.
 */
final class WorkspaceDeployEvents {

  /**
   * Name of the event fired before a workspace is exported.
   *
   * @Event
   *
   * @see \Drupal\wse_deploy\Event\WorkspaceExportEvent
   */
  const WORKSPACE_PRE_EXPORT = 'wse.workspace.pre_export';

  /**
   * Name of the event fired after a workspace is exported.
   *
   * @Event
   *
   * @see \Drupal\wse_deploy\Event\WorkspaceExportEvent
   */
  const WORKSPACE_POST_EXPORT = 'wse.workspace.post_export';

}
