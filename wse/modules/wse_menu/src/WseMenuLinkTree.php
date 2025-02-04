<?php

namespace Drupal\wse_menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Implements the loading, transforming and rendering of menu link trees.
 */
class WseMenuLinkTree implements MenuLinkTreeInterface {

  /**
   * Constructs a WseMenuLinkTree object.
   */
  public function __construct(
    protected MenuLinkTreeInterface $inner,
    protected WorkspaceManagerInterface $workspaceManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCurrentRouteMenuTreeParameters($menu_name) {
    return $this->inner->getCurrentRouteMenuTreeParameters($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function load($menu_name, MenuTreeParameters $parameters) {
    return $this->inner->load($menu_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function transform(array $tree, array $manipulators) {
    return $this->inner->transform($tree, $manipulators);
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $tree) {
    $build = $this->inner->build($tree);

    if ($this->workspaceManager->hasActiveWorkspace()) {
      // @todo Look into workspace-specific cache tags.
      $workspace_cache_tags = $this->workspaceManager->getActiveWorkspace()->getCacheTags();
      $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $workspace_cache_tags);

      // Ensure that menu trees are cached per workspace.
      $build['#cache']['contexts'][] = 'workspace';
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function maxDepth() {
    return $this->inner->maxDepth();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtreeHeight($id) {
    return $this->inner->getSubtreeHeight($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpanded($menu_name, array $parents) {
    return $this->inner->getExpanded($menu_name, $parents);
  }

}
