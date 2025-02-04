<?php

namespace Drupal\wse_menu;

use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\State\StateInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Defines an implementation of the menu link override using state.
 */
class WseStaticMenuLinkOverrides implements StaticMenuLinkOverridesInterface {

  /**
   * Constructs a new WseStaticMenuLinkOverrides.
   *
   * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $inner
   *   The inner static menu link overrides service.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   *   The workspace manager.
   * @param \Drupal\workflows\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected readonly StaticMenuLinkOverridesInterface $inner,
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function reload() {
    $this->inner->reload();
    $this->state->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverride($id) {
    if (!$this->applies()) {
      return $this->inner->loadOverride($id);
    }

    assert(is_string($id), 'Menu link plugin ID should be a string.');
    $all_overrides = $this->state->get($this->getStateKey());
    $id = static::encodeId($id);
    return $all_overrides[$id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultipleOverrides(array $ids) {
    if (!$this->applies()) {
      return $this->inner->deleteMultipleOverrides($ids);
    }

    $all_overrides = $this->state->get($this->getStateKey());
    $save = FALSE;
    foreach ($ids as $id) {
      $id = static::encodeId($id);
      if (isset($all_overrides[$id])) {
        unset($all_overrides[$id]);
        $save = TRUE;
      }
    }
    if ($save) {
      if (empty($all_overrides)) {
        $this->state->delete($this->getStateKey());
      }
      else {
        $this->state->set($this->getStateKey(), $all_overrides);
      }
    }
    return $save;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteOverride($id) {
    return $this->deleteMultipleOverrides([$id]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleOverrides(array $ids) {
    if (!$this->applies()) {
      return $this->inner->loadMultipleOverrides($ids);
    }

    $result = [];
    if ($ids) {
      // When rebuilding the menu tree, ensure that we also return the Live
      // config overrides, otherwise they wouldn't be applied anymore.
      $config_overrides = [];
      $original_config_overrides = $this->inner->loadMultipleOverrides($ids);
      foreach ($original_config_overrides as $key => $value) {
        $config_overrides[static::encodeId($key)] = $value;
      }

      $all_overrides = $this->state->get($this->getStateKey(), []) + $config_overrides;
      foreach ($ids as $id) {
        $encoded_id = static::encodeId($id);
        if (isset($all_overrides[$encoded_id])) {
          $result[$id] = $all_overrides[$encoded_id];
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function saveOverride($id, array $definition) {
    if (!$this->applies()) {
      return $this->inner->saveOverride($id, $definition);
    }

    // Only allow to override a specific subset of the keys.
    $expected = [
      'menu_name' => '',
      'parent' => '',
      'weight' => 0,
      'expanded' => FALSE,
      'enabled' => FALSE,
    ];
    // Filter the overrides to only those that are expected.
    $definition = array_intersect_key($definition, $expected);
    // Ensure all values are set.
    $definition = $definition + $expected;
    if ($definition) {
      // Cast keys to avoid config schema during save.
      $definition['menu_name'] = (string) $definition['menu_name'];
      $definition['parent'] = (string) $definition['parent'];
      $definition['weight'] = (int) $definition['weight'];
      $definition['expanded'] = (bool) $definition['expanded'];
      $definition['enabled'] = (bool) $definition['enabled'];

      $id = static::encodeId($id);
      $all_overrides = $this->state->get($this->getStateKey());
      // Combine with any existing data.
      $all_overrides[$id] = $definition + $this->loadOverride($id);
      $this->state->set($this->getStateKey(), $all_overrides);
    }
    return array_keys($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!$this->applies()) {
      return $this->inner->getCacheTags();
    }

    return $this->workspaceManager->getActiveWorkspace()->getCacheTags();
  }

  /**
   * Encodes the ID by replacing dots with double underscores.
   *
   * This is done because config schema uses dots for its internal type
   * hierarchy. Double underscores are converted to triple underscores to
   * avoid accidental conflicts.
   *
   * @param string $id
   *   The menu plugin ID.
   *
   * @return string
   *   The menu plugin ID with double underscore instead of dots.
   */
  protected static function encodeId($id): string {
    return strtr($id, ['.' => '__', '__' => '___']);
  }

  /**
   * Determines whether this static menu link overrides storage should be used.
   *
   * @return bool
   *   TRUE if there is an active workspace, FALSE otherwise.
   */
  protected function applies(): bool {
    return $this->workspaceManager->hasActiveWorkspace();
  }

  /**
   * Gets the ID of the state entry for a workspace.
   *
   * @return string
   *   The ID.
   */
  protected function getStateKey(): string {
    return 'wse_menu.static_menu_link_overrides.' . $this->workspaceManager->getActiveWorkspace()->id();
  }

}
