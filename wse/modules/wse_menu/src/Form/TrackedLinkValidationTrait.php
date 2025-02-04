<?php

declare(strict_types=1);

namespace Drupal\wse_menu\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Trait for validating links.
 */
trait TrackedLinkValidationTrait {

  /**
   * Validates that no form link is tracked in a non-active workspace.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateLinks(FormStateInterface $form_state): void {
    $links = $form_state->getValue('links') ?? [];
    $entity_ids = [];
    foreach ($links as $link) {
      $plugin_id = $link['id'] ?? '';
      if (!str_starts_with($plugin_id, 'menu_link_content:')) {
        continue;
      }
      try {
        $instance = $this->getLinkPluginManager()->createInstance($plugin_id);
      }
      catch (PluginException) {
        continue;
      }
      if (!($instance instanceof MenuLinkContent)) {
        continue;
      }
      $entity_id = $instance->getPluginDefinition()['metadata']['entity_id'] ?? NULL;
      if ($entity_id) {
        $entity_ids[] = $entity_id;
      }
    }
    $link_entities = $this->getEntityTypeManager()
      ->getStorage('menu_link_content')
      ->loadMultiple($entity_ids);

    $active_workspace = $this->getWorkspaceManager()->getActiveWorkspace();
    $association = $this->getWorkspaceAssociation();
    foreach ($link_entities as $link_entity) {
      assert($link_entity instanceof MenuLinkContentInterface);
      $tracked = $association->getEntityTrackingWorkspaceIds($link_entity);
      // Check if this link is tracked in a not currently active workspace.
      $diff = $active_workspace ? array_diff($tracked, [$active_workspace->id()]) : $tracked;
      if ($diff) {
        $tracking_workspaces = $this->getEntityTypeManager()
          ->getStorage('workspace')
          ->loadMultiple($diff);
        $labels = array_map(function (WorkspaceInterface $workspace) {
          return $workspace->label();
        }, $tracking_workspaces);

        $form_state->setErrorByName('links][' . $link_entity->getPluginId() . '][id', $this->t("Could not update menu link <em>@title</em> because it's tracked in other workspaces (@workspace_labels).", [
          '@title' => $link_entity->label(),
          '@workspace_labels' => implode(', ', $labels),
        ]));
      }
    }
  }

  /**
   * The entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The service.
   */
  private function getEntityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::entityTypeManager();
  }

  /**
   * The menu link plugin manager.
   *
   * @return \Drupal\Core\Menu\MenuLinkManagerInterface
   *   The service.
   */
  private function getLinkPluginManager(): MenuLinkManagerInterface {
    return \Drupal::service('plugin.manager.menu.link');
  }

  /**
   * The workspace manager service.
   *
   * @return \Drupal\workspaces\WorkspaceManagerInterface
   *   The service.
   */
  private function getWorkspaceManager(): WorkspaceManagerInterface {
    return \Drupal::service('workspaces.manager');
  }

  /**
   * The workspace association service.
   *
   * @return \Drupal\workspaces\WorkspaceAssociationInterface
   *   The service.
   */
  private function getWorkspaceAssociation(): WorkspaceAssociationInterface {
    return \Drupal::service('workspaces.association');
  }

}
