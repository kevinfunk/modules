<?php

namespace Drupal\wse_config\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\wse_config\WorkspaceIdDetector;

/**
 * Defines a workspace aware cache backend.
 *
 * @ingroup cache
 */
class WseCacheBackend implements CacheBackendInterface {

  /**
   * The cache backend returned by the decorated cache backend factory.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $innerBackend;

  /**
   * The workspace ID detector.
   *
   * @var \Drupal\wse_config\WorkspaceIdDetector
   */
  protected $workspaceIdDetector;

  /**
   * Constructs a WseDatabaseBackend object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $inner_backend
   *   The cache backend returned by the decorated cache backend factory.
   * @param \Drupal\wse_config\WorkspaceIdDetector $workspace_id_detector
   *   The workspace id detector.
   */
  public function __construct(CacheBackendInterface $inner_backend, WorkspaceIdDetector $workspace_id_detector) {
    $this->innerBackend = $inner_backend;
    $this->workspaceIdDetector = $workspace_id_detector;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $workspace_aware_cid = $this->getWorkspaceAwareCid($cid);
    return $this->innerBackend->get($workspace_aware_cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $workspace_aware_cids = $this->getWorkspaceAwareCids($cids);
    return $this->innerBackend->getMultiple($workspace_aware_cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $workspace_aware_cid = $this->getWorkspaceAwareCid($cid);
    $this->innerBackend->set($workspace_aware_cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    $workspace_id = $this->workspaceIdDetector->getActiveWorkspaceId();
    if ($workspace_id) {
      $workspace_cache_items = [];
      foreach ($items as $cid => $item) {
        $workspace_cache_items[$this->getWorkspaceAwareCid($cid, $workspace_id)] = $item;
      }
      $this->innerBackend->setMultiple($workspace_cache_items);
      return;
    }
    $this->innerBackend->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->innerBackend->delete($this->getWorkspaceAwareCid($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $workspace_aware_cids = $this->getWorkspaceAwareCids($cids);
    $this->innerBackend->deleteMultiple($workspace_aware_cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->innerBackend->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->innerBackend->invalidate($this->getWorkspaceAwareCid($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $this->innerBackend->invalidateMultiple($this->getWorkspaceAwareCids($cids));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->innerBackend->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->innerBackend->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->innerBackend->removeBin();
  }

  /**
   * Prefixes given cache ids with the currently active workspace if applicable.
   *
   * @param array $original_cids
   *   The original cache ids before prefixing.
   *
   * @return array
   *   Workspace ID prefixed cids if a workspace is active or the originals.
   */
  protected function getWorkspaceAwareCids(array $original_cids) {
    $workspace_id = $this->workspaceIdDetector->getActiveWorkspaceId();
    if ($workspace_id) {
      $workspace_aware_cids = [];
      foreach ($original_cids as $cid) {
        $workspace_aware_cids[] = $this->getWorkspaceAwareCid($cid, $workspace_id);
      }
      return $workspace_aware_cids;
    }
    return $original_cids;
  }

  /**
   * Gets the workspace aware cache ID.
   *
   * @param string $cid
   *   The cache ID.
   * @param string $workspace_id
   *   The ID of the workspace.
   *
   * @return string
   *   The CID.
   */
  protected function getWorkspaceAwareCid($cid, $workspace_id = NULL) {
    if (!$workspace_id) {
      $workspace_id = $this->workspaceIdDetector->getActiveWorkspaceId();
    }

    if ($workspace_id && strpos($cid, $workspace_id) !== 0) {
      $workspace_aware_cid = $workspace_id . ':' . $cid;
      // The cid may already be workspace specific at this point.
      return $cid != $workspace_aware_cid
        ? $workspace_id . ':' . $cid
        : $cid;
    }
    return $cid;
  }

}
