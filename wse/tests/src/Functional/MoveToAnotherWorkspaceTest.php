<?php

declare(strict_types=1);

namespace Drupal\Tests\wse\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UiHelperTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Move to another workspace test.
 *
 * @group wse
 */
class MoveToAnotherWorkspaceTest extends BrowserTestBase {

  use UiHelperTrait;
  use WseTestUtilities;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field_ui',
    'node',
    'taxonomy',
    'toolbar',
    'user',
    'options',
    'workspaces',
    'wse',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'label' => 'Article']);
    $this->setupWorkspaceSwitcherBlock();

    $permissions = [
      'create workspace',
      'edit own workspace',
      'view own workspace',
      'bypass entity access own workspace',
    ];
    $admin = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin);
  }

  /**
   * Test callback.
   */
  public function testMoveToAnotherWorkspaceEntityOperation(): void {
    // Create two workspaces.
    $source_workspace = $this->wseCreateAndActivateWorkspaceThroughUi('Source', 'source');
    $destination_workspace = $this->wseCreateWorkspaceThroughUi('Destination', 'destination');

    // Create a node in the workspace.
    $original_node = $this->createNodeThroughUi('Spock', 'article');
    $workspace_association = \Drupal::service('workspaces.association');
    $source_tracked = $workspace_association->getTrackedEntities($source_workspace->id());
    $this->assertEquals($source_tracked['node'][2], $original_node->id());

    // Move node to another workspace.
    $this->drupalGet(Url::fromRoute('entity.node.move_to_workspace', [
      'node' => $original_node->id(),
      'source_workspace' => $source_workspace->id(),
    ])->toString());
    $this->submitForm([
      'target_workspace' => $destination_workspace->id(),
    ], 'Confirm');

    $this->assertEmpty($workspace_association->getTrackedEntities($source_workspace->id()));
    $destination_tracked = $workspace_association->getTrackedEntities($destination_workspace->id());
    $this->assertEquals($destination_tracked['node'][2], $original_node->id());
  }

}
