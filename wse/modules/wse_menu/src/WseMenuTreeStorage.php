<?php

namespace Drupal\wse_menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Overrides the menu tree storage to provide workspace-specific menu trees.
 *
 * @internal
 */
class WseMenuTreeStorage extends MenuTreeStorage implements WseMenuTreeStorageInterface {

  /**
   * The prefix for workspace-specific menu tree tables.
   */
  const TABLE_PREFIX = 'wse_menu_tree_';

  /**
   * The original menu tree table name.
   */
  protected string $originalTable;

  /**
   * Constructor.
   */
  public function __construct(
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceAssociationInterface $workspaceAssociation,
    protected EntityTypeManagerInterface $entityTypeManager,
    Connection $connection,
    CacheBackendInterface $menu_cache_backend,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    string $table,
    array $options = [],
  ) {
    parent::__construct($connection, $menu_cache_backend, $cache_tags_invalidator, $table, $options);
    $this->originalTable = $table;

    // @todo Take care of these points:
    //   - deal with static menu link overrides and views links
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild(array $definitions): void {
    // The menu tree should always be rebuilt without a workspace context.
    $this->workspaceManager->executeOutsideWorkspace(function () use ($definitions) {
      $current_table = $this->table;

      $this->table = $this->originalTable;
      parent::rebuild($definitions);
      $this->table = $current_table;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    $this->ensureTableOverride();
    return parent::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $this->ensureTableOverride();
    return parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $properties) {
    $this->ensureTableOverride();
    return parent::loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    $this->ensureTableOverride();
    return parent::loadByRoute($route_name, $route_parameters, $menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $definition) {
    $this->ensureTableOverride();
    return parent::save($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    $this->ensureTableOverride();
    parent::delete($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadTreeData($menu_name, MenuTreeParameters $parameters) {
    $this->ensureTableOverride();

    // Add the active workspace as a menu tree condition parameter in order to
    // include it in the cache ID.
    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $parameters->conditions['workspace'] = $active_workspace->id();
    }
    return parent::loadTreeData($menu_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllChildren($id, $max_relative_depth = NULL) {
    $this->ensureTableOverride();
    return parent::loadAllChildren($id, $max_relative_depth);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllChildIds($id) {
    $this->ensureTableOverride();
    return parent::getAllChildIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadSubtreeData($id, $max_relative_depth = NULL) {
    $this->ensureTableOverride();
    return parent::loadSubtreeData($id, $max_relative_depth);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootPathIds($id) {
    $this->ensureTableOverride();
    return parent::getRootPathIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpanded($menu_name, array $parents) {
    $this->ensureTableOverride();
    return parent::getExpanded($menu_name, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtreeHeight($id) {
    $this->ensureTableOverride();
    return parent::getSubtreeHeight($id);
  }

  /**
   * {@inheritdoc}
   */
  public function menuNameInUse($menu_name) {
    $this->ensureTableOverride();
    return parent::menuNameInUse($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuNames() {
    $this->ensureTableOverride();
    return parent::getMenuNames();
  }

  /**
   * {@inheritdoc}
   */
  public function countMenuLinks($menu_name = NULL) {
    $this->ensureTableOverride();
    return parent::countMenuLinks($menu_name);
  }

  /**
   * Ensures that the storage uses the possibly overridden menu tree table.
   */
  protected function ensureTableOverride(): void {
    $current_table = $this->table;

    // Use a workspace-specific menu tree table if needed.
    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->table = $this->getWorkspaceTableName($active_workspace);
    }
    else {
      $this->table = $this->originalTable;
    }

    // Reset the internal definitions if they were built with a different table.
    if ($this->table != $current_table) {
      $this->resetDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function ensureTableExists(): bool {
    $current_table = $this->table;

    // The parent method must always run with the original table name.
    $this->table = $this->originalTable;
    $return = parent::ensureTableExists();
    $this->table = $current_table;

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildWorkspaceMenuTree(WorkspaceInterface $workspace, bool $replay_changes = TRUE): void {
    $this->ensureTableExists();
    $table_name = $this->getWorkspaceTableName($workspace);

    if ($this->connection->schema()->tableExists($table_name)) {
      $this->connection->schema()->dropTable($table_name);
    }

    $this->connection->schema()->createTable($table_name, static::schemaDefinition());

    $select = $this->connection->select($this->originalTable)
      ->fields($this->originalTable);
    $this->connection->insert($table_name)
      ->from($select)
      ->execute();

    // Replay all the menu tree changes from this workspace.
    if ($replay_changes) {
      $current_table = $this->table;
      $this->table = $table_name;
      $definitions = [];

      $tracked_menu_link_ids = $this->workspaceAssociation
        ->getTrackedEntities($workspace->id(), 'menu_link_content')['menu_link_content'] ?? [];
      if ($tracked_menu_link_ids) {
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $menu_links */
        $menu_links = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadMultipleRevisions(array_keys($tracked_menu_link_ids));
        foreach ($menu_links as $menu_link) {
          $definitions[$menu_link->getPluginId()] = $menu_link->getPluginDefinition();
        }
      }

      // Sort the definitions by menu_name, parent and weight, for fewer
      // recursive calls below.
      $menu_name = array_column($definitions, 'menu_name');
      $parent = array_column($definitions, 'parent');
      $weight = array_column($definitions, 'weight');
      array_multisort($menu_name, SORT_ASC, $parent, SORT_ASC, $weight, SORT_ASC, $definitions);

      foreach ($definitions as $key => $definition) {
        $this->resaveDefinition($definitions, $key);
      }

      // Restore the original menu tree table name when we're finished.
      $this->table = $current_table;
    }

    // @todo Investigate whether we can target only workspace-specific caches.
    $this->menuCacheBackend->invalidateAll();
  }

  /**
   * Saves a menu definition, ensuring that its parent is saved first.
   *
   * @param array $definitions
   *   An array of menu tree definitions.
   * @param string $key
   *   The ID of the definition to save.
   */
  protected function resaveDefinition(array &$definitions, string $key): void {
    if (isset($definitions[$key])) {
      if (($parent = $definitions[$key]['parent']) && isset($definitions[$parent])) {
        $this->resaveDefinition($definitions, $parent);
      }
      parent::save($definitions[$key]);
      unset($definitions[$key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupWorkspaceMenuTree(WorkspaceInterface $workspace): void {
    $this->connection->schema()->dropTable($this->getWorkspaceTableName($workspace));
  }

  /**
   * {@inheritdoc}
   */
  public function getAllWorkspacesWithMenuTreeOverrides(): array {
    $uuids = [];
    foreach ($this->connection->schema()->findTables(static::TABLE_PREFIX . '%') as $table) {
      $uuids[] = str_replace('_', '-', substr($table, strlen(static::TABLE_PREFIX)));
    }

    if (!$uuids) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('workspace')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $uuids, 'IN')
      ->execute();
  }

  /**
   * Gets the workspace-specific menu tree table name.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace entity.
   *
   * @return string
   *   The workspace-specific menu tree table name.
   *
   * @internal
   *   This method is public only to aid in testing.
   */
  public function getWorkspaceTableName(WorkspaceInterface $workspace): string {
    // Workspaces currently have string IDs, but that might change in the
    // future, so it's safer to use its UUID.
    assert(is_string($workspace->uuid()));
    return static::TABLE_PREFIX . str_replace('-', '_', $workspace->uuid());
  }

}
