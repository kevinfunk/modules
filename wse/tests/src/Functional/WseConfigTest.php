<?php

declare(strict_types=1);

namespace Drupal\Tests\wse\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UiHelperTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests publishing and reverting configuration changes.
 *
 * @group wse
 */
class WseConfigTest extends BrowserTestBase {

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
    'wse_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ((float) \Drupal::VERSION >= 11.1) {
      \Drupal::service('module_installer')->install(['workspaces_ui']);
    }
    $this->drupalCreateContentType(['type' => 'article', 'label' => 'Article']);
    $this->setupWorkspaceSwitcherBlock();

    $admin = $this->drupalCreateUser(admin: TRUE);
    $this->drupalLogin($admin);
  }

  /**
   * Test callback.
   */
  public function testWseConfig(): void {
    // This avoids exception being thrown when saving a block.
    // @todo create issue about this.
    $this->drupalGet(Url::fromRoute('wse.settings'));
    $this->submitForm([], 'Save configuration');

    $block = $this->placeBlock('system_powered_by_block', [
      'label' => 'A block label',
      'label_display' => 'visible',
    ]);
    // Create a node.
    $node = $this->createNodeThroughUi('Test node', 'article');

    $this->assertSession()->pageTextContains('Test node');
    $this->assertSession()->pageTextContains('A block label');

    $this->drupalGet('admin/config/development/configuration/config-content');

    $workspace = $this->wseCreateAndActivateWorkspaceThroughUi('Source', 'source');

    $this->drupalGet($block->toUrl('edit-form'));
    $this->submitForm(['edit-settings-label' => 'Edited block label'], 'Save block');
    $this->assertSession()->pageTextContains('The block configuration has been saved.');

    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm(['title[0][value]' => 'Test edited node'], 'Save');

    $this->assertSession()->pageTextContains('Test edited node');
    $this->assertSession()->pageTextContains('Edited block label');

    $this->assertSession()->buttonExists('Switch to Live')->press();

    $this->assertSession()->pageTextContains('Test node');
    $this->assertSession()->pageTextContains('A block label');

    $this->assertSession()->selectExists('workspace_id')->selectOption($workspace->id());
    $this->assertSession()->buttonExists('Activate')->press();

    $this->assertSession()->pageTextContains('Test edited node');
    $this->assertSession()->pageTextContains('Edited block label');

    $this->drupalGet(Url::fromRoute('entity.workspace.publish_form', ['workspace' => $workspace->id()]));
    $this->assertSession()->pageTextContains('1 content item');
    $this->assertSession()->pageTextContains('1 workspace config');
    $this->assertSession()->buttonExists('Publish 2 items to Live')->press();

    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Test edited node');
    $this->assertSession()->pageTextContains('Edited block label');
    // Ensure we are in the live workspace.
    $this->assertSession()->pageTextContains('Current workspace None');

    // Try to revert the workspace.
    $this->drupalGet(Url::fromRoute('entity.workspace.revert_form', ['workspace' => $workspace->id()]));
    $this->assertSession()->buttonExists('Revert')->press();
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Test node');
    $this->assertSession()->pageTextContains('A block label');
  }

}
