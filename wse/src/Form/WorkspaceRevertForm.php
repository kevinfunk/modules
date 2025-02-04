<?php

namespace Drupal\wse\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse\WorkspaceReverter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the workspace revert form.
 */
class WorkspaceRevertForm extends ConfirmFormBase implements WorkspaceSafeFormInterface, ContainerInjectionInterface {

  /**
   * The workspace that will be reverted.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $workspace;

  /**
   * The workspace reverter service.
   *
   * @var \Drupal\wse\WorkspaceReverter
   */
  protected $workspaceReverter;

  /**
   * Constructs a new WorkspaceRevertForm.
   *
   * @param \Drupal\wse\WorkspaceReverter $workspace_reverter
   *   The workspace reverter service.
   */
  public function __construct(WorkspaceReverter $workspace_reverter) {
    $this->workspaceReverter = $workspace_reverter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wse.workspace_reverter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_revert_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WorkspaceInterface $workspace = NULL) {
    $this->workspace = $workspace;
    $form = parent::buildForm($form, $form_state);

    $form['actions']['submit']['#value'] = $this->t('Revert');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->workspaceReverter->revert($this->workspace);
      $this->messenger()->addMessage($this->t('Successful revert.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t('Revert failed. All errors have been logged.'), 'error');
      Error::logException($this->getLogger('workspaces'), $e);
    }
    $form_state->setRedirectUrl($this->workspace->toUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Would you like to revert the contents of the %label workspace?', [
      '%label' => $this->workspace->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Revert workspace contents.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->workspace->toUrl();
  }

}
