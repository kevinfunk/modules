<?php

namespace Drupal\content_planner;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Provides functions for loading information related to content moderation.
 */
class ContentModerationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * ContentModerationService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface
   *   The moderation information service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModerationInformationInterface $moderation_information
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * Gets the Content Moderation Entities.
   *
   * @param string $workflow
   *   The workflow ID.
   * @param array $filters
   *   An array with the filters.
   * @param array $entities
   *   An array with the entities.
   *
   * @return \Drupal\content_moderation\Entity\ContentModerationState[]
   *   Returns an array with the content moderation states for the given
   *   workflow.
   */
  public function getEntityContentModerationEntities($workflow, array $filters = [], array $entities = []) {
    $result = [];
    try {
      $query = $this->entityTypeManager->getStorage('content_moderation_state')->getQuery();
      $query->accessCheck();
      if (!empty(array_keys($entities))) {
        $query->condition('workflow', $workflow);
        $query->condition('content_entity_type_id', array_keys($entities), 'in');
      }

      // Moderation state filter.
      if (array_key_exists('moderation_state', $filters) && $filters['moderation_state']) {
        $query->condition('moderation_state', $filters['moderation_state']);
      }

      // User ID filter.
      if (array_key_exists('uid', $filters) && $filters['uid']) {
        $query->condition('uid', $filters['uid']);
      }

      // User ID filter.
      $result = $query->execute();
    }
    catch (InvalidPluginDefinitionException $e) {
      watchdog_exception('content_planner', $e);
    }
    catch (PluginNotFoundException $e) {
      watchdog_exception('content_planner', $e);
    }
    if ($result) {
      return $this->entityTypeManager->getStorage('content_moderation_state')->loadMultiple($result);
    }

    return $result;
  }

  /**
   * Gets the entity IDs from Content Moderation entities.
   *
   * @param string $workflow
   *   The workflow id.
   * @param array $filters
   *   An array with the filters.
   * @param array $entities
   *   An array with the entities.
   *
   * @return array
   *   Returns an array with the entity ids.
   */
  public function getEntityIdsFromContentModerationEntities($workflow, array $filters = [], array $entities = []) {
    $entityIds = [];

    if ($content_moderation_states = $this->getEntityContentModerationEntities($workflow, $filters, $entities)) {
      foreach ($content_moderation_states as $content_moderation_state) {

        // Get property.
        $content_entity_id_property = $content_moderation_state->content_entity_id;

        // Get value.
        $content_entity_id_value = $content_entity_id_property->getValue();
        $entity_type_id_value = $content_moderation_state->get('content_entity_type_id')->getValue();
        // Get the entity type id.
        $entity_type_id = $entity_type_id_value[0]['value'];
        // Build the ids array with entity type as key.
        $entityIds[$entity_type_id][] = $content_entity_id_value[0]['value'];
      }
    }
    return $entityIds;
  }

  /**
   * Checks if a given workflow is a valid Content Moderation workflow.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow service.
   *
   * @return bool
   *   Returns TRUE if the workflow is valid, FALSE otherwise.
   */
  public function isValidContentModerationWorkflow(WorkflowInterface $workflow) {

    if ($workflow->get('type') == 'content_moderation') {
      $type_settings = $workflow->get('type_settings');

      if (!empty($type_settings['entity_types'])) {
        if (array_key_exists('states', $type_settings)) {
          if (!empty($type_settings['states'])) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Gets the label of the current state of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   *
   * @return bool|string
   *   Returns the current state if any, FALSE otherwise.
   */
  public function getCurrentStateLabel(string $entityTypeId, string $bundle, string $state) {
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    if (!$this->moderationInformation->shouldModerateEntitiesOfBundle($entityType, $bundle)) {
      return NULL;
    }

    $workflow = $this->moderationInformation->getWorkflowForEntityTypeAndBundle($entityTypeId, $bundle);
    if ($workflow === NULL) {
      return NULL;
    }

    $states = $this->getWorkflowStates($workflow);
    if (!array_key_exists($state, $states)) {
      return NULL;
    }

    return $states[$state];
  }

  /**
   * Get Workflow States.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow object.
   *
   * @return array
   *   Returns an array with the available workflow states.
   */
  public function getWorkflowStates(WorkflowInterface $workflow) {

    $states = [];

    $type_settings = $workflow->get('type_settings');

    // Sort by weight.
    uasort($type_settings['states'], function ($a, $b) {

      if ($a['weight'] == $b['weight']) {
        return 0;
      }
      elseif ($a['weight'] < $b['weight']) {
        return -1;
      }
      else {
        return 1;
      }

    });

    foreach ($type_settings['states'] as $state_id => $state) {
      $states[$state_id] = $state['label'];
    }

    return $states;
  }

}
