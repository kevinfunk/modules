<?php

namespace Drupal\wse_lb;

use Drupal\Core\Entity\EntityInterface;
use Drupal\workspaces\Entity\Handler\DefaultWorkspaceHandler;

/**
 * Customizations for block content entities.
 *
 * @internal
 */
class BlockContentWorkspaceHandler extends DefaultWorkspaceHandler {

  /**
   * {@inheritdoc}
   */
  public function isEntitySupported(EntityInterface $entity): bool {
    // Non-reusable (inline) blocks need to defer the workspace-support check to
    // the entity that is "using" them.
    /** @var \Drupal\block_content\BlockContentInterface $entity */
    if (!$entity->isReusable() && ($parent = $entity->getAccessDependency()) && $parent instanceof EntityInterface) {
      $entity = $parent;
    }

    return parent::isEntitySupported($entity);
  }

}
