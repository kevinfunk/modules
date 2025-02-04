<?php

namespace Drupal\wse\Plugin\diff\Layout;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\diff\Plugin\diff\Layout\UnifiedFieldsDiffLayout;

/**
 * Provides a workspace-compatible Unified fields diff layout.
 *
 * @DiffLayoutBuilder(
 *   id = "wse_unified_fields",
 *   label = @Translation("Workspace - Unified fields"),
 *   description = @Translation("Field based layout, displays revision comparison line by line."),
 * )
 */
class WseUnifiedFieldsDiffLayout extends UnifiedFieldsDiffLayout {

  /**
   * {@inheritdoc}
   */
  protected function buildTableHeader(EntityInterface $right_revision): array {
    // Overrides the parent method because the WSE Diff controller doesn't
    // display this information, and the parent method only works for entity
    // types that provide a 'revision' link template.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRevisionData(ContentEntityInterface $revision): array {
    // Overrides the parent method because the WSE Diff controller doesn't
    // display this information, and the parent method only works for entity
    // types that provide a 'revision' link template.
    return [];
  }

}
