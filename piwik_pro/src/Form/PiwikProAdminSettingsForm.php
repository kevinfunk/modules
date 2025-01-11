<?php

namespace Drupal\piwik_pro\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Piwik PRO settings for this site.
 */
class PiwikProAdminSettingsForm extends ConfigFormBase {


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a \Drupal\piwik_pro\Form\PiwikProAdminSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface|null $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, $typedConfigManager = NULL) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piwik_pro_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['piwik_pro.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('piwik_pro.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General settings', [], ['context' => 'Piwik PRO']),
    ];
    $form['general']['piwik_domain'] = [
      '#type' => 'url',
      '#title' => $this->t('Container address (URL)', [], ['context' => 'Piwik PRO']),
      '#description' => $this->t('The account address with <strong>containers</strong> added to the address.<br>
F.e.: <code>https://yourname.<strong>containers</strong>.piwik.pro/</code> or
<code>https://yourname.piwik.pro/<strong>containers</strong></code>. Always end with a slash (/).', [], ['context' => 'Piwik PRO']),
      '#default_value' => $config->get('piwik_domain'),
      '#required' => TRUE,
    ];
    $form['general']['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site ID', [], ['context' => 'Piwik PRO']),
      '#description' => $this->t('The ID for your site in Piwik PRO. <a href=":piwik_help_url" target="_blank">Where to find it?</a>', [':piwik_help_url' => 'https://help.piwik.pro/support/questions/find-website-id/'], ['context' => 'Piwik PRO']),
      '#default_value' => $config->get('site_id'),
      '#required' => TRUE,
    ];
    $form['general']['data_layer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data layer'),
      '#description' => $this->t('Default: <code>dataLayer</code>.
Rename the data layer if you use other data layers to prevent interference.
<a href=":piwik_help_datalayer" target="_blank">How to check it?</a>', [':piwik_help_datalayer' => 'https://developers.piwik.pro/en/latest/tag_manager/data_layer_name.html#data-layer-name-guidelines'], ['context' => 'Piwik PRO']),
      '#default_value' => $config->get('data_layer'),
      '#required' => TRUE,
    ];

    $form['tracking'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('What to track', [], ['context' => 'Piwik PRO']),
    ];
    $form['tracking']['piwik_pro_visibility_request_path_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking to specific pages', [], ['context' => 'Piwik PRO']),
      '#options' => [
        $this->t('Every page except the listed pages', [], ['context' => 'Piwik PRO']),
        $this->t('The listed pages only', [], ['context' => 'Piwik PRO']),
      ],
      '#default_value' => $config->get('visibility.request_path_mode'),
    ];
    $visibility_request_path_pages = $config->get('visibility.request_path_pages');
    $form['tracking']['piwik_pro_visibility_request_path_pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages', [], ['context' => 'Piwik PRO']),
      '#title_display' => 'invisible',
      '#default_value' => !empty($visibility_request_path_pages) ? $visibility_request_path_pages : '',
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", [
        '%blog' => '/blog',
        '%blog-wildcard' => '/blog/*',
        '%front' => '<front>',
      ], ['context' => 'Piwik PRO']),
      '#rows' => 10,
    ];

    $form['tracking']['piwik_pro_visibility_user_role_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking to specific roles', [], ['context' => 'Piwik PRO']),
      '#options' => [
        $this->t('Every role except the selected roles', [], ['context' => 'Piwik PRO']),
        $this->t('The selected roles only', [], ['context' => 'Piwik PRO']),
      ],
      '#default_value' => $config->get('visibility.user_role_mode') ?? 0,
    ];

    $form['tracking']['piwik_pro_visibility_user_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles', [], ['context' => 'Piwik PRO']),
      '#description' => $this->t('User roles you want the tracking snippets to be loaded with.', [], ['context' => 'Piwik PRO']),
      '#default_value' => $config->get('visibility.user_roles'),
      '#options' => $this->getUserRoles(),
    ];

    if ($this->getContentTypes()) {
      $form['tracking']['piwik_pro_visibility_content_type_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Add tracking to specific content types', [], ['context' => 'Piwik PRO']),
        '#options' => [
          $this->t('Every content type except the selected content types', [], ['context' => 'Piwik PRO']),
          $this->t('The selected content types only', [], ['context' => 'Piwik PRO']),
        ],
        '#default_value' => $config->get('visibility.content_type_mode') ?? 0,
      ];

      $form['tracking']['piwik_pro_visibility_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Content types to track', [], ['context' => 'Piwik PRO']),
        '#description' => $this->t('Content types you want the tracking snippets to be loaded with.', [], ['context' => 'Piwik PRO']),
        '#options' => $this->getContentTypes(),
        '#default_value' => $config->get('visibility.content_types'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate URL.
    $value = rtrim((string) $form_state->getValue('piwik_domain'), '/');
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('piwik_domain', $this->t('Invalid <code>Container address (URL)</code> value.'), [], ['context' => 'Piwik PRO']);
    }

    // Validate Site ID.
    $value = strtolower((string) $form_state->getValue('site_id'));
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) !== 1) {
      $form_state->setErrorByName('site_id', $this->t('Invalid <code>Site ID</code> value.', [], ['context' => 'Piwik PRO']));
    }

    // Validate data-layers.
    if (preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*$/', (string) $form_state->getValue('data_layer')) !== 1) {
      $form_state->setErrorByName('data_layer', $this->t('Invalid <code>Data layer</code> value.', [], ['context' => 'Piwik PRO']));
    }

    // Validate visibility pages.
    $form_state->setValue('piwik_pro_visibility_request_path_pages', trim((string) $form_state->getValue('piwik_pro_visibility_request_path_pages')));

    // Verify that every path is prefixed with a slash.
    if (!empty($form_state->getValue('piwik_pro_visibility_request_path_pages'))) {
      $pages = preg_split('/(\r\n?|\n)/', (string) $form_state->getValue('piwik_pro_visibility_request_path_pages'));
      foreach ($pages as $page) {
        if (strpos($page, '/') !== 0 && $page !== '<front>') {
          $form_state->setErrorByName('piwik_pro_visibility_request_path_pages', $this->t('Path "@page" not prefixed with slash.', ['@page' => $page], ['context' => 'Piwik PRO']));
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('piwik_pro.settings');

    // The url should always end with a slash.
    $piwik_domain = $form_state->getValue('piwik_domain');
    if (!(substr((string) $piwik_domain, -1) === "/")) {
      $piwik_domain .= '/';
    }

    $config
      ->set('site_id', $form_state->getValue('site_id'))
      ->set('piwik_domain', $piwik_domain)
      ->set('data_layer', $form_state->getValue('data_layer'))
      ->set('visibility.request_path_mode', $form_state->getValue('piwik_pro_visibility_request_path_mode'))
      ->set('visibility.request_path_pages', $form_state->getValue('piwik_pro_visibility_request_path_pages'))
      ->set('visibility.user_role_mode', $form_state->getValue('piwik_pro_visibility_user_role_mode'))
      ->set('visibility.user_roles', $form_state->getValue('piwik_pro_visibility_user_roles'))
      ->set('visibility.content_type_mode', $form_state->getValue('piwik_pro_visibility_content_type_mode'))
      ->set('visibility.content_types', $form_state->getValue('piwik_pro_visibility_content_types'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns an array of user roles.
   *
   * @return array<string, mixed>
   *   Array with key|value where the key is the id of the role
   *   and the value is the label of the role.
   */
  protected function getUserRoles(): array {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $options = [];

    foreach ($roles as $role) {
      $options[$role->id()] = $role->label();
    }

    return $options;
  }

  /**
   * Returns an array of content types.
   *
   * @return array<string, mixed>
   *   Array with key|value where the key is the id of the content types
   *   and the value is the label of the content types.
   */
  protected function getContentTypes(): array {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $types */
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $node_types = [];

    foreach ($types as $type) {
      $node_types[$type->getOriginalId()] = $type->label();
    }

    return $node_types;
  }

}
