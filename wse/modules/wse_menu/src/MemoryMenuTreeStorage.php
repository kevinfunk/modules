<?php

namespace Drupal\wse_menu;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides a menu tree storage using a content entity.
 *
 * This tree storage implementation was initially designed to handle menu tree
 * definitions stored in a content entity, but it can be repurposed into a more
 * generic "memory-only" menu tree storage.
 */
class MemoryMenuTreeStorage implements MenuTreeStorageInterface {

  /**
   * The maximum depth of a menu links tree.
   */
  const MAX_DEPTH = 9;

  /**
   * The menu tree entity that stores the menu link definitions.
   *
   * @var \Drupal\wse_menu\WseMenuTreeInterface
   */
  protected $menuTreeEntity;

  /**
   * Stores definitions that have already been loaded for better performance.
   *
   * An array of plugin definition arrays, keyed by plugin ID.
   *
   * @var array
   */
  protected $definitions = [];

  /**
   * List of plugin definition fields.
   *
   * @var array
   *
   * @todo Decide how to keep these field definitions in sync.
   *   https://www.drupal.org/node/2302085
   *
   * @see \Drupal\Core\Menu\MenuLinkManager::$defaults
   */
  protected $definitionFields = [
    'menu_name',
    'route_name',
    'route_parameters',
    'route_param_key',
    'url',
    'title',
    'description',
    'parent',
    'weight',
    'options',
    'expanded',
    'enabled',
    'provider',
    'metadata',
    'class',
    'form_class',
    'id',
  ];

  public function __construct(
    protected MenuTreeStorageInterface $inner,
    protected WorkspaceManagerInterface $workspaceManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function maxDepth() {
    return static::MAX_DEPTH;
  }

  /**
   * {@inheritdoc}
   */
  public function resetDefinitions() {
    if (!$this->applies()) {
      return $this->inner->resetDefinitions();
    }

    $this->definitions = $this->getMenuTreeEntity()->getDefinitions();
    $this->rebuildTree();
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild(array $definitions) {
    if (!$this->applies()) {
      return $this->inner->rebuild($definitions);
    }

    foreach ($definitions as $definition) {
      // Flag this link as discovered, i.e. saved via rebuild().
      $definition['discovered'] = 1;

      // Force orphan links to be top-level.
      if (!empty($definition['parent']) && !isset($definitions[$definition['parent']])) {
        $definition['parent'] = '';
      }
    }

    // Filter out discovered definitions that no longer exist.
    $no_longer_existing_definitions = array_filter($this->definitions, function (array $definition) use ($definitions) {
      return !empty($definition['discovered']) && !isset($definitions[$definition['id']]);
    });
    $this->definitions = array_diff_key($this->definitions, $no_longer_existing_definitions);

    // Append the definitions stored in the menu tree entity in order to keep
    // custom link definitions, for example those provided by the
    // 'menu_link_content' entity type.
    $this->definitions = $definitions + $this->definitions;
    $this->getMenuTreeEntity()->setDefinitions($definitions)->save();
    $this->rebuildTree();
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    if (!$this->applies()) {
      return $this->inner->load($id);
    }

    return $this->definitions[$id] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    if (!$this->applies()) {
      return $this->inner->loadMultiple($ids);
    }

    return $ids ? array_intersect_key($this->definitions, array_flip($ids)) : $this->definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $properties, bool $allow_all_properties = FALSE) {
    if (!$this->applies()) {
      return $this->inner->loadByProperties($properties);
    }

    if (!$allow_all_properties && $diff = array_diff(array_keys($properties), $this->definitionFields)) {
      $name = reset($diff);
      $fields = implode(', ', $this->definitionFields);
      throw new \InvalidArgumentException("An invalid property name, $name was specified. Allowed property names are: $fields.");
    }

    $parameters = new MenuTreeParameters();
    foreach ($properties as $name => $value) {
      $parameters->addCondition($name, $value);
    }

    return $this->loadLinks(NULL, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    if (!$this->applies()) {
      return $this->inner->loadByRoute($route_name, $route_parameters, $menu_name);
    }

    $properties = array_filter([
      'route_name' => $route_name,
      'route_parameters' => $route_parameters,
      'menu_name' => $menu_name,
    ]);

    $filtered = $this->loadByProperties($properties);

    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $definition) {
    if (!$this->applies()) {
      return $this->inner->save($definition);
    }

    // Gather the affected menu names.
    $affected_menus[$definition['menu_name']] = $definition['menu_name'];

    // The link might have moved to a different menu.
    if ($definition['parent'] && isset($this->definitions[$definition['parent']])) {
      $parent = $this->definitions[$definition['parent']];
      $definition['menu_name'] = $this->definitions[$definition['parent']]['menu_name'];
      $affected_menus[$definition['menu_name']] = $definition['menu_name'];
    }

    // If the definition has been re-parented, ensure that the new tree doesn't
    // exceed the maximum depth.
    if (isset($this->definitions[$definition['id']])) {
      $limit = $this->maxDepth() - $this->doFindChildrenRelativeDepth($this->definitions[$definition['id']]) - 1;
    }
    else {
      $limit = $this->maxDepth() - 1;
    }

    if (isset($parent) && $parent['depth'] > $limit) {
      throw new PluginException("The link with ID {$definition['id']} or its children exceeded the maximum depth of {$this->maxDepth()}");
    }

    // If the maximum depth of the tree is not exceed, we can save the new tree.
    $this->definitions[$definition['id']] = $definition;
    $this->getMenuTreeEntity()->setDefinitions($this->definitions)->save();
    $this->rebuildTree();

    return $affected_menus;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    if (!$this->applies()) {
      return $this->inner->delete($id);
    }

    if (isset($this->definitions[$id])) {
      // Re-parent all possible children.
      $definition = $this->definitions[$id];
      if ($children = $this->loadByProperties(['parent' => $id])) {
        foreach (array_keys($children) as $child_id) {
          $this->definitions[$child_id]['parent'] = $definition['parent'];
        }
      }

      // Remove the definition and rebuild the tree.
      unset($this->definitions[$id]);
      $this->getMenuTreeEntity()->setDefinitions($this->definitions)->save();
      $this->rebuildTree();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadTreeData($menu_name, MenuTreeParameters $parameters) {
    if (!$this->applies()) {
      return $this->inner->loadTreeData($menu_name, $parameters);
    }

    $links = $this->loadLinks($menu_name, $parameters);
    $data['tree'] = $this->treeDataRecursive(array_keys($links), $parameters->activeTrail);
    $data['route_names'] = array_unique(array_filter(array_column($links, 'route_name')));

    return $data;
  }

  /**
   * Builds the data representing a menu tree.
   *
   * @param array $link_ids
   *   A flat array of menu links that are part of the menu. Each array element
   *   is an associative array of information about the menu link, containing
   *   the fields from the $this->table. This array must be ordered
   *   depth-first. MenuTreeStorage::loadTreeData() includes a sample query.
   * @param array $active_trail
   *   An array of the menu link ID values that are in the path from the current
   *   page to the root of the menu tree.
   * @param array $child_ids
   *   A flat array of child menu links.
   * @param array $visited
   *   An array containing link IDs that were already placed in the tree.
   *
   * @return array
   *   The fully built tree.
   */
  protected function treeDataRecursive(array $link_ids, array $active_trail, array $child_ids = [], array &$visited = []): array {
    $tree = [];

    $item_ids = $child_ids ?: $link_ids;
    foreach ($item_ids as $id) {
      if (isset($visited[$id])) {
        continue;
      }

      $tree[$id] = [
        'definition' => array_intersect_key($this->definitions[$id], array_flip($this->definitionFields)),
        'has_children' => $this->definitions[$id]['has_children'],
        // We need to determine if we're on the path to root, so we can later
        // build the correct active trail.
        'in_active_trail' => in_array($id, $active_trail, TRUE),
        'subtree' => [],
        'depth' => $this->definitions[$id]['depth'],
      ];

      if ($this->definitions[$id]['has_children']) {
        $child_ids = array_keys(array_filter($this->definitions, function ($child) use ($id) {
          return $child['parent'] == $id;
        }));
        $tree[$id]['subtree'] = $this->treeDataRecursive($link_ids, $active_trail, $child_ids, $visited);
      }

      $visited[$id] = TRUE;
    }

    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllChildren($id, $max_relative_depth = NULL) {
    if (!$this->applies()) {
      return $this->inner->loadAllChildren($id, $max_relative_depth);
    }

    $parameters = new MenuTreeParameters();
    $parameters->setRoot($id)->excludeRoot()->setMaxDepth($max_relative_depth)->onlyEnabledLinks();
    return $this->loadLinks(NULL, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllChildIds($id) {
    if (!$this->applies()) {
      return $this->inner->getAllChildIds($id);
    }

    return $this->definitions[$id]['descendants'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadSubtreeData($id, $max_relative_depth = NULL) {
    if (!$this->applies()) {
      return $this->inner->loadSubtreeData($id, $max_relative_depth);
    }

    if (!isset($this->definitions[$id])) {
      return [];
    }

    $parameters = new MenuTreeParameters();
    $parameters->setRoot($id)->onlyEnabledLinks();
    return $this->loadTreeData($this->definitions[$id]['menu_name'], $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootPathIds($id) {
    if (!$this->applies()) {
      return $this->inner->getRootPathIds($id);
    }

    if (isset($this->definitions[$id])) {
      return array_merge([$id], $this->definitions[$id]['ancestors']);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExpanded($menu_name, array $parents) {
    if (!$this->applies()) {
      return $this->inner->getExpanded($menu_name, $parents);
    }

    $parameters = new MenuTreeParameters();
    $parameters->addCondition('expanded', 1);
    $parameters->addCondition('has_children', 1);
    $parameters->addCondition('enabled', 1);
    $parameters->addCondition('parent', $parents, 'IN');
    $parameters->addCondition('id', $parents, 'NOT IN');

    return array_keys($this->loadLinks($menu_name, $parameters));
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtreeHeight($id) {
    if (!$this->applies()) {
      return $this->inner->getSubtreeHeight($id);
    }

    return isset($this->definitions[$id]) ? $this->doFindChildrenRelativeDepth($this->definitions[$id]) + 1 : 0;
  }

  /**
   * Finds the relative depth of this link's deepest child.
   *
   * @param array $definition
   *   The parent definition used to find the depth.
   *
   * @return int
   *   Returns the relative depth.
   */
  protected function doFindChildrenRelativeDepth(array $definition) {
    // Find the maximum depth of the link's descendants.
    if (isset($this->definitions[$definition['id']]) && !empty($this->definitions[$definition['id']]['descendants'])) {
      $descendants = $this->loadMultiple($this->definitions[$definition['id']]['descendants']);
      $max_depth = max(array_column($descendants, 'depth'));
    }

    return isset($max_depth) ? $max_depth - $definition['depth'] : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function menuNameInUse($menu_name) {
    if (!$this->applies()) {
      return $this->inner->menuNameInUse($menu_name);
    }

    return in_array($menu_name, $this->getMenuNames(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuNames() {
    if (!$this->applies()) {
      return $this->inner->getMenuNames();
    }

    return array_unique(array_column($this->definitions, 'menu_name'));
  }

  /**
   * {@inheritdoc}
   */
  public function countMenuLinks($menu_name = NULL) {
    if (!$this->applies()) {
      return $this->inner->countMenuLinks($menu_name);
    }

    if ($menu_name) {
      return count($this->loadByProperties(['menu_name' => $menu_name]));
    }

    return count($this->definitions);
  }

  /**
   * Determines whether this storage should be used.
   *
   * @return bool
   *   TRUE if there is an active workspace, FALSE otherwise.
   */
  protected function applies(): bool {
    if ($this->workspaceManager->hasActiveWorkspace()) {
      // Lazy load the entire definitions array in memory.
      if (!$this->definitions) {
        if ($definitions = $this->getMenuTreeEntity()->getDefinitions()) {
          $this->definitions = $definitions;
        }
        else {
          // If there's no overridden data for this workspace, we simply load
          // all the definitions provided by the decorated service.
          $this->definitions = $this->inner->loadByProperties([]);
        }
        $this->rebuildTree();
      }

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the wse_menu_tree entity which stores per-workspace menu trees.
   *
   * @return \Drupal\wse_menu\WseMenuTreeInterface
   *   The menu tree entity that stores the menu link definitions.
   */
  protected function getMenuTreeEntity() {
    if (!$this->menuTreeEntity) {
      $storage = $this->entityTypeManager->getStorage('wse_menu_tree');
      $this->menuTreeEntity = $storage->load(1);

      if (!$this->menuTreeEntity) {
        // Create the wse_menu_tree entity if it doesn't exist.
        $this->menuTreeEntity = $this->workspaceManager->executeOutsideWorkspace(function () use ($storage) {
          $entity = $storage->create(['id' => 1]);
          $entity->save();

          return $entity;
        });
      }
    }

    return $this->menuTreeEntity;
  }

  /**
   * Adds useful extra properties for all link definitions, and sorts the tree.
   */
  protected function rebuildTree() {
    $graph = [];
    foreach ($this->definitions as $definition_id => $definition) {
      $graph[$definition_id]['edges'] = [];
      if ($definition['parent']) {
        $graph[$definition_id]['edges'][$definition['parent']] = TRUE;

        // 'has_children' is really just a rendering hint, it conveys whether a
        // link has any 'visible' children.
        if ($definition['enabled']) {
          $has_visible_children[$definition['parent']] = TRUE;
        }
      }
    }
    $graph = (new Graph($graph))->searchAndSort();

    foreach ($this->definitions as $definition_id => &$definition) {
      // The menu system expects roots to have a depth of 1.
      $definition['depth'] = count($graph[$definition_id]['paths']) + 1;
      $definition['ancestors'] = array_keys($graph[$definition_id]['paths']);
      $definition['descendants'] = isset($graph[$definition_id]['reverse_paths']) ? array_keys($graph[$definition_id]['reverse_paths']) : [];
      $definition['has_children'] = $has_visible_children[$definition_id] ?? FALSE;
    }

    // Sort the definitions by menu_name, depth and weight, so they're
    // consistently ordered at all times.
    $menu_name = array_column($this->definitions, 'menu_name');
    $depth = array_column($this->definitions, 'depth');
    $weight = array_column($this->definitions, 'weight');
    array_multisort($menu_name, SORT_ASC, $depth, SORT_ASC, $weight, SORT_ASC, $this->definitions);
  }

  /**
   * Loads links in the given menu, according to the given tree parameters.
   *
   * @param string $menu_name
   *   A menu name.
   * @param \Drupal\Core\Menu\MenuTreeParameters $parameters
   *   The parameters to determine which menu links to be loaded into a tree.
   *   This method will set the absolute minimum depth, which is used in
   *   MenuTreeStorage::doBuildTreeData().
   *
   * @return array
   *   A flat array of menu links that are part of the menu. Each array element
   *   is an associative array of information about the menu link, containing
   *   the fields from the {menu_tree} table. This array must be ordered
   *   depth-first.
   */
  protected function loadLinks($menu_name, MenuTreeParameters $parameters) {
    $conditions = [];

    // Allow a custom root to be specified for loading a menu link tree. If
    // omitted, the default root (i.e. the actual root, '') is used.
    if ($parameters->root !== '') {
      // If the custom root does not exist, we cannot load the links below it.
      if (!isset($this->definitions[$parameters->root])) {
        return [];
      }

      $root = $this->definitions[$parameters->root];

      // When specifying a custom root, we only want to find links whose
      // parent IDs match that of the root; that's how we ignore the rest of the
      // tree. In other words: we exclude everything unreachable from the
      // custom root.
      $conditions[] = ['id', array_merge([$parameters->root], $root['descendants']), 'IN'];

      // When specifying a custom root, the menu is determined by that root.
      $menu_name = $root['menu_name'];

      // If the custom root exists, then we must rewrite some of our
      // parameters; parameters are relative to the root (default or custom),
      // but the queries require absolute numbers, so adjust correspondingly.
      if (isset($parameters->minDepth)) {
        $parameters->minDepth += $root['depth'];
      }
      else {
        $parameters->minDepth = $root['depth'];
      }
      if (isset($parameters->maxDepth)) {
        $parameters->maxDepth += $root['depth'];
      }
    }

    // If no minimum depth is specified, then set the actual minimum depth,
    // depending on the root.
    if (!isset($parameters->minDepth)) {
      if ($parameters->root !== '' && $root) {
        $parameters->minDepth = $root['depth'];
      }
      else {
        $parameters->minDepth = 1;
      }
    }

    if ($menu_name) {
      $conditions[] = ['menu_name', $menu_name];
    }

    if (!empty($parameters->expandedParents)) {
      $conditions[] = ['parent', $parameters->expandedParents, 'IN'];
    }
    if (isset($parameters->minDepth) && $parameters->minDepth > 1) {
      $conditions[] = ['depth', $parameters->minDepth, '>='];
    }
    if (isset($parameters->maxDepth)) {
      $conditions[] = ['depth', $parameters->maxDepth, '<='];
    }
    // Add custom query conditions, if any were passed.
    if (!empty($parameters->conditions)) {
      // Only allow conditions that are testing definition fields.
      $parameters->conditions = array_intersect_key($parameters->conditions, array_flip($this->definitionFields));
      foreach ($parameters->conditions as $column => $value) {
        if ($column === 'route_parameters') {
          // Sort the route parameters so the query string will be the same.
          asort($value);
          $operator = '=';
        }
        elseif (is_array($value)) {
          $operator = $value[1];
          $value = $value[0];
        }
        else {
          $operator = '=';
        }
        $conditions[] = [$column, $value, $operator];
      }
    }

    $filtered = array_filter($this->definitions, function (array $definition) use ($conditions) {
      foreach ($conditions as $condition) {
        $column = $condition[0];
        $value = $condition[1];
        $operator = $condition[2] ?? (is_array($value) ? 'IN' : '=');

        // Process the value for the operator that uses it.
        if (!in_array($operator, ['IS NULL', 'IS NOT NULL'], TRUE)) {
          // Lowercase condition value(s) for case-insensitive matches.
          if (is_array($value)) {
            $value = array_map('mb_strtolower', $value);
          }
          elseif (!is_bool($value)) {
            $value = mb_strtolower($value);
          }
        }

        // If any condition fails, filter out this link definition.
        if (!$this->match(['value' => $value, 'operator' => $operator], $definition[$column])) {
          return FALSE;
        }
      }

      // If all conditions matched, include this definition.
      return TRUE;
    });

    return $filtered;
  }

  /**
   * Matches a condition against a value.
   *
   * Lifted from \Drupal\Core\Config\Entity\Query\Condition::match().
   *
   * @param array $condition
   *   An array containing the condition operator and the value.
   * @param string|null $value
   *   The value to match against.
   *
   * @return bool
   *   TRUE when it matches, FALSE otherwise.
   */
  protected function match(array $condition, ?string $value = NULL): bool {
    // "IS NULL" and "IS NOT NULL" conditions can also deal with array values,
    // so we return early for them to avoid problems.
    if (in_array($condition['operator'], ['IS NULL', 'IS NOT NULL'], TRUE)) {
      $should_be_set = $condition['operator'] === 'IS NOT NULL';
      return $should_be_set === isset($value);
    }

    if (isset($value)) {
      // We always want a case-insensitive match.
      if (!is_bool($value) && !is_array($value)) {
        $value = mb_strtolower($value);
      }

      switch ($condition['operator']) {
        case '=':
          return $value == $condition['value'];

        case '>':
          return $value > $condition['value'];

        case '<':
          return $value < $condition['value'];

        case '>=':
          return $value >= $condition['value'];

        case '<=':
          return $value <= $condition['value'];

        case '<>':
          return $value != $condition['value'];

        case 'IN':
          return array_search($value, $condition['value']) !== FALSE;

        case 'NOT IN':
          return array_search($value, $condition['value']) === FALSE;

        case 'STARTS_WITH':
          return strpos($value, $condition['value']) === 0;

        case 'CONTAINS':
          return strpos($value, $condition['value']) !== FALSE;

        case 'ENDS_WITH':
          return substr($value, -strlen($condition['value'])) === (string) $condition['value'];

        default:
          throw new \InvalidArgumentException('Invalid condition operator.');
      }
    }
    return FALSE;
  }

}
