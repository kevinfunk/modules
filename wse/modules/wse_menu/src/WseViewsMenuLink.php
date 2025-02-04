<?php

namespace Drupal\wse_menu;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\views\Plugin\Menu\ViewsMenuLink;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides support for workspace-specific updates to menu links from views.
 */
class WseViewsMenuLink extends ViewsMenuLink implements ContainerFactoryPluginInterface {

  /**
   * The key of the state entry where overrides are stored.
   */
  const STATE_KEY = 'wse_menu.views_menu_link_overrides';

  /**
   * The workspace manager.
   */
  protected WorkspaceManagerInterface $workspaceManager;

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->workspaceManager = $container->get('workspaces.manager');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return parent::updateLink($new_definition_values, $persist);
    }

    $overrides = array_intersect_key($new_definition_values, $this->overrideAllowed);

    // Update the definition.
    $this->pluginDefinition = $overrides + $this->pluginDefinition;

    // Store the overridden definition values in state.
    if ($persist) {
      $active_workspace_id = $this->workspaceManager->getActiveWorkspace()->id();
      $metadata = $this->getMetaData();
      $view_id = $metadata['view_id'];
      $display_id = $metadata['display_id'];

      $all_overrides = $this->state->get(static::STATE_KEY);
      $all_overrides[$active_workspace_id][$view_id][$display_id] = $overrides;
      $this->state->set(static::STATE_KEY, $all_overrides);
    }

    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function isExpanded() {
    return (bool) $this->pluginDefinition['expanded'];
  }

}
