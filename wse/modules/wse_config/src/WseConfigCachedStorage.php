<?php

namespace Drupal\wse_config;

use Drupal\Core\Config\StorageCacheInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines the WseConfigCached config storage.
 */
class WseConfigCachedStorage implements StorageInterface, StorageCacheInterface {
  use DependencySerializationTrait;

  /**
   * The configuration storage to be cached.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new WseConfigCachedStorage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   A configuration storage to be cached.
   */
  public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return $this->storage->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    return $this->storage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    return $this->storage->readMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data, $langcode = NULL) {
    return $this->storage->write($name, $data, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    return $this->storage->delete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    return $this->storage->rename($name, $new_name);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    return $this->storage->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    return $this->storage->deleteAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->storage->createCollection($collection)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

  /**
   * {@inheritdoc}
   */
  public function resetListCache() {
    // There's no cache. The StorageCacheInterface is implemented so Drush
    // config commands still exist.
    // @todo Remove once https://github.com/drush-ops/drush/issues/6196 is
    //   fixed.
  }

}
