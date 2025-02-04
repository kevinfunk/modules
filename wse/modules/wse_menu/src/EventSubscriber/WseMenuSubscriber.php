<?php

namespace Drupal\wse_menu\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\State\StateInterface;
use Drupal\views\Entity\View;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse\Event\WorkspaceEvents;
use Drupal\wse\Event\WorkspaceRevertEvent;
use Drupal\wse_menu\Entity\WseMenuTree;
use Drupal\wse_menu\WseMenuTreeStorageInterface;
use Drupal\wse_menu\WseViewsMenuLink;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles menu tree changes on workspace operations.
 */
class WseMenuSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceAssociationInterface $workspaceAssociation,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StaticMenuLinkOverridesInterface $staticMenuLinkOverrides,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected StateInterface $state,
    protected WseMenuTreeStorageInterface $treeStorage,
  ) {}

  /**
   * Publishes workspace-specific menu link overrides.
   *
   * @param \Drupal\workspaces\Event\WorkspacePrePublishEvent $event
   *   The workspace pre-publish event.
   */
  public function onWorkspacePrePublish(WorkspacePrePublishEvent $event): void {
    $overrides = $this->workspaceManager->executeInWorkspace($event->getWorkspace()->id(), function () {
      /** @var \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_menu_link_overrides */
      $static_menu_link_overrides = \Drupal::service('menu_link.static.overrides');

      $definitions = WseMenuTree::load(1)->getDefinitions();
      return $static_menu_link_overrides->loadMultipleOverrides(array_keys($definitions));
    });

    $this->workspaceManager->executeOutsideWorkspace(function () use ($overrides) {
      /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
      $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

      foreach ($overrides as $id => $override) {
        $menu_link_manager->updateDefinition($id, $override);
      }
    });
  }

  /**
   * Publishes workspace-specific menu link overrides.
   *
   * @param \Drupal\workspaces\Event\WorkspacePostPublishEvent $event
   *   The workspace post-publish event.
   */
  public function onWorkspacePostPublish(WorkspacePostPublishEvent $event): void {
    // Clean up the workspace-specific menu tree table.
    $this->treeStorage->cleanupWorkspaceMenuTree($event->getWorkspace());

    // Resave the menu link definitions in Live in order to ensure that they're
    // parented correctly.
    $this->workspaceManager->executeOutsideWorkspace(function () use ($event) {
      $menu_link_ids = $event->getPublishedRevisionIds()['menu_link_content'] ?? [];
      if ($menu_link_ids) {
        $menu_links = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadMultiple($menu_link_ids);

        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $menu_links */
        foreach ($menu_links as $menu_link) {
          $this->menuLinkManager->updateDefinition($menu_link->getPluginId(), $menu_link->getPluginDefinition(), FALSE);
        }

        // Set the wse_menu_tree_needs_rebuild flag.
        wse_menu_set_menu_rebuild_flag();
      }
    });

    // Handle menu items provided by Views.
    // @todo Think about reverts. We're currently deleting the workspace
    //   overrides at the end.
    $workspace_id = $event->getWorkspace()->id();
    $all_overrides = $this->state->get(WseViewsMenuLink::STATE_KEY, []);

    if (isset($all_overrides[$workspace_id])) {
      $overrides = $all_overrides[$workspace_id];
      $this->workspaceManager->executeOutsideWorkspace(function () use ($overrides) {
        foreach ($overrides as $view_id => $displays) {
          if (!$view_entity = View::load($view_id)) {
            continue;
          }

          $view = $view_entity->getExecutable();
          foreach ($displays as $display_id => $display_overrides) {
            $view->setDisplay($display_id);
            $view->initDisplay();

            $display = &$view->storage->getDisplay($view->current_display);
            foreach ($display_overrides as $key => $new_definition_value) {
              $display['display_options']['menu'][$key] = $new_definition_value;
            }
          }

          $view->storage->save();
        }
      });

      // Remove the override info for the published workspace.
      unset($all_overrides[$workspace_id]);
      $this->state->set(WseViewsMenuLink::STATE_KEY, $all_overrides);
    }
  }

  /**
   * Rebuilds the workspace-specific menu tree.
   *
   * @param \Drupal\wse\Event\WorkspaceRevertEvent $event
   *   The event object.
   */
  public function onWorkspacePostRevert(WorkspaceRevertEvent $event): void {
    // When reverting a workspace that contains menu link changes, we need to
    // get the Live menu tree to the same state it was in before the workspace
    // was published.
    $this->workspaceManager->executeOutsideWorkspace(function () use ($event) {
      $menu_link_ids = $this->workspaceAssociation->getTrackedEntities($event->getWorkspace()->id())['menu_link_content'] ?? [];
      $initial_revisions = $this->workspaceAssociation
        ->getAssociatedInitialRevisions($event->getWorkspace()->id(), 'menu_link_content');

      if ($menu_link_ids) {
        $menu_links = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadMultiple($menu_link_ids);

        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $menu_links */
        foreach ($menu_links as $menu_link) {
          if (!in_array($menu_link->id(), $initial_revisions)) {
            // Menu link definitions that were only changed in the reverted
            // workspace have to be updated, so they're re-parented correctly.
            $this->menuLinkManager->updateDefinition($menu_link->getPluginId(), $menu_link->getPluginDefinition(), FALSE);
          }
          else {
            // Menu link definitions that were initially created in that
            // workspace have to be deleted from the Live menu tree.
            $this->menuLinkManager->removeDefinition($menu_link->getPluginId(), FALSE);
          }
        }
      }
    });

    $this->workspaceManager->executeInWorkspace($event->getWorkspace()->id(), function () use ($event) {
      $this->treeStorage->rebuildWorkspaceMenuTree($event->getWorkspace());
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // @todo Bring back support for static and views menu link overrides.
    // $events[WorkspacePrePublishEvent::class][] = 'onWorkspacePrePublish';
    $events[WorkspacePostPublishEvent::class][] = 'onWorkspacePostPublish';
    $events[WorkspaceEvents::WORKSPACE_POST_REVERT][] = 'onWorkspacePostRevert';
    return $events;
  }

}
