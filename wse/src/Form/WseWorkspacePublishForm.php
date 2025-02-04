<?php

namespace Drupal\wse\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workspaces\Form\WorkspacePublishForm;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Provides the workspace publishing form.
 */
class WseWorkspacePublishForm extends WorkspacePublishForm {

  /**
   * All value for the save_published_revisions settings config property.
   *
   * @var string
   */
  const SAVE_PUBLISHED_REVISIONS_ALL = 'all';

  /**
   * Published value for the save_published_revisions settings config property.
   *
   * @var string
   */
  const SAVE_PUBLISHED_REVISIONS_PUBLISHED_ONLY = 'published';

  /**
   * Gets the form workspace.
   *
   * @return \Drupal\workspaces\WorkspaceInterface
   *   The current form workspace.
   */
  public function getWorkspace() {
    return $this->workspace;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkspace(WorkspaceInterface $workspace) {
    $this->workspace = $workspace;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WorkspaceInterface $workspace = NULL) {
    $form = parent::buildForm($form, $form_state, $workspace);
    $config = $this->config('wse.settings');
    if ($config->get('override_save_published_revisions')) {
      static::addSaveRevisionsSelect($form, $config);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $form_state->getFormObject()->getWorkspace();
    $config = $this->config('wse.settings');
    $save_published_revisions = $config->get('override_save_published_revisions')
      ? $form_state->getValue('save_published_revisions')
      : $config->get('save_published_revisions');

    if ($save_published_revisions) {
      $workspace->_save_published_revisions = $save_published_revisions;
    }
  }

  /**
   * Adds a select widget for the save_published_revisions options to a form.
   *
   * @param array $form
   *   The form the select is added to, passed by reference.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The wse.settings config object containing the default value.
   */
  public static function addSaveRevisionsSelect(&$form, $config) {
    $form['save_published_revisions'] = [
      '#type' => 'select',
      '#title' => t('Saved revisions during publishing'),
      '#description' => t('Store all or only published revisions IDs of entities inside a workspace during publishing.'),
      '#options' => [
        static::SAVE_PUBLISHED_REVISIONS_PUBLISHED_ONLY => t('Only published revision IDs'),
        static::SAVE_PUBLISHED_REVISIONS_ALL => t('All revision IDs'),
      ],
      '#empty_option' => t('- None -'),
      '#empty_value' => FALSE,
      '#default_value' => $config->get('save_published_revisions'),
    ];
  }

}
