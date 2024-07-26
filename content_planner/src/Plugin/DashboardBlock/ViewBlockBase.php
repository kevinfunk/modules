<?php

namespace Drupal\content_planner\Plugin\DashboardBlock;

use Drupal\content_planner\DashboardBlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements ViewBlockBase class.
 *
 * @package Drupal\content_planner\Plugin\DashboardBlock
 */
abstract class ViewBlockBase extends DashboardBlockBase {

  use StringTranslationTrait;

  /**
   * ID for the block.
   *
   * @var string
   */
  protected $blockID = 'ID-HERE';

  /**
   * Builds the content for the views block.
   *
   * @return array|null
   *   The render for the views block.
   */
  public function build() {
    if (!$this->currentUserHasRole()) {
      return [];
    }

    $content = [];
    $config = $this->getConfiguration();

    // Get view from config.
    $view_config = $this->getCustomConfigByKey($config, $this->blockID);
    // Syntax is view_id.display_id.
    $view_array = explode('.', $view_config ?? '');

    if ($view_array && is_array($view_array) && isset($view_array[0]) && isset($view_array[1])) {
      $view_id = $view_array[0];
      $view_display_id = $view_array[1];

      $view = Views::getView($view_id);

      if ($view instanceof ViewExecutable && $view->access($view_display_id)) {
        $content = $view->render($view_display_id);
      }
    }

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(FormStateInterface &$form_state, Request &$request, array $block_configuration) {

    $form = [];

    // View.
    $view_default_value = $this->getCustomConfigByKey($block_configuration, $this->blockID);
    $view_options = [];
    $views = Views::getEnabledViews();
    foreach ($views as $view) {
      $displays = $view->get('display');

      if (is_array($displays)) {
        foreach ($displays as $display) {
          $view_options[$view->id() . '.' . $display['id']] = $view->label() . ' (' . $display['display_title'] . ')';
        }
      }
    }

    $form[$this->blockID] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#options' => $view_options,
      '#required' => TRUE,
      '#default_value' => $view_default_value,
    ];

    $form['allowed_roles'] = $this->buildAllowedRolesSelectBox($block_configuration);

    return $form;
  }

}
