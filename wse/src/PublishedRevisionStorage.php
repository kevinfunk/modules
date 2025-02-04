<?php

namespace Drupal\wse;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * The published revision storage service.
 */
class PublishedRevisionStorage {

  use DependencySerializationTrait;

  /**
   * The name of the table in which data is stored.
   *
   * @var string
   */
  const TABLE = 'workspace_published_revisions';

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly WorkspaceAssociationInterface $workspacesAssociation,
    protected readonly Connection $connection,
    protected readonly TimeInterface $time,
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly WorkspaceInformationInterface $workspacesInformation,
  ) {}

  /**
   * Stores revision IDs about to be published from the given workspace.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace to collect the revision IDs from.
   */
  public function storePublishedRevisions(WorkspaceInterface $workspace) {
    $tracked_entities = $this->workspacesAssociation->getTrackedEntities($workspace->id());

    $revert_revision_ids = [];
    foreach ($tracked_entities as $entity_type_id => $tracked_entity_ids) {
      $result = $this->workspaceManager->executeOutsideWorkspace(function () use ($entity_type_id, $tracked_entity_ids) {
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        return $this->entityTypeManager->getStorage($entity_type_id)
          ->getQuery()
          ->condition($entity_type->getKey('id'), $tracked_entity_ids, 'IN')
          ->accessCheck(FALSE)
          ->currentRevision()
          ->execute();
      });

      if ($result) {
        $revert_revision_ids[$entity_type_id] = $result;
      }
    }

    $this->connection->insert(static::TABLE)
      ->fields([
        'workspace_id' => $workspace->id(),
        'workspace_label' => $workspace->label(),
        'published_on' => $this->time->getCurrentTime(),
        'published_revision_ids' => Json::encode($tracked_entities),
        'revert_revision_ids' => Json::encode($revert_revision_ids),
      ])
      ->execute();
  }

  /**
   * Retrieves all the revisions that were published as part of a workspace.
   *
   * @param string $workspace_id
   *   The workspace ID.
   * @param int|null $offset
   *   The zero-based offset of the first result returned.
   * @param int|null $limit
   *   The number of results to return.
   *
   * @return array
   *   Returns a multidimensional array where the first level keys are entity
   *   type IDs and the values are an array of entity IDs keyed by revision IDs.
   */
  public function getPublishedRevisions($workspace_id, $offset = NULL, $limit = NULL) {
    $published_revisions = [];

    $result = $this->connection->select(static::TABLE, 'wpr')
      ->fields('wpr', ['published_revision_ids'])
      ->condition('workspace_id', $workspace_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($result) {
      $published_revisions = JSON::decode($result);

      if ($offset || $limit) {
        // Flatten the multidimensional array, so we can slice it with the
        // offset and limit passed.
        $all_published_revision_ids = [];
        foreach ($published_revisions as $entity_type_id => $published_revision_ids) {
          foreach ($published_revision_ids as $revision_id => $entity_id) {
            $all_published_revision_ids[$entity_type_id . ':' . $revision_id] = $entity_id;
          }
        }

        $published_revisions = [];
        $slice = array_slice($all_published_revision_ids, $offset, $limit);
        foreach ($slice as $key => $entity_id) {
          [$entity_type_id, $revision_id] = explode(':', $key, 2);
          $published_revisions[$entity_type_id][$revision_id] = $entity_id;
        }
      }
    }

    return $published_revisions;
  }

  /**
   * Retrieves the revert revisions for a workspace.
   *
   * @param string $workspace_id
   *   The workspace ID.
   *
   * @return array
   *   Returns a multidimensional array where the first level keys are entity
   *   type IDs and the values are an array of entity IDs keyed by revision IDs.
   */
  public function getRevertRevisions($workspace_id) {
    $revert_revisions = [];

    $result = $this->connection->select(static::TABLE, 'wpr')
      ->fields('wpr', ['revert_revision_ids'])
      ->condition('workspace_id', $workspace_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($result) {
      $revert_revisions = JSON::decode($result);
    }

    return $revert_revisions;
  }

  /**
   * Stores all current default revision IDs after the workspace was published.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace to save the revision snapshot for.
   */
  public function storeAllRevisions(WorkspaceInterface $workspace) {
    $all_revision_ids = [];

    foreach (array_keys($this->workspacesInformation->getSupportedEntityTypes()) as $entity_type_id) {
      $result = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->getQuery()
        ->accessCheck(FALSE)
        ->currentRevision()
        ->execute();

      if ($result) {
        $all_revision_ids[$entity_type_id] = $result;
      }
    }

    if ($all_revision_ids) {
      $record_id = $this->connection->select(static::TABLE, 'wpr')
        ->fields('wpr', ['id'])
        ->condition('workspace_id', $workspace->id())
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      $this->connection->update(static::TABLE)
        ->condition('id', $record_id)
        ->fields(['all_revision_ids' => Json::encode($all_revision_ids)])
        ->execute();
    }
  }

  /**
   * Retrieves the last published workspace.
   *
   * @return string
   *   The ID of the last published workspace.
   */
  public function getLastPublishedWorkspaceId() {
    return $this->connection->select(static::TABLE, 'wpr')
      ->fields('wpr', ['workspace_id'])
      ->orderBy('published_on', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Deletes the published revision record for a given workspace.
   *
   * @param string $workspace_id
   *   The workspace ID.
   */
  public function deleteRecord($workspace_id) {
    $this->connection->delete(static::TABLE)
      ->condition('workspace_id', $workspace_id)
      ->execute();
  }

}
