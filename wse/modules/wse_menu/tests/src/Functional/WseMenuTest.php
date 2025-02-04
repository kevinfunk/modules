<?php

// phpcs:ignoreFile This file just has too many PHPCS issue to fix right now.

declare(strict_types=1);

namespace Drupal\Tests\wse_menu\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\workspaces\Functional\WorkspaceTestUtilities;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Tests wse_menu functionality.
 *
 * @group wse_menu
 */
class WseMenuTest extends BrowserTestBase {

  use WorkspaceTestUtilities;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected WorkspaceManagerInterface $workspaceManager;
  protected MenuLinkManagerInterface $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'menu_link_content',
    'node',
    'workspaces',
    'wse_menu_test',
    'options',
    'views',
  ];

  /**
   * Disabled config schema checking temporarily until all errors are resolved.
   *
   * @todo remove one the third party settings on the menu are removed.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected $stage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer site configuration',
      'administer workspaces',
      'administer menu',
    ];
    $this->drupalLogin($this->drupalCreateUser($permissions));
    $this->stage = Workspace::load('stage');
    $this->setupWorkspaceSwitcherBlock();
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->workspaceManager = \Drupal::service('workspaces.manager');
    $this->menuLinkManager = \Drupal::service('plugin.manager.menu.link');
  }

  /**
   * Tests custom menu links in non-default workspaces.
   */
  public function testWorkspacesWithCustomMenuLinks() {
    $this->markTestSkipped();
    $stage = $this->stage;
    $menu_name = 'wse-menu-test';
    $menu_edit_path = '/admin/structure/menu/manage/' . $menu_name;
    $title = 'Page 1 Link';
    $live_tree = \Drupal::service('wse_menu.tree_storage')->loadByProperties([
      'menu_name' => $menu_name,
    ]);

    $this->drupalGet($menu_edit_path);
    $assert_session = $this->assertSession();
    $assert_session->linkExists($title);
    $this->switchToWorkspace($stage);
    $this->drupalGet($menu_edit_path);
    $assert_session->linkExists($title);
    $this->submitForm([], 'Save');

    $tree_entity = WseMenuTree::load(1);
    // If not changes are submitted no workspace specific tree should be saved.
    $assert_session->assert(is_null($tree_entity), 'No tree without changes was created when submitting the menu form,');
    // @todo test whether menu is rendered on home inside the workspace.

    $this->drupalGet($menu_edit_path);
    $session = $this->getSession();

    $weight_change_link_id = 'wse_menu_test.drupal.org';
    $links_field_prefix = 'menu_plugin_id:';
    $new_weight = -50;
    $original_weight = (int) $live_tree[$weight_change_link_id]['weight'];
    $weight_field_selector = 'links[' . $links_field_prefix . $weight_change_link_id . '][weight]';

    // Change the weight of a link and verify that it got saved inside a tree
    // entity and is reflected on the form as well.
    $this->submitForm([$weight_field_selector => $new_weight], 'Save');
    $tree_entity = WseMenuTree::load(1);
    $tree = json_decode($tree_entity->get('tree')->value, TRUE);
    $this->assertEquals($tree[$weight_change_link_id]['weight'], $new_weight);
    $this->assertNotEquals($new_weight, $original_weight);
    $this->drupalGet($menu_edit_path);
    $page = $session->getPage();
    $field = $page->findField($weight_field_selector);
    $weight_form_value = (int) $field->getValue();
    $this->assertEquals($weight_form_value, $new_weight);
    $this->assertNotEquals($weight_form_value, $original_weight);

    // Now verify that the weight change in the workspace is not affecting the
    // live menu tree.
    $this->switchToLive();
    $this->drupalGet($menu_edit_path);
    $field = $page->findField($weight_field_selector);
    $weight_form_value = (int) $field->getValue();
    $this->assertNotEquals($weight_form_value, $new_weight);
    $this->assertEquals($weight_form_value, $original_weight);

    /**
     * phpcs:disable Drupal.Commenting.InlineComment.SpacingBefore
     * $default_title = 'default';
     * $default_link = '#live';
     * $menu_link_content = MenuLinkContent::create([
     * 'title' => $default_title,
     * 'menu_name' => 'main',
     * 'link' => [['uri' => 'internal:/' . $default_link]],
     * ]);
     * $menu_link_content->save();
     *
     * $pending_title = 'pending';
     * $pending_link = 'http://example.com';
     * $this->switchToWorkspace($stage);
     * $menu_link_content->set('title', $pending_title);
     * $menu_link_content->set('link', [['uri' => $pending_link]]);
     * $menu_link_content->save();
     *
     * $this->drupalGet('');
     * $assert_session = $this->assertSession();
     * $assert_session->linkExists($pending_title);
     * $assert_session->linkByHrefExists($pending_link);
     *
     * // Add a new menu link in the Stage workspace.
     * $menu_link_content = MenuLinkContent::create([
     * 'title' => 'stage link',
     * 'menu_name' => 'main',
     * 'link' => [['uri' => 'internal:/#stage']],
     * ]);
     * $menu_link_content->save();
     *
     * $this->drupalGet('');
     * $assert_session->linkExists('stage link');
     * $assert_session->linkByHrefExists('#stage');
     *
     * // Switch back to the Live workspace and check that the menu link has the
     * // default values.
     * $this->switchToLive();
     * $this->drupalGet('');
     * $assert_session->linkExists($default_title);
     * $assert_session->linkByHrefExists($default_link);
     * $assert_session->linkNotExists($pending_title);
     * $assert_session->linkByHrefNotExists($pending_link);
     * $assert_session->linkNotExists('stage link');
     * $assert_session->linkByHrefNotExists('#stage');
     *
     * // Publish the workspace and check that the menu link has been updated.
     * $stage->publish();
     * $this->drupalGet('');
     * $assert_session->linkNotExists($default_title);
     * $assert_session->linkByHrefNotExists($default_link);
     * $assert_session->linkExists($pending_title);
     * $assert_session->linkByHrefExists($pending_link);
     * $assert_session->linkExists('stage link');
     * $assert_session->linkByHrefExists('#stage');
     * phpcs:enable Drupal.Commenting.InlineComment.SpacingBefore
     */
  }

  /**
   * @todo Tests moving of a link to a new parent.
   */
  // public function testLinkReparenting() {
  //    $link_ids = array_keys($live_tree);
  //    $link3 = MenuLinkContent::load(3);
  // Move the third link from the root to under Page Link 2.
  // We can't set values of hidden inputs in the $edit array of submitForm().
  //    $link2 = MenuLinkContent::load(2);
  //    $link3_parent_field = $page->find('css', 'input[name="links[menu_plugin_id:menu_link_content:' . $link3->uuid() . '][parent]"]');
  //    $link3_parent_field->setValue('menu_link_content:' . $link2->uuid());
  //
  //    ['links[menu_plugin_id:menu_link_content:' . $link3->uuid() . '][parent]' => 'menu_link_content:' . $link2->uuid()]
  //    $link3_parent_field = $page->find('css', 'input[name="links[menu_plugin_id:menu_link_content:' . $link3->uuid() . '][parent]"]');
  //
  //    $this->assertEquals($link3_parent_field->getValue(), 'menu_link_content:' . $link2->uuid());
  //    dump($tree);
  //    $this->assertEquals($tree['menu_link_content:' . $link3->uuid()]['parent'], 'menu_link_content:' . $link2->uuid());
  //  }

  /**
   * @todo Tests tree mutations via link forms inside workspaces.
   * test:
   *   - expanded
   *   - enabled
   *   - title
   *   - link
   *   - weight
   */
  // public function testTreeMutationsViaLinkForm() {
  //    $assert_session = $this->assertSession();
  //    $this->drupalGet('/admin/structure/menu/item/3/edit');
  //    $assert_session->fieldExists('menu_parent');
  //    $this->switchToWorkspace($this->stage);
  //    // As agreed, hierarchies can only be edited via the tree form inside
  //    // workspaces, thus this field shouldn't be available.
  //    $assert_session->fieldNotExists('menu_parent');
  //
  //    // @todo test mutations on content and static links.
  //  }

  /**
   * @todo Tests adding and removing links to a tree inside a workspace.
   */
  // public function testLinkAddingAndRemoving() {
  // }

  /**
   * @todo Tests moving of a link to another menu.
   */
  // public function testMoveLinkToMenu() {
  // }

  /**
   * @todo Tests if active trail in live and workspace is different.
   */
  // public function testActiveTrails() {
  // }

  /**
   * @todo Tests static menu link overrides.
   */
  // public function testStaticMenuLinkOverrides() {
  // }

  /**
   * @todo Tests publishing of a tree into the live tree.
   */
  // public function testTreePublishing() {
  // }

  /**
   * @todo Tests content link changes.
   */
  // public function testContentLinkChanges() {
  // }

}
