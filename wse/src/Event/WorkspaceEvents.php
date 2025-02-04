<?php

namespace Drupal\wse\Event;

/**
 * Defines events for the WSE module.
 */
final class WorkspaceEvents {

  /**
   * Name of the event fired before a workspace is reverted.
   *
   * @Event
   *
   * @see \Drupal\wse\Event\WorkspaceRevertEvent
   */
  const WORKSPACE_PRE_REVERT = 'wse.workspace.pre_revert';

  /**
   * Name of the event fired after a workspace is reverted.
   *
   * @Event
   *
   * @see \Drupal\wse\Event\WorkspaceRevertEvent
   */
  const WORKSPACE_POST_REVERT = 'wse.workspace.post_revert';

}
