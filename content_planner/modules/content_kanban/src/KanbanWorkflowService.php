<?php

namespace Drupal\content_kanban;

use Drupal\Core\Database\Connection;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements KanbanWorkflowService class.
 */
class KanbanWorkflowService {

  use StringTranslationTrait;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The Kanban Log service.
   *
   * @var \Drupal\content_kanban\KanbanLogService
   */
  protected $kanbanLogService;

  /**
   * Constructs a new NewsService object.
   */
  public function __construct(
    Connection $database,
    ModerationInformationInterface $moderation_information,
    KanbanLogService $kanban_log_service
  ) {
    $this->database = $database;
    $this->moderationInformation = $moderation_information;
    $this->kanbanLogService = $kanban_log_service;
  }

  /**
   * Acts upon a entity presave.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity that is saved.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user that is related to the entity save.
   *
   * @see content_kanban_entity_presave()
   */
  public function onEntityPresave(ContentEntityInterface $entity, AccountInterface $user) {
    // If the entity is moderated, meaning it belongs to a certain workflow.
    if ($this->moderationInformation->isModeratedEntity($entity)) {
      $current_state = $entity->moderation_state->value;
      $prev_state = $this->getPreviousWorkflowStateId($entity);

      if ($current_state && $prev_state) {
        $name = $this->t('Workflow State change on Entity')->render();
        $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

        // Create new log entity.
        $this->kanbanLogService->createLogEntity(
          $name,
          $user->id(),
          $entity->id(),
          $entity->getEntityTypeId(),
          $workflow->id(),
          $prev_state,
          $current_state
        );
      }
    }
  }

  /**
   * Get ID of the previous workflow state.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   *
   * @return string
   *   Returns the previous state id.
   */
  public function getPreviousWorkflowStateId(ContentEntityInterface $entity) {

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

    if ($state_history = $this->getWorkflowStateHistory($workflow->id(), $entity)) {

      if (isset($state_history[0])) {
        return $state_history[0];
      }
    }
    $state = $workflow->getTypePlugin()->getInitialState($entity);
    return $state->id();
  }

  /**
   * Gets the workflow state history of a given entity.
   *
   * @param string $workflow_id
   *   A string representing the workflow id.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the workflow history is requested.
   *
   * @return array
   *   An array with the workflow state history for the given entity.
   */
  public function getWorkflowStateHistory($workflow_id, ContentEntityInterface $entity) {

    $query = $this->database->select('content_moderation_state_field_revision', 'r');

    $query->addField('r', 'moderation_state');

    $query->condition('r.workflow', $workflow_id);
    $query->condition('r.content_entity_type_id', $entity->getEntityTypeId());
    $query->condition('r.content_entity_id', $entity->id());

    $query->orderBy('r.revision_id', 'DESC');

    $result = $query->execute()->fetchAll();

    $return = [];

    foreach ($result as $row) {
      $return[] = $row->moderation_state;
    }

    return $return;
  }

}
