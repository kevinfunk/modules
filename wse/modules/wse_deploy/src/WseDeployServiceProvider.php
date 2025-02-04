<?php

namespace Drupal\wse_deploy;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\wse_deploy\EventSubscriber\WseResourceObjectNormalizationCacher;

/**
 * Defines a service provider for the Workspace Deploy module.
 */
class WseDeployServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('jsonapi.normalization_cacher')) {
      $container->getDefinition('jsonapi.normalization_cacher')
        ->setClass(WseResourceObjectNormalizationCacher::class);
    }
  }

}
