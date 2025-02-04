<?php

namespace Drupal\wse_menu;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service provider for the WSE Menu module.
 */
class WseMenuServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    if ($container->hasDefinition('toolbar.menu_tree')) {
      $container->register('wse_menu.toolbar.menu_tree', WseToolbarMenuLinkTree::class)
        ->setDecoratedService('toolbar.menu_tree')
        ->setPublic(FALSE)
        ->addArgument(new Reference('wse_menu.toolbar.menu_tree.inner'))
        ->addArgument(new Reference('workspaces.manager'));
    }

    if ($container->hasDefinition('workspaces.menu.tree_storage')) {
      $container->removeDefinition('workspaces.menu.tree_storage');
    }
  }

}
