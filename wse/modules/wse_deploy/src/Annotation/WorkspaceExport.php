<?php

namespace Drupal\wse_deploy\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the workspace export plugin annotation object.
 *
 * Plugin namespace: Plugin\wse_deploy\WorkspaceExport.
 *
 * @Annotation
 */
class WorkspaceExport extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
