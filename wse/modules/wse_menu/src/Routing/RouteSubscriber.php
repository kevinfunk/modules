<?php

declare(strict_types=1);

namespace Drupal\wse_menu\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.group_content_menu.delete_form')) {
      $route->addRequirements(['_has_active_workspace' => 'FALSE']);
    }
  }

}
