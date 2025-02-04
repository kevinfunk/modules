<?php

declare(strict_types=1);

namespace Drupal\wse\Diff\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\diff\Form\RevisionOverviewForm;
use Drupal\node\NodeStorageInterface;
use Drupal\wse\Controller\VersionHistoryTrait;

/**
 * Provides a form for revision overview page.
 */
class WseRevisionOverviewForm extends RevisionOverviewForm {

  use VersionHistoryTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL): array {
    $form = parent::buildForm($form, $form_state, $node);

    $storage = $this->entityTypeManager->getStorage('node');
    assert($storage instanceof NodeStorageInterface);
    // @phpstan-ignore-next-line
    $revisions = $storage->loadMultipleRevisions($this->getRevisionIds($node));
    $this->alterRevisionsTable($form['node_revisions_table'], $node, $revisions);
    $form['node_revisions_table']['#header']['workspace'] = $this->t('Workspace');

    return $form;
  }

}
