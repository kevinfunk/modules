<?php

namespace Drupal\wse\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form for discarding the changes to an entity in a workspace.
 */
class DiscardEntityForm extends ConfirmFormBase implements WorkspaceSafeFormInterface, ContainerInjectionInterface {

  /**
   * The entity that will be discarded.
   *
   * @var \Drupal\Core\Entity\RevisionableInterface
   */
  protected $entity;

  /**
   * The workspace from which the entity will be discarded.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $sourceWorkspace;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * Constructs a new DiscardEntityForm.
   *
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   */
  public function __construct(WorkspaceAssociationInterface $workspace_association) {
    $this->workspaceAssociation = $workspace_association;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.association')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_discard_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = NULL, ?WorkspaceInterface $source_workspace = NULL) {
    $this->entity = $route_match->getParameter($route_match->getParameter('entity_type_id'));
    $this->sourceWorkspace = $source_workspace;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->workspaceAssociation->discardEntity($this->entity, $this->sourceWorkspace);

      $this->messenger()->addMessage($this->t('Successful operation.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t('Operation failed. All errors have been logged.'), 'error');
    }
    $form_state->setRedirectUrl($this->sourceWorkspace->toUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Discard changes for this entity?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('All the revisions that were created in this workspace will be deleted. <strong>This action can not be undone!</strong>');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->sourceWorkspace->toUrl();
  }

}
