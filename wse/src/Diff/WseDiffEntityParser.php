<?php

namespace Drupal\wse\Diff;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\diff\DiffEntityParser;

/**
 * Enhances the contrib diff entity parser with workspace revision support.
 */
class WseDiffEntityParser extends DiffEntityParser {

  /**
   * {@inheritdoc}
   */
  public function parseEntity(ContentEntityInterface $entity): array {
    $result = parent::parseEntity($entity);

    if (!$entity->hasField('path')) {
      return $result;
    }

    // Add the 'path' field manually.
    $plugin = $this->diffBuilderManager->createInstance('path_field_diff_builder', []);
    $path_build = $plugin->build($entity->get('path'));

    if (!empty($path_build)) {
      $result[$entity->id() . ':' . $entity->getEntityTypeId() . '.path'] = $path_build;
      $result[$entity->id() . ':' . $entity->getEntityTypeId() . '.path']['label'] = $entity->get('path')->getFieldDefinition()->getLabel();
    }

    return $result;
  }

}
