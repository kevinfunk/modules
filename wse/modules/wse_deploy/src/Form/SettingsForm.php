<?php

namespace Drupal\wse_deploy\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures wse_deploy settings.
 *
 * @internal
 */
class SettingsForm extends ConfigFormBase implements WorkspaceSafeFormInterface {

  /**
   * The workspaces export plugin manager.
   *
   * @var \Drupal\wse_deploy\WorkspaceExportPluginManager
   */
  protected $workspaceExportPluginManager;

  /**
   * The configured workspace export plugin.
   *
   * @var \Drupal\wse_deploy\WorkspaceExportInterface|null
   */
  protected $exportPlugin;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wse_deploy_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wse_deploy.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workspaceExportPluginManager = $container->get('plugin.manager.wse_deploy.workspace_export');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wse_deploy.settings');

    $export_plugin_options = $this->workspaceExportPluginManager->getPluginOptions();
    $export_plugin_id = $form_state->getUserInput()['export_plugin_wrapper']['export_plugin'] ?? $config->get('export_plugin') ?? NULL;
    $export_plugin_configuration = $form_state->getUserInput()['export_plugin_wrapper']['export_plugin_configuration'] ?? $config->get('export_plugin_configuration') ?? [];

    if ($export_plugin_id) {
      $this->exportPlugin = $this->workspaceExportPluginManager->createInstance($export_plugin_id, $export_plugin_configuration);
    }

    $form['deploy_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment path'),
      '#description' => $this->t('A file system path for storing workspace deployment files. It should be writable by Drupal, not accessible over the web, and needs to be the same across the source and destinations sites.'),
      '#default_value' => $config->get('deploy_path'),
    ];

    $ajax_wrapper_id = Html::getUniqueId('ajax-wrapper');
    $form['export_plugin_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="' . $ajax_wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $form['export_plugin_wrapper']['export_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Export plugin'),
      '#description' => $this->t('After exporting a workspace, an export plugin can push its content to a remote destination. <strong>NOTE: this should only be set on the source site</strong>.'),
      '#options' => $export_plugin_options,
      '#default_value' => $export_plugin_id,
      '#empty_option' => $this->t('- Select -'),
      '#required' => FALSE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $ajax_wrapper_id,
      ],
    ];

    $form['export_plugin_wrapper']['export_plugin_configuration'] = [];
    if ($export_plugin_id) {
      $subform_state = SubformState::createForSubform($form['export_plugin_wrapper']['export_plugin_configuration'], $form, $form_state);
      $form['export_plugin_wrapper']['export_plugin_configuration'] = $this->exportPlugin->buildConfigurationForm($form['export_plugin_wrapper']['export_plugin_configuration'], $subform_state);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Call the plugin validation handler.
    if ($this->exportPlugin) {
      $subform_state = SubformState::createForSubform($form['export_plugin_wrapper']['export_plugin_configuration'], $form, $form_state);
      $this->exportPlugin->validateConfigurationForm($form['export_plugin_wrapper']['export_plugin_configuration'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Call the plugin submit handler.
    if ($this->exportPlugin) {
      $subform_state = SubformState::createForSubform($form['export_plugin_wrapper']['export_plugin_configuration'], $form, $form_state);
      $this->exportPlugin->submitConfigurationForm($form, $subform_state);

      $export_plugin_id = $form_state->getValue(['export_plugin_wrapper', 'export_plugin']);
      $export_plugin_configuration = $this->exportPlugin->getConfiguration();
    }
    else {
      $export_plugin_id = NULL;
      $export_plugin_configuration = [];
    }

    $config = $this->config('wse_deploy.settings');
    $config->set('deploy_path', $form_state->getValue('deploy_path'));
    $config->set('export_plugin', $export_plugin_id);
    $config->set('export_plugin_configuration', $export_plugin_configuration);
    $config->save();
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(&$form, FormStateInterface $form_state) {
    $element_parents = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $element_parents);
  }

}
