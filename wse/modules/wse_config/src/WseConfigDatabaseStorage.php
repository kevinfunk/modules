<?php

namespace Drupal\wse_config;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Error;

/**
 * Defines the Database storage.
 */
class WseConfigDatabaseStorage implements StorageInterface {

  use DependencySerializationTrait;
  use LoggerChannelTrait;

  /**
   * The decorated config.storage service.
   *
   * @var \Drupal\Core\Config\DatabaseStorage
   */
  protected $inner;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace ID detector.
   *
   * @var \Drupal\wse_config\WorkspaceIdDetector
   */
  protected $workspaceIdDetector;

  /**
   * The wse config matcher.
   *
   * @var \Drupal\wse_config\WseConfigMatcher
   */
  protected $configMatcher;

  /**
   * The storage collection.
   *
   * @var string
   */
  protected $collection = StorageInterface::DEFAULT_COLLECTION;

  /**
   * Constructs a new WseConfigDatabaseStorage.
   *
   * @param \Drupal\Core\Config\StorageInterface $inner
   *   The decorated config.storage.active service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\wse_config\WorkspaceIdDetector $workspace_id_detector
   *   The workspace ID detector.
   * @param \Drupal\wse_config\WseConfigMatcher $config_matcher
   *   The wse config matcher.
   * @param string $collection
   *   (optional) The collection to store configuration in. Defaults to the
   *   default collection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(StorageInterface $inner, EntityTypeManagerInterface $entity_type_manager, WorkspaceIdDetector $workspace_id_detector, WseConfigMatcher $config_matcher, $collection = StorageInterface::DEFAULT_COLLECTION) {
    $this->inner = $inner->createCollection($collection);
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceIdDetector = $workspace_id_detector;
    $this->configMatcher = $config_matcher;
    $this->collection = $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    if (!$this->wseConfigStorageIsActive() || $this->configMatcher->configIsIgnored([$name])) {
      return $this->inner->exists($name);
    }

    try {
      $exists = (bool) $this->getWorkspaceAwareQuery()
        ->condition('collection', $this->getCollectionName())
        ->condition('name', $name)
        ->execute();

      // Cover the case where this module is active, but existing config was not
      // yet saved into content entities.
      if (!$exists) {
        $exists = $this->inner->exists($name);
      }
      return $exists;
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    if (!$this->wseConfigStorageIsActive() || $this->configMatcher->configIsIgnored([$name])) {
      return $this->inner->read($name);
    }

    $data = FALSE;
    try {
      $entity = $this->getWseConfigEntityByName($name);
      if ($entity && $entity->get('data')->value) {
        $data = $this->decode($entity->get('data')->value);
      }

      // Fall back to inner storage if no config was saved in a content entity.
      if (!$data) {
        $data = $this->inner->read($name);
      }
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);

      // If something goes wrong with reading config from wse_config entities,
      // fall back to the core storage or things will break.
      $data = $this->inner->read($name);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    if (!$this->wseConfigStorageIsActive()) {
      return $this->inner->readMultiple($names);
    }

    $list = [];
    try {
      $not_ignored = $this->getUnIgnored($names);
      if ($not_ignored) {
        $query = $this->getWorkspaceAwareQuery()
          ->condition('name', $not_ignored, 'IN');

        if ($this->getCollectionName()) {
          $query->condition('collection', $this->collection);
        }

        $result = $query->execute();
        $revisions = $this->getWseConfigStorage()->loadMultiple($result);

        foreach ($revisions as $revision) {
          // Load the translation config override record if we find a collection
          // tagged like langcode.de.
          if (\str_contains($this->collection, 'langcode.')) {
            [, $langcode] = explode('.', $this->collection);
            $revision = $revision->getTranslation($langcode);
          }
          $name = $revision->get('name')->value;
          $list[$name] = $this->decode(
            $revision->get('data')->value
          );
        }
      }

      // At this point, the list contains only config which was already saved
      // inside a wse config entity, but not all the other configs which may
      // have existed before enabling the module. Thus add the latter ones in,
      // and also add ignored ones if there were any.
      $list += $this->inner->readMultiple($names);
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);

      // If something goes wrong with reading config from wse_config entities,
      // fall back to the core storage or things will break.
      $list = $this->inner->readMultiple($names);
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data, $langcode = NULL) {
    if (!$this->wseConfigStorageIsActive() || $this->configMatcher->configIsIgnored([$name])) {
      return $this->inner->write($name, $data);
    }

    $serialized_data = $this->encode($data);
    try {
      $storage = $this->getWseConfigStorage();
      $entity = $this->getWseConfigEntityByName($name);
      if (!$entity) {
        // Create a default revision with the active configuration data, needed
        // in order to have a canonical version to revert to.
        $default_revision_data = $serialized_data;
        if ($existing_data = $this->inner->read($name)) {
          $default_revision_data = $this->encode($existing_data);
        }
        $entity = \Drupal::service('workspaces.manager')->executeOutsideWorkspace(function () use ($storage, $name, $default_revision_data) {
          $entity = $storage->create([
            'name' => $name,
            'collection' => $this->getCollectionName(),
            'data' => $default_revision_data,
          ]);
          $entity->save();

          return $entity;
        });
      }

      if ($langcode) {
        if ($entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($langcode);
        }
        else {
          $entity = $entity->addTranslation($langcode);
        }
      }

      $entity->set('name', $name);
      $entity->set('collection', $this->getCollectionName());
      $entity->set('data', $serialized_data);
      $entity->save();

      return TRUE;
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);
      // Some other failure that we can not recover from.
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    if (!$this->wseConfigStorageIsActive() || $this->configMatcher->configIsIgnored([$name])) {
      return $this->inner->delete($name);
    }

    try {
      $entity = $this->getWseConfigEntityByName($name);
      $entity->delete();
      return TRUE;
    }
    catch (\Exception $exception) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    if (!$this->wseConfigStorageIsActive() || $this->configMatcher->configIsIgnored([$name])) {
      return $this->inner->rename($name, $new_name);
    }

    try {
      $entity = $this->getWseConfigEntityByName($name);
      $entity->set('name', $new_name)->save();
      return TRUE;
    }
    catch (\Exception $exception) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->inner->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->inner->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    if (!$this->wseConfigStorageIsActive()) {
      return $this->inner->listAll($prefix);
    }

    try {
      $configs = $this->listAllWseConfigs($prefix);

      // Add configs which were not saved into wse_config entities yet or that
      // are ignored from the inner storage.
      $configs = array_merge($this->inner->listAll($prefix), $configs);
      return $configs;
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    if (!$this->wseConfigStorageIsActive()) {
      return $this->inner->deleteAll($prefix);
    }

    try {
      $not_ignored = $this->getUnIgnored([$prefix]);
      if ($not_ignored) {
        $query = $this->getWorkspaceAwareQuery()
          ->condition('collection', $this->getCollectionName())
          ->condition('name', $not_ignored, 'IN');
        $result = $query->execute();

        if ($result) {
          $entities = $this->getWseConfigStorage()->loadMultiple($result);
          foreach ($entities as $entity) {
            $entity->delete();
          }
        }
      }
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->inner,
      $this->entityTypeManager,
      $this->workspaceIdDetector,
      $this->configMatcher,
      $collection
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    if (!$this->wseConfigStorageIsActive()) {
      return $this->inner->getAllCollectionNames();
    }

    try {
      $result = $this->getWorkspaceAwareQuery()
        ->condition('collection', $this->getCollectionName(), '<>')
        ->sort('collection')
        ->execute();

      $collections = [];
      if ($result) {
        $entities = $this->getWseConfigStorage()->loadMultiple($result);
        foreach ($entities as $entity) {
          $collection = $entity->get('collection')->value;
          if (!in_array($collection, $collections)) {
            $collections[] = $collection;
          }
        }
      }

      $collections = array_merge($this->inner->getAllCollectionNames(), $collections);
      return $collections;
    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('wse_config'), $e);
      // @todo does this need to return the collection names of core storage
      // instead?
      return [];
    }
  }

  /**
   * Loads a wse config entity by name.
   *
   * @param string $name
   *   The name of the entity to be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   A loaded entity or false if none was found for given name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getWseConfigEntityByName($name) {
    $entities = $this->getWseConfigEntitiesByNames([$name]);
    return reset($entities);
  }

  /**
   * Loads wse config entity by names.
   *
   * @param array $names
   *   The names of the entities to be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Loaded entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getWseConfigEntitiesByNames(array $names) {
    $result = $this->getWorkspaceAwareQuery()
      ->condition('name', $names, 'IN')
      ->execute();
    return $this->getWseConfigStorage()->loadMultiple($result);
  }

  /**
   * Checks if using wse_config storage is applicable.
   *
   * If the module hasn't completed the install process we can't use this
   * storage yet, thus the first condition. We neither need to use it if there
   * is no workspace active, see second condition.
   *
   * @return bool
   *   TRUE if wse_config storage should be used.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function wseConfigStorageIsActive() {
    return $this->workspaceIdDetector->getActiveWorkspaceId();
  }

  /**
   * Shortcut for the entity storage of wse_config entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The wse_config entity storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getWseConfigStorage() {
    return $this->entityTypeManager->getStorage('wse_config');
  }

  /**
   * Extracts configs which are not ignored from a given list of config names.
   *
   * @param array $names
   *   The list of config names to extract the not ignored configs from.
   *
   * @return array
   *   List of config names which are not ignored.
   *
   * @see \Drupal\wse_config\Event\WseConfigEvents
   * @see \Drupal\wse_config\Event\WseConfigOptOutEvent
   * @see \Drupal\wse_config\WseConfigMatcher
   */
  public function getUnIgnored(array $names) {
    $ignored = $this->configMatcher->getIgnoredConfigs($names);
    return array_diff($names, $ignored);
  }

  /**
   * Writes all config stored in wse_config entities into the inner storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function publishWseConfig() {
    $configs = $this->listAllWseConfigs();
    foreach ($configs as $config_name) {
      $entity = $this->getWseConfigEntityByName($config_name);
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $translation = $entity->getTranslation($langcode);
        if ($translation && $translation->get('data')->value) {
          // Pass the collection to the inner storage.
          if ($collection = $translation->collection->value) {
            $original_collection = $this->inner->getCollectionName();
            $this->inner = $this->inner->createCollection($collection);
          }
          $data = $this->decode($translation->get('data')->value);
          $this->inner->write($config_name, $data);
          if ($collection && isset($original_collection)) {
            // Put the collection back how we found it as it will persist for
            // any other entities being operated on.
            $this->inner = $this->inner->createCollection($original_collection);
          }
        }
      }
    }
  }

  /**
   * Writes config data stored in wse_config entities into the inner storage.
   */
  public function revertWseConfig(array $revision_ids) {
    $wse_config_revisions = $this->getWseConfigStorage()->loadMultipleRevisions($revision_ids);
    foreach ($wse_config_revisions as $revision) {
      $name = $revision->get('name')->value;
      $data = $this->decode($revision->get('data')->value);
      $this->inner->write($name, $data);
    }
  }

  /**
   * Retrieves a list of configs that were changed in the current workspace.
   *
   * @param string $prefix
   *   The prefix to filter by. If omitted, all configuration object names that
   *   exist are returned.
   *
   * @return array
   *   List of config names.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function listAllWseConfigs($prefix = '') {
    $configs = [];
    $not_ignored = $this->getUnIgnored([$prefix]);

    if (count($not_ignored)) {
      $query = $this->getWorkspaceAwareQuery()
        ->sort('collection')
        ->sort('name');

      $not_ignored = array_filter($not_ignored);
      if ($not_ignored) {
        $or_group = $query->orConditionGroup();
        foreach ($not_ignored as $prefix) {
          $or_group->condition('name', $prefix, 'STARTS_WITH');
        }
        $query->condition($or_group);
      }

      if ($this->getCollectionName()) {
        $query->condition('collection', $this->getCollectionName());
      }

      $result = $query->execute();
      if ($result) {
        $entities = $this->getWseConfigStorage()->loadMultiple($result);
        foreach ($entities as $entity) {
          $configs[] = $entity->get('name')->value;
        }
      }
    }
    return $configs;
  }

  /**
   * Provides the wse_config entity query with a condition on workspace.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getWorkspaceAwareQuery() {
    return $this->getWseConfigStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('workspace', $this->workspaceIdDetector->getActiveWorkspaceId());
  }

}
