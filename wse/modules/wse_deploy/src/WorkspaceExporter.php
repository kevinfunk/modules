<?php

namespace Drupal\wse_deploy;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Serializer\Serializer;
use Drupal\link\LinkItemInterface;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;
use Drupal\user\EntityOwnerInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse_deploy\Event\WorkspaceDeployEvents;
use Drupal\wse_deploy\Event\WorkspaceExportEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service for exporting workspace contents.
 */
class WorkspaceExporter {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * The JSON:API serializer.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $jsonApiSerializer;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The encryption handler.
   *
   * @var \Drupal\wse_deploy\EncryptionHandler
   */
  protected $encryptionHandler;

  /**
   * A graph array as needed by \Drupal\Component\Graph\Graph.
   *
   * @var array
   */
  private $graph = [];

  /**
   * Constructs a new WorkspaceExporter instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   * @param \Drupal\jsonapi\Serializer\Serializer $serializer
   *   The JSON:API serializer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\wse_deploy\EncryptionHandler $encryption_handler
   *   The encryption handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceAssociationInterface $workspace_association, Serializer $serializer, ResourceTypeRepositoryInterface $resource_type_repository, ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, EventDispatcherInterface $event_dispatcher, EncryptionHandler $encryption_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceAssociation = $workspace_association;
    $this->jsonApiSerializer = $serializer;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->eventDispatcher = $event_dispatcher;
    $this->encryptionHandler = $encryption_handler;
  }

  /**
   * Exports the contents of a workspace to JSON files.
   */
  public function exportToJson(WorkspaceInterface $workspace) {
    $tracked_entities = $this->workspaceAssociation->getTrackedEntities($workspace->id());

    if (!$tracked_entities || empty($workspace->id())) {
      return;
    }

    $workspace_export_dir = 'private://workspaces/export/' . $workspace->id();

    // Create an archive for all the data files.
    // @todo Archiving should be the export plugin's responsibility?
    $archiver = new ArchiveTar($this->fileSystem->getTempDirectory() . '/export.tar.gz', 'gz');

    // Cleanup filesystem.
    if (is_dir($workspace_export_dir)) {
      $this->fileSystem->deleteRecursive($workspace_export_dir);
    }
    $this->fileSystem->mkdir($workspace_export_dir, NULL, TRUE);

    // Export workspace.json to the export set.
    [$workspace_data] = $this->exportEntityToJSON($workspace);
    $this->addFile($workspace_data, $workspace_export_dir . '/workspace.json', $archiver);

    $index_data = $files_data = $users_data = [];

    foreach ($tracked_entities as $entity_type_id => $tracked_entity_ids) {
      $tracked_revisions = $this->entityTypeManager->getStorage($entity_type_id)->loadMultipleRevisions(array_keys($tracked_entity_ids));
      foreach ($tracked_revisions as $revision) {
        $default_langcode = $revision->getUntranslated()->language()->getId();
        $langcodes = $revision instanceof TranslatableInterface ?
          array_keys([$default_langcode => NULL] + $revision->getTranslationLanguages(FALSE)) :
          [$default_langcode];

        if (count($langcodes) > 1) {
          [$data, $file_name, , $referenced_files, $referenced_users] = $this->exportMultilingualEntityToJson($revision, $langcodes);
        }
        else {
          [$data, $file_name, , $referenced_files, $referenced_users] = $this->exportEntityToJSON($revision);
        }

        $this->fileSystem->saveData($data, $workspace_export_dir . '/' . $file_name, FileExists::Replace);
        $archiver->addString($file_name, $data);
        $index_data[$revision->uuid()] = [
          'entity_type_id' => $entity_type_id,
          'entity_id' => $revision->id(),
          'entity_uuid' => $revision->uuid(),
          'entity_languages' => $langcodes,
          'filename' => $file_name,
          'hash' => $this->encryptionHandler->getHash($data),
        ];
        $users_data += $referenced_users;
        $files_data += $referenced_files;
      }
    }

    // Sort the index data so that dependencies are always imported first.
    $graph = (new Graph($this->graph))->searchAndSort();
    uasort($graph, [SortArray::class, 'sortByWeightElement']);
    $sorted = array_keys(array_reverse($graph));

    uksort($index_data, function ($key1, $key2) use ($sorted) {
      return ((array_search($key1, $sorted) > array_search($key2, $sorted)) ? 1 : -1);
    });

    $index_json = json_encode(array_values($index_data), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    $this->addFile($index_json, $workspace_export_dir . '/index.json', $archiver);

    // Export users.json to the export set.
    $normalization = $this->getEntityCollectionNormalization('user', 'user', $users_data);
    $users_json = json_encode($normalization, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    $this->addFile($users_json, $workspace_export_dir . '/users.json', $archiver);

    $normalization = $this->getEntityCollectionNormalization('file', 'file', $files_data);
    $files_json = json_encode($normalization, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    $this->addFile($files_json, $workspace_export_dir . '/files.json', $archiver);

    // Move the archive from its temporary to the final location.
    $this->fileSystem->move($this->fileSystem->getTempDirectory() . '/export.tar.gz', $workspace_export_dir . '/export.tar.gz');

    $this->eventDispatcher->dispatch(new WorkspaceExportEvent($workspace, $index_data, $files_data), WorkspaceDeployEvents::WORKSPACE_POST_EXPORT);
  }

  /**
   * Archives the specified file with a hash to allow for integrity checks.
   *
   * @param string $data
   *   The file data.
   * @param string $destination
   *   The file path.
   * @param \Drupal\Core\Archiver\ArchiveTar $archiver
   *   The archiver to be used.
   */
  protected function addFile(string $data, string $destination, ArchiveTar $archiver): void {
    $this->fileSystem->saveData($data, $destination, FileExists::Replace);
    $archiver->addString(basename($destination), $data);

    $hash = $this->encryptionHandler->getHash($data);
    $destination = $destination . '.hash';
    $this->fileSystem->saveData($hash, $destination, FileExists::Replace);
    $archiver->addString(basename($destination), $hash);
  }

  /**
   * Exports the contents of a multilingual entity to JSON.
   *
   * @return array
   *   The data of an entity encoded to JSON.
   */
  public function exportMultilingualEntityToJson(EntityInterface $entity, array $langcodes): array {
    $export = [];

    foreach ($langcodes as $langcode) {
      $translation = $entity->getTranslation($langcode);
      [, $file_name, $normalization, $referenced_files, $referenced_users] = $this->exportEntityToJSON($translation);
      $export[$langcode] = $normalization;
    }
    $data = json_encode($export, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);

    return [$data, $file_name ?? '', NULL, $referenced_files ?? [], $referenced_users ?? []];
  }

  /**
   * Exports the contents of an entity to JSON.
   *
   * @return array
   *   The data of an entity encoded to JSON.
   */
  public function exportEntityToJson(EntityInterface $entity): array {
    // Gather a list of users referenced by this entity so they can be synced to
    // the destination site.
    $referenced_users = [];
    if ($entity instanceof EntityOwnerInterface) {
      $owner = $entity->getOwner();
      $referenced_users[$owner->uuid()] = $owner;
    }

    if ($entity->getEntityType()->hasRevisionMetadataKey('revision_user')) {
      $revision_user_field = $entity->getEntityType()->getRevisionMetadataKey('revision_user');
      $revision_user_item = $entity->get($revision_user_field);
      if (!$revision_user_item->isEmpty()) {
        $author = $revision_user_item->first()->entity;
        $referenced_users[$author->uuid()] = $author;
      }
    }

    // Gather a list of files referenced by this entity.
    $referenced_files = [];
    $this->graph[$entity->uuid()]['edges'] = [];
    foreach ($entity->getFields() as $field_items) {
      if ($field_items instanceof EntityReferenceFieldItemListInterface) {
        $referenced_entities = $field_items->referencedEntities();

        if ($field_items->getFieldDefinition()->getSetting('target_type') === 'file') {
          /** @var \Drupal\file\FileInterface[] $referenced_entities */
          foreach ($referenced_entities as $file) {
            $referenced_files[$file->uuid()] = $file;
          }
        }

        // Add this reference to the dependency graph so we can sort them at the
        // end.
        foreach ($referenced_entities as $referenced_entity) {
          $this->graph[$entity->uuid()]['edges'][$referenced_entity->uuid()] = TRUE;
        }
      }

      if ($field_items instanceof PathFieldItemList) {
        if (($value = $field_items->first()->getValue()) && isset($value['pid'])) {
          $alias = $this->entityTypeManager->getStorage('path_alias')->load($value['pid']);
          $this->graph[$entity->uuid()]['edges'][$alias->uuid()] = TRUE;
        }
      }

      $field_item = $field_items->first();
      if ($field_item instanceof LinkItemInterface && str_starts_with($field_item->uri, 'entity:')) {
        $parameters = $field_item->getUrl()->getRouteParameters();
        $target_entity_type_id = key($parameters);
        $target_entity_id = reset($parameters);
        $target_entity = $this->entityTypeManager->getStorage($target_entity_type_id)->load($target_entity_id);
        if ($target_entity) {
          $this->graph[$entity->uuid()]['edges'][$target_entity->uuid()] = TRUE;
        }
      }
    }

    $normalization = $this->getEntityNormalization($entity);
    $data = json_encode($normalization, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    $file_name = $normalization['data']['type'] . '--' . $normalization['data']['id'] . '.json';

    return [$data, $file_name, $normalization, $referenced_files, $referenced_users];
  }

  /**
   * Normalizes an entity using JSON:API.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return mixed
   *   The normalized entity data.
   */
  protected function getEntityNormalization(EntityInterface $entity) {
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
    $doc = new JsonApiDocumentTopLevel(new ResourceObjectData([ResourceObject::createFromEntity($resource_type, $entity)], 1), new NullIncludedData(), new LinkCollection([]));

    return $this->jsonApiSerializer->normalize($doc, 'api_json', [
      'resource_type' => $resource_type,
      'account' => \Drupal::currentUser(),
    ])->getNormalization();
  }

  /**
   * Normalizes a collection of entities using JSON:API.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The entities bundle name.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entity objects.
   *
   * @return mixed
   *   The normalized entity collection data.
   */
  protected function getEntityCollectionNormalization($entity_type_id, $bundle, $entities) {
    $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle);
    $data = [];
    foreach ($entities as $entity) {
      $data[] = ResourceObject::createFromEntity($resource_type, $entity);
    }
    $doc = new JsonApiDocumentTopLevel(new ResourceObjectData($data, -1), new NullIncludedData(), new LinkCollection([]));

    return $this->jsonApiSerializer->normalize($doc, 'api_json', [
      'resource_type' => $resource_type,
      'account' => \Drupal::currentUser(),
    ])->getNormalization();
  }

}
