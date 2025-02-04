<?php

namespace Drupal\wse;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Utility\Error;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\DependentEntityWrapperInterface;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an override for core's workspace association service.
 */
class WseWorkspaceAssociation implements WorkspaceAssociationInterface, EventSubscriberInterface {

  /**
   * A multidimensional array of entity IDs that are associated to a workspace.
   *
   * The first level keys are workspace IDs, the second level keys are entity
   * type IDs, and the third level array are entity IDs, keyed by revision IDs.
   *
   * @var array
   */
  protected array $associatedRevisions = [];

  /**
   * A multidimensional array of entity IDs that were created in a workspace.
   *
   * The first level keys are workspace IDs, the second level keys are entity
   * type IDs, and the third level array are entity IDs, keyed by revision IDs.
   *
   * @var array
   */
  protected array $associatedInitialRevisions = [];

  public function __construct(
    protected WorkspaceAssociationInterface $innerWorkspaceAssociation,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function trackEntity(RevisionableInterface $entity, WorkspaceInterface $workspace) {
    $this->innerWorkspaceAssociation->trackEntity($entity, $workspace);
    $this->associatedRevisions = $this->associatedInitialRevisions = [];
  }

  /**
   * Moves the given entity to another workspace.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity to move.
   * @param \Drupal\workspaces\WorkspaceInterface $source_workspace
   *   The workspace in which the entity is currently tracked.
   * @param \Drupal\workspaces\WorkspaceInterface $target_workspace
   *   The workspace in which the entity will be tracked.
   * @param bool $include_dependencies
   *   (optional) Whether to move all the dependencies too. Defaults to TRUE.
   */
  public function moveEntity(RevisionableInterface $entity, WorkspaceInterface $source_workspace, WorkspaceInterface $target_workspace, bool $include_dependencies = TRUE) {
    $affected_entity_ids[$entity->getEntityTypeId()][$entity->id()] = TRUE;

    // Use the 'depcalc' module, if available, for gathering all the
    // dependencies of the moved entity.
    if ($include_dependencies && \Drupal::moduleHandler()->moduleExists('depcalc')) {
      $this->gatherAffectedDependencies($affected_entity_ids, $entity, $source_workspace);
    }

    try {
      $transaction = $this->database->startTransaction();

      // Move all workspace-specific revisions to the new workspace.
      foreach ($affected_entity_ids as $entity_type_id => $entity_ids) {
        $affected_revision_ids = $this->getAssociatedRevisions($source_workspace->id(), $entity_type_id, array_keys($entity_ids));
        $affected_revisions = $this->entityTypeManager->getStorage($entity_type_id)
          ->loadMultipleRevisions(array_keys($affected_revision_ids));

        foreach ($affected_revisions as $revision) {
          $field_name = $revision->getEntityType()
            ->getRevisionMetadataKey('workspace');
          $revision->{$field_name}->target_id = $target_workspace->id();
          $revision->setSyncing(TRUE);
          $revision->save();

          // Delete the association entries for the source workspace, and track
          // the entity in the target workspace.
          $this->innerWorkspaceAssociation->deleteAssociations($source_workspace->id(), $revision->getEntityTypeId(), [$revision->id()]);
          $this->innerWorkspaceAssociation->trackEntity($revision, $target_workspace);
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      Error::logException($this->logger, $e);
      throw $e;
    }
  }

  /**
   * Retrieves all the dependencies of an entity.
   */
  protected function gatherAffectedDependencies(array &$affected_entity_ids, RevisionableInterface $entity, WorkspaceInterface $source_workspace) {
    $dependencies = \Drupal::service('workspaces.manager')->executeInWorkspace($source_workspace->id(), function () use ($entity) {
      $wrapper = new DependentEntityWrapper($entity);
      $dependency_stack = new DependencyStack();
      $dependency_stack->ignoreCache(TRUE);

      return \Drupal::service('entity.dependency.calculator')->calculateDependencies($wrapper, $dependency_stack);
    });

    /** @var \Drupal\depcalc\DependentEntityWrapperInterface $dependency */
    $tracked_entities = $this->getTrackedEntities($source_workspace->id());
    foreach ($dependencies as $dependency) {
      if ($dependency instanceof DependentEntityWrapperInterface
          && isset($tracked_entities[$dependency->getEntityTypeId()])
          && in_array($dependency->getId(), $tracked_entities[$dependency->getEntityTypeId()])) {
        $affected_entity_ids[$dependency->getEntityTypeId()][$dependency->getId()] = TRUE;
      }
    }
  }

  /**
   * Discards the changes for an entity in the given workspace.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity to discard.
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace in which the entity will be discarded.
   */
  public function discardEntity(RevisionableInterface $entity, WorkspaceInterface $workspace) {
    // Discard all workspace-specific revisions of the given entity.
    $associated_revisions = $this->getAssociatedRevisions($workspace->id(), $entity->getEntityTypeId(), [$entity->id()]);
    $associated_entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    // Sort the associated revisions in reverse ID order, so we can delete the
    // most recent revisions first.
    krsort($associated_revisions);

    // Get a list of default revisions tracked by the given workspace, because
    // they need to be handled differently than pending revisions.
    $initial_revision_ids = $this->getAssociatedInitialRevisions($workspace->id(), $entity->getEntityTypeId(), [$entity->id()]);

    foreach (array_keys($associated_revisions) as $revision_id) {
      // If the workspace is tracking the entity's default revision (i.e. the
      // entity was created inside that workspace), we need to delete the
      // whole entity after all of its pending revisions are gone.
      if (isset($initial_revision_ids[$revision_id])) {
        $associated_entity_storage->delete([$associated_entity_storage->load($initial_revision_ids[$revision_id])]);
      }
      else {
        // Delete the associated entity revision.
        $associated_entity_storage->deleteRevision($revision_id);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function workspaceInsert(WorkspaceInterface $workspace) {
    $this->innerWorkspaceAssociation->workspaceInsert($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackedEntities($workspace_id, $entity_type_id = NULL, $entity_ids = NULL, $offset = NULL, $limit = NULL) {
    // For closed workspaces, try to use the data from the published revision
    // storage.
    $workspace = Workspace::load($workspace_id);
    if ($workspace && wse_workspace_get_status($workspace) === WSE_STATUS_CLOSED) {
      // We can't inject the published revision storage because it causes a
      // circular dependency with the workspace association service.
      return \Drupal::service('wse.published_revision_storage')->getPublishedRevisions($workspace_id, $offset, $limit);
    }

    return $this->innerWorkspaceAssociation->getTrackedEntities($workspace_id, $entity_type_id, $entity_ids, $offset, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackedEntitiesForListing($workspace_id, ?int $pager_id = NULL, int|false $limit = 50): array {
    return $this->innerWorkspaceAssociation->getTrackedEntitiesForListing($workspace_id, $pager_id, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssociatedRevisions($workspace_id, $entity_type_id, $entity_ids = NULL) {
    if (isset($this->associatedRevisions[$workspace_id][$entity_type_id])) {
      if ($entity_ids) {
        return array_intersect($this->associatedRevisions[$workspace_id][$entity_type_id], $entity_ids);
      }
      else {
        return $this->associatedRevisions[$workspace_id][$entity_type_id];
      }
    }

    // WSE ensures that workspaces have unique IDs, so we can simplify core's
    // way of retrieving the associated revisions.
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entity_type->get('revision_metadata_keys')['workspace'], $workspace_id, '=')
      ->sort($entity_type->getKey('revision'), 'ASC');

    if ($entity_ids) {
      $query->condition($entity_type->getKey('id'), $entity_ids, 'IN');
    }

    $result = $query->execute();

    // Cache the list of associated entity IDs if the full list was requested.
    if (!$entity_ids) {
      $this->associatedRevisions[$workspace_id][$entity_type_id] = $result;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssociatedInitialRevisions(string $workspace_id, string $entity_type_id, array $entity_ids = []) {
    if (isset($this->associatedInitialRevisions[$workspace_id][$entity_type_id])) {
      if ($entity_ids) {
        return array_intersect($this->associatedInitialRevisions[$workspace_id][$entity_type_id], $entity_ids);
      }
      else {
        return $this->associatedInitialRevisions[$workspace_id][$entity_type_id];
      }
    }

    // WSE ensures that workspaces have unique IDs, so we can simplify core's
    // way of retrieving the associated initial (default) revisions.
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entity_type->get('revision_metadata_keys')['revision_default'], TRUE)
      ->condition($entity_type->get('revision_metadata_keys')['workspace'], $workspace_id, '=');

    if ($entity_ids) {
      $query->condition($entity_type->getKey('id'), $entity_ids, 'IN');
    }

    $result = $query->execute();

    // Cache the list of associated entity IDs if the full list was requested.
    if (!$entity_ids) {
      $this->associatedInitialRevisions[$workspace_id][$entity_type_id] = $result;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTrackingWorkspaceIds(RevisionableInterface $entity, bool $latest_revision = FALSE) {
    return $this->innerWorkspaceAssociation->getEntityTrackingWorkspaceIds($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function postPublish(WorkspaceInterface $workspace) {
    $this->innerWorkspaceAssociation->postPublish($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAssociations($workspace_id = NULL, $entity_type_id = NULL, $entity_ids = NULL, $revision_ids = NULL) {
    $this->innerWorkspaceAssociation->deleteAssociations($workspace_id, $entity_type_id, $entity_ids);
    $this->associatedRevisions = $this->associatedInitialRevisions = [];
  }

  /**
   * {@inheritdoc}
   */
  public function initializeWorkspace(WorkspaceInterface $workspace) {
    $this->innerWorkspaceAssociation->initializeWorkspace($workspace);
    $this->associatedRevisions = $this->associatedInitialRevisions = [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Workspace association records cleanup should happen as late as possible.
    $events[WorkspacePostPublishEvent::class][] = ['onPostPublish', -500];
    return $events;
  }

  /**
   * Triggers clean-up operations after a workspace is published.
   *
   * @param \Drupal\workspaces\Event\WorkspacePostPublishEvent $event
   *   The workspace publish event.
   */
  public function onPostPublish(WorkspacePostPublishEvent $event): void {
    $this->innerWorkspaceAssociation->onPostPublish($event);
  }

}
