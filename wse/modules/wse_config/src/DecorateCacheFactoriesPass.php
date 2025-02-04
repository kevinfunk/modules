<?php

namespace Drupal\wse_config;

use Drupal\wse_config\Cache\WseCacheBackendFactory;
use Drupal\wse_config\Cache\WseChainedFastBackendFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Decorates cache factories to make cache entries workspace specific.
 */
class DecorateCacheFactoriesPass implements CompilerPassInterface {

  /**
   * Implements CompilerPassInterface::process().
   */
  public function process(ContainerBuilder $container) {
    $services = $container->getDefinitions();
    foreach ($services as $service_id => $definition) {
      $service_class = $definition->getClass();
      try {
        if (!$service_class || !class_exists($service_class)) {
          continue;
        }
      }
      catch (\Throwable $e) {
        // If this fails then a service has a dependency that is unmet. See
        // https://www.drupal.org/project/drupal/issues/3493595.
        continue;
      }
      $interfaces = class_implements($service_class);
      if (in_array('Drupal\Core\Cache\CacheFactoryInterface', $interfaces) && $service_class != 'Drupal\Core\Cache\CacheFactory') {
        $decorated_service_id = $service_id . '.wse';
        if (!in_array($decorated_service_id, $services)) {
          if ($service_id == 'cache.backend.chainedfast') {
            $container->register($decorated_service_id, WseChainedFastBackendFactory::class)
              ->setDecoratedService($service_id)
              ->setArguments([
                new Reference($decorated_service_id . '.inner'),
                new Reference('wse.workspace_id_detector'),
                new Reference('service_container'),
                new Reference('settings'),
              ]);
          }
          else {
            $container->register($decorated_service_id, WseCacheBackendFactory::class)
              ->setDecoratedService($service_id)
              ->setArguments([
                new Reference($decorated_service_id . '.inner'),
                new Reference('wse.workspace_id_detector'),
              ]);
          }
        }
      }
    }
  }

}
