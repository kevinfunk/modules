<?php

namespace Drupal\Tests\wse_menu\Kernel;

use Drupal\KernelTests\Core\Menu\MenuTreeStorageTest;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests the WSE menu tree storage.
 *
 * @group wse_menu
 */
class WseMenuTreeStorageTest extends MenuTreeStorageTest {

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

    $this->installSchema('user', ['users_data']);
    $this->installSchema('workspaces', ['workspace_association']);
    $this->installEntitySchema('user');
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

    // Swap the tree storage class instantiated by the parent with our storage.
    $this->treeStorage = \Drupal::service('wse_menu.tree_storage');

    // Activate the Stage workspace, so all test methods of the parent class run
    // in a workspace context.
    $this->switchToWorkspace($this->workspaces['stage']->id());
  }

  /**
   * Ensure hierarchy persists after a menu rebuild.
   */
  public function testMenuRebuild(): void {
    // WSE Menu supports CRUD menu tree operations for menu_link_content
    // entities, while the parent test method is using "mock" menu links.
    $this->markTestSkipped();
  }

  /**
   * {@inheritdoc}
   */
  public function testLoadByProperties(): void {
    parent::testLoadByProperties();

    // Test loading by route parameters.
    $this->addMenuLink('test_link.2', '', 'test', ['foo' => 'bar'], 'menu1');
    $properties = ['route_parameters' => serialize(['foo' => 'bar'])];
    $links = $this->treeStorage->loadByProperties($properties);
    $this->assertEquals('menu1', $links['test_link.2']['menu_name']);
    $this->assertEquals('test', $links['test_link.2']['route_name']);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertMenuLink(string $id, array $expected_properties, array $parents = [], array $children = []): void {
    $table_name = 'menu_tree';
    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $table_name = $this->treeStorage->getWorkspaceTableName($active_workspace);
    }

    $query = $this->connection->select($table_name);
    $query->fields($table_name);
    $query->condition('id', $id);
    foreach ($expected_properties as $field => $value) {
      $query->condition($field, $value);
    }
    $all = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $this->assertCount(1, $all, "Found link $id matching all the expected properties");
    $raw = reset($all);

    // Put the current link onto the front.
    array_unshift($parents, $raw['id']);

    $found_parents = $this->treeStorage->loadMultiple($parents);
    $this->assertSameSize($parents, $found_parents, 'Found expected number of parents');
    $this->assertCount($raw['depth'], $found_parents, 'Number of parents is the same as the depth');

    $materialized_path = $this->treeStorage->getRootPathIds($id);
    $this->assertEquals(array_values($parents), array_values($materialized_path), 'Parents match the materialized path');

    for ($i = $raw['depth']; $i >= 1; $i--) {
      array_shift($parents);
    }

    if ($parents) {
      $this->assertEquals(end($parents), $raw['parent'], 'Ensure that the parent field is set properly');
    }
    // Verify that the child IDs match.
    $this->assertEqualsCanonicalizing($children, array_keys($this->treeStorage->loadAllChildren($id)));
  }

}
