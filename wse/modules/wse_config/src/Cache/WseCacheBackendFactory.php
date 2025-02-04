<?php

namespace Drupal\wse_config\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\wse_config\WorkspaceIdDetector;

/**
 * Implements the factory for workspace specific cache backends.
 */
class WseCacheBackendFactory implements CacheFactoryInterface {

  /**
   * The decorated cache backend factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $innerFactory;

  /**
   * The workspace ID detector.
   *
   * @var \Drupal\wse_config\WorkspaceIdDetector
   */
  protected $workspaceIdDetector;

  /**
   * Constructs the DatabaseBackendFactory object.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $inner_factory
   *   The decorated cache backend factory.
   * @param \Drupal\wse_config\WorkspaceIdDetector $workspace_id_detector
   *   The workspace id detector.
   *
   * @throws \BadMethodCallException
   */
  public function __construct(CacheFactoryInterface $inner_factory, WorkspaceIdDetector $workspace_id_detector) {
    $this->innerFactory = $inner_factory;
    $this->workspaceIdDetector = $workspace_id_detector;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $allowed_bins = ['config', 'data', 'discovery'];
    if (!in_array($bin, $allowed_bins, TRUE)) {
      return $this->innerFactory->get($bin);
    }

    return new WseCacheBackend(
      $this->innerFactory->get($bin),
      $this->workspaceIdDetector,
    );
  }

}
