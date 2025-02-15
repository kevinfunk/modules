<?php

namespace Drupal\wse_config;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a wse config entity type.
 */
class WseConfigViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    // The wse config has no entity template itself.
    unset($build['#theme']);
    return $build;
  }

}
