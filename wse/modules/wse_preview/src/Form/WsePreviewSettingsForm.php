<?php

declare(strict_types=1);

namespace Drupal\wse_preview\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;

/**
 * Settings form for Workspace previews.
 */
class WsePreviewSettingsForm extends ConfigFormBase implements WorkspaceSafeFormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'wse_preview_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['preview_expiry_default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default preview expiry'),
      '#description' => $this->t("The time period should be specified as '8 hours', '1 day', '10 days, 12 hours', etc."),
      '#required' => TRUE,
      '#config_target' => 'wse_preview.settings:preview_expiry_default',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['wse_preview.settings'];
  }

}
