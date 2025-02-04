<?php

namespace Drupal\wse_deploy\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\Form\WorkspacePublishForm;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the workspace export form.
 */
class WorkspaceExportForm extends WorkspacePublishForm {

  /**
   * The workspace exporter service.
   *
   * @var \Drupal\wse_deploy\WorkspaceExporter
   */
  protected $workspaceExporter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workspaceExporter = $container->get('wse_deploy.workspace_exporter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WorkspaceInterface $workspace = NULL) {
    $form = parent::buildForm($form, $form_state, $workspace);

    $args = [
      '%source_label' => $this->workspace->label(),
    ];
    $form['#title'] = $this->t('Export %source_label workspace', $args);

    // List the changes that can be exported.
    $workspace_publisher = $this->workspaceOperationFactory->getPublisher($this->workspace);
    if ($workspace_publisher->getDifferringRevisionIdsOnSource()) {
      $total_count = $workspace_publisher->getNumberOfChangesOnSource();
      $form['description']['#title'] = $this->formatPlural($total_count, 'There is @count item that can be exported from %source_label', 'There are @count items that can be exported from %source_label', $args);
      $form['actions']['submit']['#value'] = $this->formatPlural($total_count, 'Export @count item', 'Export @count items');
    }
    else {
      // If there are no changes to export, show an informational message.
      $form['help'] = [
        '#markup' => $this->t('There are no changes that can be exported from %source_label.', $args),
      ];

      // Do not allow the 'Export' operation if there's nothing to export.
      $form['actions']['submit']['#value'] = $this->t('Export');
      $form['actions']['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->workspaceExporter->exportToJson($this->workspace);

      $this->messenger()->addMessage($this->t('Successful export.'));
    }
    catch (\Exception $e) {
      Error::logException($this->logger('wse_deploy'), $e);
      $this->messenger()->addMessage($this->t('Export failed. All errors have been logged.'), 'error');
    }
    $form_state->setRedirectUrl($this->workspace->toUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Would you like to export the contents of the %label workspace?', [
      '%label' => $this->workspace->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Export workspace contents.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->workspace->toUrl();
  }

}
