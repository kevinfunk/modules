<?php

namespace Drupal\Tests\piwik_pro\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic testing of the Piwik PRO snippet.
 *
 * @group piwik_pro
 */
class PiwikProSnippetTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The Piwik PRO configuration object with original configuration data.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User|bool
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'piwik_pro',
    'user',
    'help',
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set basic config.
    $this->config = $this->config('piwik_pro.settings');
    $this->config
      ->set('site_id', '00000000-0000-1000-a000-000000000000')
      ->set('piwik_domain', 'https://yourname.containers.piwik.pro/')
      ->set('data_layer', 'dataLayer')
      ->set('visibility.request_path_mode', 0)
      ->set('visibility.request_path_pages', "/admin\n/admin/*\n/batch\n/node/add*\n/node/*/*\n/user/*/*")
      ->save();

    // Setup and login admin user.
    $permissions = [
      'access administration pages',
      'administer piwik pro',
      'administer modules',
      'administer site configuration',
      'access help pages',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);

    // Create page and article content types.
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalCreateContentType(['type' => 'article']);

    // Place the block to show help.
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);
  }

  /**
   * Tests if configuration is possible.
   */
  public function testPiwikProConfiguration(): void {
    // Check if Configure link is available on 'Extend' page.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseContains('admin/config/services/piwik-pro');

    // Check for admin page availability.
    $this->drupalGet('admin/config/services/piwik-pro');
    $this->assertSession()->responseContains($this->t('Container address (URL)'));
  }

  /**
   * Tests if help sections are shown.
   */
  public function testPiwikProHelp(): void {
    // Test help on admin page.
    $this->drupalGet('admin/config/services/piwik-pro');
    $this->assertSession()->responseContains('<a href="https://piwik.pro/">Piwik PRO</a> is a GDPR-proof analytics tool.');

    // Test module help-page.
    $this->drupalGet('admin/help/piwik_pro');
    $this->assertSession()->responseContains('Piwik PRO is a GDPR-proof tracking tool that allows you to track user visits.');
  }

  /**
   * Tests setting "Show on all pages except listed" without sync-snippet.
   */
  public function testSnippetVisibility(): void {
    // Default should show on the homepage.
    $this->drupalGet('');
    $async_url = sprintf('%s%s/noscript.html',
      (string) $this->config->get('piwik_domain'),
      (string) $this->config->get('site_id'));
    $this->assertSession()->responseContains($async_url);
    $this->assertSession()->responseContains((string) $this->config->get('data_layer'));

    // Default the sync-script is not shown.
    $sync_url = sprintf('%s\'+id+\'.sync.js',
      (string) $this->config->get('piwik_domain'));
    $this->assertSession()->responseNotContains($sync_url);

    // Default should not show on admin pages.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet shows on "Every page except the listed pages" selected.
   */
  public function testSnippetVisibilityPages(): void {
    $this->config->set('visibility.request_path_mode', 0)
      ->save();
    $this->config->set('visibility.request_path_pages', '/admin/modules')
      ->save();
    $this->refreshVariables();
    // Shown on the front page.
    $this->drupalGet('');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
    // But does not show on admin/modules.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet shows with setting "Show only on these pages".
   */
  public function testSnippetVisibilityPagesInverted(): void {
    $this->config->set('visibility.request_path_mode', 1)
      ->save();
    $this->config->set('visibility.request_path_pages', '/admin/modules')
      ->save();
    $this->refreshVariables();
    // Not shown on the front page.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
    // Show on admin/modules.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet shows on "Every page except the listed pages" selected.
   *
   * No pages entered.
   */
  public function testSnippetVisibilityPagesNoPagesEntered(): void {
    $this->config->set('visibility.request_path_mode', 0)
      ->save();
    $this->config->set('visibility.request_path_pages', '')
      ->save();
    $this->refreshVariables();
    // Shown on the front page.
    $this->drupalGet('');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
    // Show on admin/modules.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet shows on "Show only on these pages" selected.
   *
   * No pages entered.
   */
  public function testSnippetVisibilityPagesNoPagesEnteredInverted(): void {
    $this->config->set('visibility.request_path_mode', 1)
      ->save();
    $this->config->set('visibility.request_path_pages', '')
      ->save();
    $this->refreshVariables();
    // Does not show on the front page.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
    // Does not show on admin/modules.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet doesn't show for the listed roles.
   */
  public function testSnippetVisibilityRoles(): void {
    $this->config
      ->set('visibility.user_role_mode', 0)
      ->set('visibility.user_roles.anonymous', 'anonymous')
      ->save();

    $this->refreshVariables();

    // Now shown on the front page as authenticated user.
    $this->drupalGet('');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));

    $this->drupalLogout();
    // Now not shown on the front page as anonymous user.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet only shows for the listed roles.
   */
  public function testSnippetVisibilityRolesInverted(): void {
    $this->config
      ->set('visibility.user_role_mode', 1)
      ->set('visibility.user_roles.anonymous', 'anonymous')
      ->save();

    $this->refreshVariables();

    // Now not shown on the front page as authenticated user.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));

    $this->drupalLogout();
    // Now shown on the front page as anonymous user.
    $this->drupalGet('');
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet doesn't show for the selected content types.
   */
  public function testSnippetVisibilityContentTypes(): void {
    $this->config
      ->set('visibility.content_type_mode', 0)
      ->set('visibility.content_types.page', 'page')
      ->set('visibility.content_types.article', 0)
      ->save();

    $this->refreshVariables();

    // Now not shown on the page node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'page'])->id());
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));

    // Now shown on the article node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'article'])->id());
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the snippet only shows for the selected content types.
   */
  public function testSnippetVisibilityContentTypesInverted(): void {
    $this->config
      ->set('visibility.content_type_mode', 1)
      ->set('visibility.content_types.page', 'page')
      ->set('visibility.content_types.article', 0)
      ->save();

    $this->refreshVariables();

    // Now shown on the page node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'page'])->id());
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));

    // Now not shown on the article node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'article'])->id());
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test case: if no types selected and the selected content types only.
   */
  public function testSnippetVisibilityContentTypesNoTypesSelected(): void {
    $this->config
      ->set('visibility.content_type_mode', 1)
      ->set('visibility.content_types.page', 0)
      ->set('visibility.content_types.article', 0)
      ->save();

    $this->refreshVariables();

    // Now shown on the page node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'page'])->id());
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));

    // Now not shown on the article node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'article'])->id());
    $this->assertSession()->responseNotContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test case: if no types selected and all types except selected.
   */
  public function testSnippetVisibilityContentTypesNoTypesSelectedInverted(): void {
    $this->config
      ->set('visibility.content_type_mode', 0)
      ->set('visibility.content_types.page', 0)
      ->set('visibility.content_types.article', 0)
      ->save();

    $this->refreshVariables();

    // Now shown on the page node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'page'])->id());
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));

    // Now not shown on the article node.
    $this->drupalGet('node/' . $this->drupalCreateNode(['type' => 'article'])->id());
    $this->assertSession()->responseContains((string) $this->config->get('piwik_domain'));
  }

  /**
   * Test if the datalayer is changed when set in config.
   */
  public function testSnippetVisibilityChangedDataLayer(): void {
    $this->config->set('data_layer', 'changedDataLayer')->save();
    $this->refreshVariables();

    $this->drupalGet('');
    $new_datalayer = sprintf('(window, document, \'%s\', \'%s\')',
    'changedDataLayer',
      (string) $this->config->get('site_id'));
    $this->assertSession()->responseContains($new_datalayer);
  }

  /**
   * Test if the script is hidden when one of the properties is empty.
   */
  public function testSnippetVisibilityInvalidSettings(): void {
    $this->config->set('site_id', '')->save();
    $this->refreshVariables();

    $this->drupalGet('');
    $async_url = '/noscript.html';
    $this->assertSession()->responseNotContains($async_url);

    $this->config
      ->set('site_id', '00000000-0000-1000-a000-000000000000')
      ->set('piwik_domain', '')
      ->save();
    $this->refreshVariables();

    $this->drupalGet('');
    $async_url = '/noscript.html';
    $this->assertSession()->responseNotContains($async_url);

    $this->config
      ->set('piwik_domain', 'https://yourname.containers.piwik.pro/')
      ->set('data_layer', '')
      ->save();
    $this->refreshVariables();

    $this->drupalGet('');
    $async_url = '/noscript.html';
    $this->assertSession()->responseNotContains($async_url);

    // Reset all for the next test.
    $this->config->set('data_layer', 'dataLayer')->save();
    $this->refreshVariables();
  }

  /**
   * Test the settings-form.
   */
  public function testSettingsForm(): void {
    $this->drupalGet('/admin/config/services/piwik-pro');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Piwik PRO');
    $this->assertSession()->elementExists('css', '#edit-site-id');

    $edit = [
      'site_id' => '0',
      'data_layer' => '0',
    ];
    $this->submitForm($edit, (string) $this->t('Save configuration'));
    $this->assertSession()->responseContains($this->t('Invalid <code>Site ID</code> value.'));
    $this->assertSession()->responseContains($this->t('Invalid <code>Data layer</code> value.'));
  }

}
