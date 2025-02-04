<?php

namespace Drupal\wse_config\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\ChainedFastBackend;
use Drupal\wse_config\WorkspaceIdDetector;

/**
 * Defines a chained fast backend that omits fast caching inside workspaces.
 */
class WseChainedFastBackend extends ChainedFastBackend {

  /**
   * The workspace ID detector.
   *
   * @var \Drupal\wse_config\WorkspaceIdDetector
   */
  protected $workspaceIdDetector;

  /**
   * Constructs a ChainedFastBackend object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $consistent_backend
   *   The consistent cache backend.
   * @param \Drupal\Core\Cache\CacheBackendInterface $fast_backend
   *   The fast cache backend.
   * @param string $bin
   *   The cache bin for which the object is created.
   * @param \Drupal\wse_config\WorkspaceIdDetector $workspace_id_detector
   *   The workspace ID detector.
   */
  public function __construct(CacheBackendInterface $consistent_backend, CacheBackendInterface $fast_backend, $bin, WorkspaceIdDetector $workspace_id_detector) {
    parent::__construct($consistent_backend, $fast_backend, $bin);
    $this->workspaceIdDetector = $workspace_id_detector;
  }

  /**
   * Gets the last write timestamp.
   */
  protected function getLastWriteTimestamp() {
    if ($this->workspaceIdDetector->getActiveWorkspaceId()) {
      return 0;
    }

    return parent::getLastWriteTimestamp();
  }

}
