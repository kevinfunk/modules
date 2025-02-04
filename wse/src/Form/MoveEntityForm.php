<?php

namespace Drupal\wse\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse\WseWorkspaceAssociation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form for moving an entity to a different workspace.
 */
class MoveEntityForm extends ConfirmFormBase implements WorkspaceSafeFormInterface, ContainerInjectionInterface {

  /**
   * The entity that will be moved.
   *
   * @var \Drupal\Core\Entity\RevisionableInterface
   */
  protected $entity;

  /**
   * The workspace where the entity will be moved from.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $sourceWorkspace;

  /**
   * The workspace where the entity will be moved to.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $targetWorkspace;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * The entity reference selection handler.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   */
  protected $selectionHandler;

  /**
   * Constructs a new MoveEntityForm.
   *
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_handler
   *   The entity reference selection handler.
   */
  public function __construct(WorkspaceAssociationInterface $workspace_association, SelectionPluginManagerInterface $selection_handler) {
    $this->workspaceAssociation = $workspace_association;
    $this->selectionHandler = $selection_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.association'),
      $container->get('plugin.manager.entity_reference_selection')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_move_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = NULL, ?WorkspaceInterface $source_workspace = NULL) {
    $this->entity = $route_match->getParameter($route_match->getParameter('entity_type_id'));
    $this->sourceWorkspace = $source_workspace;
    $form = parent::buildForm($form, $form_state);

    $workspace_selection = $this->selectionHandler->getInstance(['target_type' => 'workspace']);
    $options = $workspace_selection->getReferenceableEntities();
    $options = reset($options);

    // Remove the source workspace from the list of target workspaces.
    unset($options[$source_workspace->id()]);

    // Filter out closed workspaces.
    $workspaces = \Drupal::entityTypeManager()->getStorage('workspace')->loadMultiple(array_keys($options));
    foreach (array_keys($options) as $workspace_id) {
      if (wse_workspace_get_status($workspaces[$workspace_id]) !== WSE_STATUS_OPEN) {
        unset($options[$workspace_id]);
      }
    }

    $form['target_workspace'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Target workspace'),
      '#description' => $this->t('The workspace where this entity should be moved.'),
      '#required' => TRUE,
    ];

    $form['include_dependencies'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include dependencies'),
      '#default_value' => TRUE,
    ];

    $form['description']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $target_workspace = $form_state->getValue('target_workspace');
    $target_workspace = Workspace::load($target_workspace);
    $include_dependencies = (bool) $form_state->getValue('include_dependencies');
    try {
      assert($this->workspaceAssociation instanceof WseWorkspaceAssociation);
      $this->workspaceAssociation->moveEntity($this->entity, $this->sourceWorkspace, $target_workspace, $include_dependencies);
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
    return $this->t('Move entity to another workspace');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Move entity to another workspace.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->sourceWorkspace->toUrl();
  }

}
