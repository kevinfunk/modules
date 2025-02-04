<?php

declare(strict_types=1);

namespace Drupal\Tests\wse\Functional;

use Drupal\Tests\workspaces\Functional\WorkspaceTestUtilities;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Utility methods for use in BrowserTestBase tests.
 *
 * This trait will not work if not used in a child of BrowserTestBase.
 */
trait WseTestUtilities {

  use WorkspaceTestUtilities;

  /**
   * Creates and activates a new Workspace through the UI.
   *
   * @param string $label
   *   The label of the workspace to create.
   * @param string $id
   *   The ID of the workspace to create.
   * @param string $parent
   *   (optional) The ID of the parent workspace. Defaults to '_none'.
   *
   * @return \Drupal\workspaces\WorkspaceInterface
   *   The workspace that was just created.
   */
  protected function wseCreateAndActivateWorkspaceThroughUi(string $label, string $id, string $parent = '_none'): WorkspaceInterface {
    $this->drupalGet('/admin/config/workflow/workspaces/add');
    $this->submitForm([
      'id' => $id,
      'label' => $label,
      'parent' => $parent,
    ], 'Save and switch');

    $entities = \Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => $label]);
    $workspace = reset($entities);

    $this->getSession()->getPage()->hasContent("$label ({$workspace->id()})");

    return $workspace;
  }

  /**
   * Creates a new Workspace through the UI.
   *
   * @param string $label
   *   The label of the workspace to create.
   * @param string $id
   *   The ID of the workspace to create.
   * @param string $parent
   *   (optional) The ID of the parent workspace. Defaults to '_none'.
   *
   * @return \Drupal\workspaces\WorkspaceInterface
   *   The workspace that was just created.
   */
  protected function wseCreateWorkspaceThroughUi($label, $id, $parent = '_none'): WorkspaceInterface {
    $this->drupalGet('/admin/config/workflow/workspaces/add');
    $this->submitForm([
      'id' => $id,
      'label' => $label,
      'parent' => $parent,
    ], 'Save');

    $entities = \Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => $label]);
    $workspace = reset($entities);

    $this->getSession()->getPage()->hasContent("$label ({$workspace->id()})");

    return $workspace;
  }

}
