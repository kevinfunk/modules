<?php

namespace Drupal\wse;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse\Event\WorkspaceEvents;
use Drupal\wse\Event\WorkspaceRevertEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service for reverting workspace contents.
 */
class WorkspaceReverter {

  use LoggerChannelTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * The published revision storage service.
   *
   * @var \Drupal\wse\PublishedRevisionStorage
   */
  protected $publishedRevisionStorage;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new WorkspaceReverter instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   * @param \Drupal\wse\PublishedRevisionStorage $published_revision_storage
   *   The published revision storage.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, WorkspaceManagerInterface $workspace_manager, WorkspaceAssociationInterface $workspace_association, PublishedRevisionStorage $published_revision_storage, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->workspaceManager = $workspace_manager;
    $this->workspaceAssociation = $workspace_association;
    $this->publishedRevisionStorage = $published_revision_storage;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Reverts the revisions published by a workspace.
   */
  public function revert(WorkspaceInterface $workspace) {
    $revert_to_revisions = $this->publishedRevisionStorage->getRevertRevisions($workspace->id());
    $revert_from_revisions = $this->publishedRevisionStorage->getPublishedRevisions($workspace->id());
    $this->eventDispatcher->dispatch(new WorkspaceRevertEvent($workspace, $revert_to_revisions, $revert_from_revisions), WorkspaceEvents::WORKSPACE_PRE_REVERT);

    $transaction = $this->database->startTransaction();
    try {
      // Roll back the revisions of this workspace.
      $this->workspaceManager->executeOutsideWorkspace(function () use ($revert_to_revisions) {
        foreach ($revert_to_revisions as $entity_type_id => $revert_revision_ids) {
          // Split the revisions into chunks to avoid loading too many entities.
          $revert_revision_chunks = array_chunk($revert_revision_ids, 100, TRUE);

          foreach ($revert_revision_chunks as $revert_revision_chunk) {
            $revisions = $this->entityTypeManager->getStorage($entity_type_id)
              ->loadMultipleRevisions(array_keys($revert_revision_chunk));

            foreach ($revisions as $revision) {
              $revision->setSyncing(TRUE);
              $revision->isDefaultRevision(TRUE);
              $revision->save();

              // Extend the execution time after each entity save, so we give
              // ample time for e.g. cascading operations.
              Environment::setTimeLimit(30);
            }
          }
        }
      });

      // Re-associate the published revisions to the workspace that is being
      // reverted.
      foreach ($revert_from_revisions as $entity_type_id => $revision_ids) {
        // Split the revisions into chunks to avoid loading too many entities.
        $revert_from_revision_chunks = array_chunk($revision_ids, 100, TRUE);

        foreach ($revert_from_revision_chunks as $revert_from_revision_chunk) {
          $revisions = $this->entityTypeManager->getStorage($entity_type_id)
            ->loadMultipleRevisions(array_keys($revert_from_revision_chunk));

          foreach ($revisions as $revision) {
            $revision->setSyncing(TRUE);
            $revision->isDefaultRevision(FALSE);

            // Track this revision in its previous workspace.
            $workspace_key = $revision->getEntityType()->getRevisionMetadataKey('workspace');
            $revision->set($workspace_key, $workspace->id());
            $this->workspaceAssociation->trackEntity($revision, $workspace);

            // Unset the 'was default revision' flag.
            $revision_default_key = $revision->getEntityType()->getRevisionMetadataKey('revision_default');
            $revision->set($revision_default_key, FALSE);

            $revision->save();

            // Extend the execution time after each entity save, so we give
            // ample time for e.g. cascading operations.
            Environment::setTimeLimit(30);
          }
        }
      }

      // Delete the published revisions record for this workspace.
      $this->publishedRevisionStorage->deleteRecord($workspace->id());

      // Move the workspace back to the 'open' status.
      $workspace->set('status', WSE_STATUS_OPEN)->save();
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      Error::logException($this->getLogger('workspaces'), $e);
      throw $e;
    }

    $this->eventDispatcher->dispatch(new WorkspaceRevertEvent($workspace, $revert_to_revisions, $revert_from_revisions), WorkspaceEvents::WORKSPACE_POST_REVERT);
  }

}
