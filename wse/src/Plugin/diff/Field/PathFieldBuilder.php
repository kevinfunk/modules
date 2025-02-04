<?php

namespace Drupal\wse\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\FieldDiffBuilderBase;

/**
 * Plugin to diff path fields.
 *
 * @FieldDiffBuilder(
 *   id = "path_field_diff_builder",
 *   label = @Translation("Path Field Diff"),
 *   field_types = {
 *     "path"
 *   },
 * )
 */
class PathFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): mixed {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['alias'])) {
          $result[$field_key][] = $values['alias'];
        }
      }
    }

    return $result;
  }

}
