<?php

declare(strict_types=1);

namespace Drupal\Tests\wse_menu\Kernel;

use Drupal\KernelTests\Core\Menu\MenuLinkTreeTest;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests menu link trees inside workspaces.
 *
 * @group wse_menu
 */
class WseMenuLinkTreeTest extends MenuLinkTreeTest {

  use UserCreationTrait;
  use WorkspaceTestTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'system',
    'user',
    'workspaces',
    'wse',
    'wse_menu',
    'wse_menu_test',
    'options',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->workspaceManager = \Drupal::service('workspaces.manager');

    $this->installSchema('workspaces', ['workspace_association']);
    $this->installEntitySchema('workspace');

    $this->workspaces['stage'] = Workspace::create(['id' => 'stage', 'label' => 'Stage']);
    $this->workspaces['stage']->save();

    $permissions = array_intersect([
      'administer nodes',
      'create workspace',
      'edit any workspace',
      'view any workspace',
    ], array_keys($this->container->get('user.permissions')->getPermissions()));
    $this->setCurrentUser($this->createUser($permissions));

    // Activate the Stage workspace, so all test methods of the parent class run
    // in a workspace context.
    $this->switchToWorkspace($this->workspaces['stage']->id());
  }

}
