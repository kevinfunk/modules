<?php

namespace Drupal\wse_deploy;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a plugin manager for workspace export plugins.
 *
 * @see \Drupal\wse_deploy\Annotation\WorkspaceExport
 * @see \Drupal\wse_deploy\WorkspaceExportBase
 * @see \Drupal\wse_deploy\WorkspaceExportPluginManager
 * @see plugin_api
 */
class WorkspaceExportPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new WorkspaceExportPluginManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/wse_deploy/WorkspaceExport', $namespaces, $module_handler, 'Drupal\wse_deploy\WorkspaceExportInterface', 'Drupal\wse_deploy\Annotation\WorkspaceExport');

    $this->alterInfo('workspace_export_info');
    $this->setCacheBackend($cache_backend, 'workspace_export');
  }

  /**
   * Gets the available workspace export plugins.
   *
   * @return array
   *   The workspace export plugin options.
   */
  public function getPluginOptions() {
    $plugin_options = [];

    // Sort the plugins based on their weight.
    $definitions = $this->getDefinitions();
    uasort($definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
      if ($plugin_class::isAvailable()) {
        $plugin_options[$plugin_id] = $plugin_definition['label'];
      }
    }

    return $plugin_options;
  }

}
