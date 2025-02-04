<?php

namespace Drupal\wse_deploy\EventSubscriber;

use Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher;
use Drupal\jsonapi\JsonApiResource\ResourceObject;

/**
 * WSE override for the JSON:API normalization cacher service.
 */
class WseResourceObjectNormalizationCacher extends ResourceObjectNormalizationCacher {

  /**
   * {@inheritdoc}
   */
  protected static function generateLookupRenderArray(ResourceObject $object) {
    $cache_info = parent::generateLookupRenderArray($object);

    $workspace_manager = \Drupal::service('workspaces.manager');
    if ($workspace_manager->hasActiveWorkspace()) {
      $cache_info['#cache']['keys'][] = 'workspace--' . $workspace_manager->getActiveWorkspace()->id();
    }

    return $cache_info;
  }

}
