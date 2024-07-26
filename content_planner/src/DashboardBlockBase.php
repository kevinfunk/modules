<?php

namespace Drupal\content_planner;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements DashboardBlockBase class.
 */
class DashboardBlockBase extends PluginBase implements DashboardBlockInterface, ContainerFactoryPluginInterface {

  use ConfigFormBaseTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->database = $container->get('database');

    return $instance;
  }

  /**
   * Gets the configuration names that will be editable.
   *
   *  Return an array of configuration object names that are editable if called
   *  in conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {

  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurable() {

    if (array_key_exists('configurable', $this->pluginDefinition)) {
      return $this->pluginDefinition['configurable'];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Get basic configuration structure for the block configuration.
   *
   * @return array
   *   The basic configuration structure.
   */
  public static function getBasicConfigStructure() {

    return [
      'plugin_id' => NULL,
      'title' => NULL,
      'weight' => 0,
      'configured' => FALSE,
      'plugin_specific_config' => [],
    ];
  }

  /**
   * Get custom config.
   *
   * @param array $block_configuration
   *   The block plugin configuration.
   * @param string $key
   *   The config key.
   * @param mixed $default_value
   *   The default value to return if key does not exist in the specific
   *   configuration.
   *
   * @return mixed|null
   *   The config value or NULL.
   */
  protected function getCustomConfigByKey(array $block_configuration, $key, $default_value = NULL) {

    // If a given key exists in the plugin specific configuration, then return
    // it.
    if ((array_key_exists($key, $block_configuration['plugin_specific_config']))) {
      return $block_configuration['plugin_specific_config'][$key];
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(FormStateInterface &$form_state, Request &$request, array $block_configuration) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface &$form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitSettingsForm(array &$form, FormStateInterface &$form_state) {}

  /**
   * Build the permissions select box.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param array $block_configuration
   *   The block configuration.
   *
   * @return array
   *   The permissions checkboxes.
   */
  protected function buildAllowedRolesSelectBox(array $block_configuration): array {
    $roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->loadMultiple();
    $options = [];

    foreach ($roles as $role_id => $role) {
      if ($role_id === 'anonymous') {
        continue;
      }

      $options[$role_id] = $role->label();
    }

    return [
      '#type' => 'checkboxes',
      '#title' => $this->t('Display for roles'),
      '#description' => $this->t('The user roles that can see this widget. Leave blank to allow all roles.'),
      '#required' => FALSE,
      '#options' => $options,
      '#default_value' => $block_configuration['plugin_specific_config']['allowed_roles'] ?? [],
    ];
  }

  /**
   * Check if the user has permission to view the block.
   *
   * @return boolean
   *   TRUE if user has permission to view the block, FALSE otherwise.
   */
  protected function currentUserHasRole(): bool {
    if (!isset($this->getConfiguration()['plugin_specific_config']['allowed_roles'])) {
      return TRUE;
    }

    if ($this->currentUser->id() == 1) {
      return TRUE;
    }

    // Get configured roles.
    $configured_roles = $this->getConfiguration()['plugin_specific_config']['allowed_roles'];
    $user_roles = $this->currentUser->getRoles();
    $display_for_all = TRUE;

    foreach ($configured_roles as $key => $value) {
      // Check if role was selected
      if (empty($value)) {
        continue;
      }

      // If a role was selected, we can't show the block for all users
      $display_for_all = FALSE;

      // Check if user has selected roles
      if (in_array($key, $user_roles)) {
        return TRUE;
      }
    }

    return $display_for_all;
  }

}
