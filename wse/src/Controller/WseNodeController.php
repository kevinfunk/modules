<?php

namespace Drupal\wse\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;

/**
 * Overrides core's NodeController.
 */
class WseNodeController extends NodeController implements ContainerInjectionInterface {

  use VersionHistoryTrait;

  /**
   * {@inheritdoc}
   */
  public function revisionOverview(NodeInterface $node) {
    $build = parent::revisionOverview($node);

    $storage = $this->entityTypeManager()->getStorage('node');
    assert($storage instanceof NodeStorageInterface);
    $revisions = $storage->loadMultipleRevisions($this->getRevisionIds($node, $storage));
    $this->alterRevisionsTable($build['node_revisions_table']['#rows'], $node, $revisions);
    $build['node_revisions_table']['#header']['workspace'] = $this->t('Workspace');

    return $build;
  }

}
