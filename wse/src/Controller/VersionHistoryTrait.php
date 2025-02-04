<?php

namespace Drupal\wse\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;

/**
 * Provides helper methods for the revision overview controllers.
 */
trait VersionHistoryTrait {

  /**
   * Adds information about each revision's workspace.
   *
   * @param array $build
   *   The render array from the version history controller.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param array|null $revisions
   *   (optional) An array of revisions that are displayed in the table.
   */
  protected function alterRevisionsTable(array &$build, EntityInterface $entity, ?array $revisions = NULL): void {
    // Add a column to show the workspace in which a revision has been created.
    $keys = Element::children($build);
    if (!$revisions) {
      $revisions = \Drupal::entityTypeManager()
        ->getStorage($entity->getEntityTypeId())
        ->loadMultipleRevisions($keys);
    }

    $field_name = $entity->getEntityType()->getRevisionMetadataKey('workspace');
    foreach ($keys as $key) {
      $revision = current($revisions);

      $label = '';
      if ($workspace = $revision->get($field_name)->entity) {
        $label = $workspace->access('view label') ? $workspace->label() : $this->t('- Restricted access -');
      }

      // Insert the workspace label column.
      if (isset($build[$key]['data'])) {
        $build[$key]['data']['workspace'] = ['data' => $label];
      }
      elseif (isset($build[$key][0])) {
        $build[$key]['workspace'] = ['data' => $label];
      }
      else {
        $build[$key]['workspace'] = ['#markup' => $label];
      }

      next($revisions);
    }
  }

}
