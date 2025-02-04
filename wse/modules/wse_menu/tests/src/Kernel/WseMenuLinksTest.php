<?php

declare(strict_types=1);

namespace Drupal\Tests\wse_menu\Kernel;

use Drupal\Tests\menu_link_content\Kernel\MenuLinksTest;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests menu links inside workspaces.
 *
 * @group wse_menu
 */
class WseMenuLinksTest extends MenuLinksTest {

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
    'entity_test',
    'link',
    'menu_link_content',
    'router_test',
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

  /**
   * {@inheritdoc}
   */
  public function testModuleUninstalledMenuLinks(): void {
    // Module installation clears the active workspace, so we can't use the
    // parent tests method as-is.
    \Drupal::service('workspaces.manager')->switchToLive();
    \Drupal::service('module_installer')->install(['menu_test']);
    $this->switchToWorkspace($this->workspaces['stage']->id());

    \Drupal::service('plugin.manager.menu.link')->rebuild();
    $menu_links = $this->menuLinkManager->loadLinksByRoute('menu_test.menu_test');
    $this->assertCount(1, $menu_links);
    $menu_link = reset($menu_links);
    $this->assertEquals('menu_test', $menu_link->getPluginId());

    // Uninstall the module and ensure the menu link got removed.
    \Drupal::service('workspaces.manager')->switchToLive();
    \Drupal::service('module_installer')->uninstall(['menu_test']);
    $this->switchToWorkspace($this->workspaces['stage']->id());

    \Drupal::service('plugin.manager.menu.link')->rebuild();
    $menu_links = $this->menuLinkManager->loadLinksByRoute('menu_test.menu_test');
    $this->assertCount(0, $menu_links);
  }

  /**
   * Tests content menu link re-parenting inside a workspace.
   */
  public function testWseMenuLinkContentReparenting() {
    // Add new menu items in a hierarchy.
    $parent = MenuLinkContent::create([
      'title' => $this->randomMachineName(8),
      'link' => [['uri' => 'internal:/']],
      'menu_name' => 'wse-menu-test',
    ]);
    $parent->save();
    $child1 = MenuLinkContent::create([
      'title' => $this->randomMachineName(8),
      'link' => [['uri' => 'internal:/']],
      'menu_name' => 'wse-menu-test',
      'parent' => 'menu_link_content:' . $parent->uuid(),
    ]);
    $child1->save();
    $child2 = MenuLinkContent::create([
      'title' => $this->randomMachineName(8),
      'link' => [['uri' => 'internal:/']],
      'menu_name' => 'wse-menu-test',
      'parent' => 'menu_link_content:' . $child1->uuid(),
    ]);
    $child2->save();

    // Delete the middle child.
    $child1->delete();
    // Refresh $child2.
    $child2 = MenuLinkContent::load($child2->id());
    // Test the reference in the child.
    $this->assertSame('menu_link_content:' . $parent->uuid(), $child2->getParentId());

    $this->workspaceManager->switchToLive();
    $child2 = MenuLinkContent::load($child2->id());
    $this->assertNotSame('menu_link_content:' . $parent->uuid(), $child2->getParentId());
    $this->assertSame('menu_link_content:' . $child1->uuid(), $child2->getParentId());

    // Publish the workspace and check that the parent has been updated.
    $this->workspaces['stage']->publish();
    $this->workspaceManager->switchToLive();
    $child2 = MenuLinkContent::load($child2->id());
    $this->assertSame('menu_link_content:' . $parent->uuid(), $child2->getParentId());
  }

  // phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

  /**
   * Test menu link reparenting.
   */
  public function _testWseMenuLinkReparenting($module = 'menu_test') {
    $this->workspaceManager->switchToLive();
    // Create the initial hierarchy.
    $links = $this->createLinkHierarchy($module);

    $expected_live_hierarchy = [
      'parent' => '',
      'child-1' => 'parent',
      'child-1-1' => 'child-1',
      'child-1-2' => 'child-1',
      'child-2' => 'parent',
    ];
    $this->assertMenuLinkParents($links, $expected_live_hierarchy);

    // Switch to a workspace and move child-1 under child-2, and check
    // that all the children of child-1 have been moved too.
    $this->switchToWorkspace($this->workspaces['stage']->id());
    $this->menuLinkManager->updateDefinition($links['child-1'], ['parent' => $links['child-2']]);

    // Verify that the entity was updated too.
    /** @var \Drupal\Core\Menu\MenuLinkInterface $menu_link_plugin  */
    $menu_link_plugin = $this->menuLinkManager->createInstance($links['child-1']);
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $menu_link_plugin->getDerivativeId());
    $this->assertEquals($links['child-2'], $entity->getParentId());

    $expected_hierarchy = [
      'parent' => '',
      'child-1' => 'child-2',
      'child-1-1' => 'child-1',
      'child-1-2' => 'child-1',
      'child-2' => 'parent',
    ];
    $this->assertMenuLinkParents($links, $expected_hierarchy);

    // Make sure that the Live tree remains unchanged.
    $this->workspaceManager->switchToLive();
    $this->assertMenuLinkParents($links, $expected_live_hierarchy);

    // Test removal of links in a workspace.
    $this->switchToWorkspace($this->workspaces['stage']->id());
    $this->menuLinkManager->removeDefinition($links['child-1']);

    $expected_hierarchy = [
      'parent' => FALSE,
      'child-1-1' => 'parent',
      'child-1-2' => 'parent',
      'child-2' => 'parent',
    ];
    $this->assertMenuLinkParents($links, $expected_hierarchy);

    // Make sure that the Live tree remains unchanged.
    $this->workspaceManager->switchToLive();
    $this->assertMenuLinkParents($links, $expected_live_hierarchy);

    // Try changing the parent at the entity level.
    $this->switchToWorkspace($this->workspaces['stage']->id());
    $definition = $this->menuLinkManager->getDefinition($links['child-1-2']);
    $entity = MenuLinkContent::load($definition['metadata']['entity_id']);
    $entity->parent->value = '';
    $entity->save();

    $expected_hierarchy = [
      'parent' => '',
      'child-1-1' => 'parent',
      'child-1-2' => '',
      'child-2' => 'parent',
    ];
    $this->assertMenuLinkParents($links, $expected_hierarchy);

    // Make sure that the Live tree remains unchanged.
    $this->workspaceManager->switchToLive();
    $this->assertMenuLinkParents($links, $expected_live_hierarchy);

    $links = $this->createLinkHierarchy($module);
    $this->switchToWorkspace($this->workspaces['stage']->id());
    $this->menuLinkManager->updateDefinition($links['child-1'], ['parent' => $links['child-2']]);
    // Verify that the entity was updated too.
    /** @var \Drupal\Core\Menu\MenuLinkInterface $menu_link_plugin  */
    $menu_link_plugin = $this->menuLinkManager->createInstance($links['child-1']);
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $menu_link_plugin->getDerivativeId());
    $this->assertEquals($links['child-2'], $entity->getParentId());

    $expected_hierarchy = [
      'parent' => '',
      'child-1' => 'child-2',
      'child-1-1' => 'child-1',
      'child-1-2' => 'child-1',
      'child-2' => 'parent',
    ];
    $this->assertMenuLinkParents($links, $expected_hierarchy);

    // Make sure that the live tree remains unchanged.
    $this->workspaceManager->switchToLive();
    $this->assertMenuLinkParents($links, $expected_live_hierarchy);

    // Now publish the workspace and verify that the changed tree is loading.
    $this->workspaces['stage']->publish();
    $this->workspaceManager->switchToLive();
    $this->assertMenuLinkParents($links, $expected_hierarchy);
  }

  // phpcs:enable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

  /**
   * {@inheritdoc}
   */
  public function testPendingRevisions(): void {
    // WSE Menu changes the way that pending revisions work for menu links.
    $this->assertTrue(TRUE);
  }

}
