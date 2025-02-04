<?php

namespace Drupal\wse;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\wse\Diff\WseDiffEntityParser;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service provider for the Workspaces module.
 */
class WseServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['diff'])) {
      $container->register('diff.entity_parser.wse', WseDiffEntityParser::class)
        ->setDecoratedService('diff.entity_parser')
        ->setPublic(FALSE)
        ->addArgument(new Reference('plugin.manager.diff.builder'))
        ->addArgument(new Reference('config.factory'));
    }
  }

}
