<?php

namespace Drupal\wse\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\wse\Controller\RevisionDiffController;
use Drupal\wse\Controller\WseDiffNodeRevisionController;
use Drupal\wse\Controller\WseNodeController;
use Drupal\wse\Controller\WseVersionHistoryController;
use Drupal\wse\Form\DiscardEntityForm;
use Drupal\wse\Form\MoveEntityForm;
use Drupal\wse\Form\WseWorkspacePublishForm;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for WSE routes.
 *
 * @internal
 *   Tagged services are internal.
 */
class RouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly WorkspaceInformationInterface $workspaceInfo,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Add the '_workspace_status' requirement to the 'activate workspace'
    // and 'publish workspace' routes, so they can't be used by closed
    // workspaces.
    if ($route = $collection->get('entity.workspace.activate_form')) {
      $route->addRequirements(['_workspace_status' => 'open']);
    }
    if ($route = $collection->get('entity.workspace.publish_form')) {
      $route->addRequirements(['_workspace_status' => 'open']);
    }

    // Use our publish form until the one from Drupal core gets the new
    // ::getWorkspace() and ::setWorkspace() methods.
    // @todo Fix this upstream.
    if ($route = $collection->get('entity.workspace.publish_form')) {
      $route->setDefault('_form', WseWorkspacePublishForm::class);
    }

    // Add routes for moving entities to another workspace and discarding
    // workspace changes.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->workspaceInfo->isEntityTypeSupported($entity_type) && $entity_type_id !== 'workspace') {
        $base_path = NULL;
        if ($entity_type->hasLinkTemplate('canonical')) {
          $base_path = $entity_type->getLinkTemplate('canonical');
        }
        elseif ($entity_type->hasLinkTemplate('edit-form')) {
          $base_path = $entity_type->getLinkTemplate('edit-form');
        }

        if (!$base_path) {
          continue;
        }

        $parameters = [
          $entity_type_id => [
            'type' => "entity:$entity_type_id",
          ],
          'source_workspace' => [
            'type' => 'entity:workspace',
          ],
        ];

        // Add a route for the 'move entity to another workspace' form.
        $route = new Route($base_path . '/move-to-workspace/{source_workspace}');
        $route
          ->addDefaults([
            '_form' => MoveEntityForm::class,
            'entity_type_id' => $entity_type_id,
          ])
          ->setRequirements([
            '_entity_access' => "$entity_type_id.update",
          ])
          ->setOption('parameters', $parameters)
          ->setOption('_admin_route', TRUE);
        $collection->add("entity.$entity_type_id.move_to_workspace", $route);

        // Add a route for the 'discard entity changes' form.
        $route = new Route($base_path . '/discard-changes/{source_workspace}');
        $route
          ->addDefaults([
            '_form' => DiscardEntityForm::class,
            'entity_type_id' => $entity_type_id,
          ])
          ->setRequirements([
            '_entity_access' => "$entity_type_id.update",
          ])
          ->setOption('parameters', $parameters)
          ->setOption('_admin_route', TRUE);
        $collection->add("entity.$entity_type_id.discard_changes", $route);

        // Add a route for the 'revision diff' controller.
        if ($this->moduleHandler->moduleExists('diff')) {
          $route = new Route($base_path . '/revision-diff/{source_workspace}');
          $route
            ->addDefaults([
              '_controller' => RevisionDiffController::class . '::getRevisionDiff',
              'entity_type_id' => $entity_type_id,
            ])
            ->setRequirements([
              '_entity_access' => "$entity_type_id.view",
            ])
            ->setOption('parameters', $parameters)
            ->setOption('_admin_route', TRUE);
          $collection->add("entity.$entity_type_id.workspace.revisions_diff", $route);
        }

        // Make revision overviews compatible with Workspaces.
        if ($route = $collection->get("entity.$entity_type_id.version_history")) {
          if ($entity_type_id === 'node') {
            if ($this->moduleHandler->moduleExists('diff')) {
              $route->setDefault('_controller', WseDiffNodeRevisionController::class . '::revisionOverview');
            }
            else {
              $route->setDefault('_controller', WseNodeController::class . '::revisionOverview');
            }
          }
          else {
            $route->setDefault('_controller', WseVersionHistoryController::class);
          }
        }
      }
    }
  }

}
