<?php

namespace Drupal\wse\Controller;

use Drupal\Core\Entity\Controller\VersionHistoryController;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Overrides core's VersionHistoryController.
 */
class WseVersionHistoryController extends VersionHistoryController {

  use VersionHistoryTrait;

  /**
   * {@inheritdoc}
   */
  protected function revisionOverview(RevisionableInterface $entity): array {
    $build = parent::revisionOverview($entity);

    $this->alterRevisionsTable($build['entity_revisions_table']['#rows'], $entity);
    $build['entity_revisions_table']['#header']['workspace'] = $this->t('Workspace');

    return $build;
  }

}
