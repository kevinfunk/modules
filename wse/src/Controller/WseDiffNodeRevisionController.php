<?php

declare(strict_types=1);

namespace Drupal\wse\Controller;

use Drupal\diff\Controller\NodeRevisionController;
use Drupal\node\NodeInterface;
use Drupal\wse\Diff\Form\WseRevisionOverviewForm;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Overrides the node revision controller for the Diff module.
 */
class WseDiffNodeRevisionController extends NodeRevisionController {

  /**
   * {@inheritdoc}
   */
  public function revisionOverview(NodeInterface $node): array {
    if (!$node->access('view')) {
      throw new AccessDeniedHttpException();
    }

    return $this->formBuilder()->getForm(WseRevisionOverviewForm::class, $node);
  }

}
