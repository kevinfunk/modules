<?php

/**
 * @file
 * Post update functions for WSE.
 */

/**
 * Set the status for existing workspaces.
 */
function wse_post_update_set_status_field() {
  /** @var \Drupal\workspaces\WorkspaceInterface[] $workspaces */
  $workspaces = \Drupal::entityTypeManager()->getStorage('workspace')->loadMultiple();
  foreach ($workspaces as $workspace) {
    $state = NULL;
    if ($workspace->hasField('entity_workflow_workspace')) {
      $state = $workspace->get('entity_workflow_workspace')->value;
    }
    elseif ($workspace->hasField('state')) {
      $state = $workspace->get('state')->value;
    }

    if (in_array($state, ['archived', 'scheduled'], TRUE)) {
      $workspace->set('status', WSE_STATUS_CLOSED);
    }
    else {
      $workspace->set('status', WSE_STATUS_OPEN);
    }

    $workspace->save();
  }
}
