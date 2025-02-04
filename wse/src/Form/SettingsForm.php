<?php

namespace Drupal\wse\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures wse settings.
 *
 * @internal
 */
class SettingsForm extends ConfigFormBase implements WorkspaceSafeFormInterface {

  /**
   * The workspace info service.
   *
   * @var \Drupal\workspaces\WorkspaceInformationInterface
   */
  protected WorkspaceInformationInterface $workspaceInformation;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wse_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wse.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workspaceInformation = $container->get('workspaces.information');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wse.settings');

    $form['workspace_switcher'] = [
      '#type' => 'details',
      '#title' => $this->t('Workspace switcher'),
      '#open' => TRUE,
    ];

    $form['workspace_switcher']['simplified_toolbar_switcher'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the simplified workspace switcher in the toolbar'),
      '#default_value' => $config->get('simplified_toolbar_switcher'),
    ];
    $form['workspace_switcher']['recent_workspaces_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of hours until a workspace is cleared from recent workspaces'),
      '#default_value' => $config->get('recent_workspaces_max_age') ?? 72,
      '#description' => $this->t('Use 0 to keep workspaces in the recent workspaces list forever.'),
      '#min' => 0,
      '#max' => 999,
    ];
    $form['workspace_switcher']['switcher_max_options'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of workspaces displayed in the switcher select'),
      '#default_value' => $config->get('switcher_max_options') ?? 10,
      '#description' => $this->t('Use 0 to display all.'),
      '#min' => 0,
      '#max' => 50,
    ];

    $form['workspace_publishing'] = [
      '#type' => 'details',
      '#title' => $this->t('Workspace publishing'),
      '#open' => TRUE,
    ];

    WseWorkspacePublishForm::addSaveRevisionsSelect($form['workspace_publishing'], $config);
    $form['workspace_publishing']['override_save_published_revisions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow overriding the setting above when publishing a workspace.'),
      '#default_value' => $config->get('override_save_published_revisions'),
      '#states' => [
        'invisible' => [
          ':input[name="save_published_revisions"]' => ['value' => '0'],
        ],
      ],
    ];

    $form['workspace_publishing']['squash_on_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('When a workspace is published, delete all intermediary draft revisions'),
      '#default_value' => $config->get('squash_on_publish') ? $config->get('squash_on_publish') : FALSE,
    ];
    $form['workspace_publishing']['squash_on_publish_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Delete intermediary revisions after'),
      '#size' => 5,
      '#default_value' => $config->get('squash_on_publish_interval') ?: 0,
      '#field_suffix' => $this->t('hour(s)'),
      '#description' => $this->t('Use 0 to delete them immediately.'),
      '#states' => [
        'visible' => [
          ':input[name="squash_on_publish"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['workspace_publishing']['clone_on_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('When a workspace is published, clone its details into a new draft workspace'),
      '#default_value' => $config->get('clone_on_publish'),
    ];

    $form['forms'] = [
      '#type' => 'details',
      '#title' => $this->t('Form submission inside workspaces'),
      '#open' => !empty(array_filter($config->get('safe_forms') ?? [])),
    ];
    $form['forms']['safe_forms'] = [
      '#type' => 'textarea',
      '#title' => t('Workspace safe forms'),
      '#default_value' => implode(PHP_EOL, $config->get('safe_forms') ?? []),
      '#rows' => '10',
      '#description' => t('Enter a form ID per line to allow forms for submission inside workspaces without a confirmation prompt.'),
    ];

    $form['workspace_status_field'] = [
      '#type' => 'details',
      '#title' => $this->t('Workspace status field'),
      '#open' => !empty(array_filter($config->get('entity_workspace_status') ?? [])),
    ];
    $options = [];
    foreach ($this->workspaceInformation->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      $options[$entity_type_id] = (string) $entity_type->getLabel();
    }
    uasort($options, function ($a, $b) {
      return $a <=> $b;
    });
    $form['workspace_status_field']['entity_workspace_status'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Enable the workspace status field on the following entity types:'),
      '#default_value' => $config->get('entity_workspace_status') ?? [],
    ];
    $form['disable_sub_workspaces'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable sub workspaces'),
      '#description' => $this->t('Sub workspaces are complicated and the user experience is generally easier if they are disabled.'),
      '#default_value' => $config->get('disable_sub_workspaces') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $safe_forms = array_map('trim', preg_split('/\r\n|\r|\n/', $form_state->getValue('safe_forms')));

    $config = $this->config('wse.settings');
    $config
      ->set('simplified_toolbar_switcher', $form_state->getValue('simplified_toolbar_switcher'))
      ->set('recent_workspaces_max_age', $form_state->getValue('recent_workspaces_max_age'))
      ->set('switcher_max_options', $form_state->getValue('switcher_max_options'))
      ->set('save_published_revisions', $form_state->getValue('save_published_revisions'))
      ->set('override_save_published_revisions', $form_state->getValue('override_save_published_revisions'))
      ->set('squash_on_publish', $form_state->getValue('squash_on_publish'))
      ->set('squash_on_publish_interval', $form_state->getValue('squash_on_publish_interval'))
      ->set('clone_on_publish', $form_state->getValue('clone_on_publish'))
      ->set('disable_sub_workspaces', $form_state->getValue('disable_sub_workspaces'))
      ->set('safe_forms', $safe_forms)
      ->set('entity_workspace_status', array_filter($form_state->getValue('entity_workspace_status')));
    $config->save();
  }

}
