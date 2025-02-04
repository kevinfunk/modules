<?php

namespace Drupal\wse_group_access\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for wse_group_access routes.
 *
 * @internal
 *   Tagged services are internal.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route_names = [
      'entity.workspace.publish_form',
      'entity.workspace.merge_form',
    ];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->addRequirements(['_wse_group_access' => 'TRUE']);
      }
    }
  }

}
