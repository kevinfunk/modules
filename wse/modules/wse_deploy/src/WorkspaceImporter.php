<?php

namespace Drupal\wse_deploy;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Serializer\Serializer;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;
use Drupal\user\UserInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse\WorkspaceReverter;
use Psr\Log\LoggerInterface;

/**
 * Defines a service for importing workspace contents.
 */
class WorkspaceImporter {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The JSON:API serializer.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected Serializer $jsonApiSerializer;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected ResourceTypeRepositoryInterface $resourceTypeRepository;

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected WorkspaceManagerInterface $workspaceManager;

  /**
   * The workspace reverter.
   *
   * @var \Drupal\wse\WorkspaceReverter
   */
  protected $workspaceReverter;

  /**
   * The signature handler.
   *
   * @var \Drupal\wse_deploy\EncryptionHandler
   */
  protected EncryptionHandler $signatureHandler;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * WorkspaceImporter constructor.
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    FileSystemInterface $file_system,
    Serializer $serializer,
    ResourceTypeRepositoryInterface $resource_type_repository,
    WorkspaceManagerInterface $workspace_manager,
    WorkspaceReverter $workspace_reverter,
    EncryptionHandler $signature_handler,
    AccountSwitcherInterface $account_switcher,
    AccountInterface $current_user,
    LoggerInterface $logger,
  ) {
    $this->database = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->fileSystem = $file_system;
    $this->jsonApiSerializer = $serializer;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->workspaceManager = $workspace_manager;
    $this->workspaceReverter = $workspace_reverter;
    $this->signatureHandler = $signature_handler;
    $this->accountSwitcher = $account_switcher;
    $this->currentUser = $current_user;
    $this->logger = $logger;
  }

  /**
   * Imports the specified workspace.
   */
  public function importWorkspace(string $path): void {
    // First, check to see if there's an archive we need to extract.
    $export_archive_path = $path . '/data/export.tar.gz';
    if (is_file($export_archive_path)) {
      $archive = new ArchiveTar($export_archive_path, 'gz');

      try {
        $archive->extract($path . '/data');

        // Delete the archive after we extracted its contents.
        $this->fileSystem->delete($export_archive_path);
      }
      catch (\Exception $e) {
        $this->throwError('Export archive could not be extracted: @message', ['@message' => $e->getMessage()]);
      }
    }

    $transaction = $this->database->startTransaction();
    try {
      $super_user = $this->entityTypeManager
        ->getStorage('user')
        ->load(1);
      assert($super_user instanceof UserInterface);

      $workspace = $this->importWorkspaceEntity($path, $super_user);
      $this->importUsers($path, $super_user);
      $this->importFiles($path, $super_user);
      $this->importEntities($path, $super_user, $workspace);
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * Imports the workspace entity.
   */
  protected function importWorkspaceEntity($path, $super_user) {
    $workspace = $this->getExportedWorkspace($path);
    if (!$workspace instanceof WorkspaceInterface) {
      $this->throwError('Workspace could not be saved.');
    }

    try {
      $this->accountSwitcher->switchTo($super_user);
      $this->saveEntity($workspace, FALSE);
      $this->accountSwitcher->switchBack();
    }
    catch (\Exception $e) {
      $this->throwError('Workspace could not be saved: @message', ['@message' => $e->getMessage()]);
    }

    return $workspace;
  }

  /**
   * Imports the users of the workspace.
   */
  protected function importUsers($path, $super_user) {
    $users_path = $path . '/data/users.json';
    if (!is_file($users_path)) {
      $this->throwError('JSON export users not found: @path', ['@path' => $users_path]);
    }

    $raw_data = file_get_contents($users_path);
    $hash = $this->getFileHash($users_path);
    if (!$this->checkHash($raw_data, $hash)) {
      $this->throwError('Invalid JSON export users hash: @path, @hash', ['@path' => $users_path, '@hash' => $hash]);
    }

    try {
      $this->accountSwitcher->switchTo($super_user);

      $users = json_decode($raw_data, TRUE);
      $header = $users['jsonapi'];
      foreach ($users['data'] as $data) {
        $data = [
          'jsonapi' => $header,
          'data' => $data,
        ];

        $user = $this->deserialize($data, 'user', 'user');
        $this->saveEntity($user, FALSE);
      }

      $this->accountSwitcher->switchBack();
    }
    catch (\InvalidArgumentException $e) {
      if (isset($user)) {
        $this->throwError('Hash check did not match for entity @uuid', ['@uuid' => $user->uuid()]);
      }
    }
    catch (\Exception $e) {
      if (isset($data)) {
        $this->throwError('Invalid export for entity @uuid', ['@uuid' => $data['data']['id']]);
      }
    }
  }

  /**
   * Imports the files of the workspace.
   */
  protected function importFiles($path, $super_user) {
    $files_data_path = $path . '/data/files.json';
    if (!is_file($files_data_path)) {
      $this->throwError('JSON export files not found: @path', ['@path' => $files_data_path]);
    }

    $raw_data = file_get_contents($files_data_path);
    $hash = $this->getFileHash($files_data_path);
    if (!$this->checkHash($raw_data, $hash)) {
      $this->throwError('Invalid JSON export files hash: @path, @hash', ['@path' => $files_data_path, '@hash' => $hash]);
    }

    try {
      $this->accountSwitcher->switchTo($super_user);

      $files = json_decode($raw_data, TRUE);
      $header = $files['jsonapi'];
      foreach ($files['data'] as $data) {
        $data = [
          'jsonapi' => $header,
          'data' => $data,
        ];
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->deserialize($data, 'file', 'file');

        // Move the actual file in place before saving the file entity.
        $import_file_location = $path . '/files/' . $file->uuid() . '.' . pathinfo($file->getFilename(), PATHINFO_EXTENSION);
        $destination_dir = $this->fileSystem->dirname($file->getFileUri());
        $this->fileSystem->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY);
        $this->fileSystem->move($import_file_location, $file->getFileUri(), FileExists::Error);

        $this->saveEntity($file, FALSE);
      }

      $this->accountSwitcher->switchBack();
    }
    catch (\InvalidArgumentException $e) {
      if (isset($file)) {
        $this->throwError('Hash check did not match for entity @uuid', ['@uuid' => $file->uuid()]);
      }
    }
    catch (\Exception $e) {
      if (isset($file)) {
        $this->throwError('Invalid export for entity @uuid', ['@uuid' => $file->uuid()]);
      }
    }
  }

  /**
   * Imports the entities of the workspace.
   */
  protected function importEntities($path, $super_user, $workspace) {
    $path .= '/data';
    $index_path = $path . '/index.json';
    if (!is_file($index_path)) {
      $this->throwError('JSON export index not found: @path', ['@path' => $index_path]);
    }

    $raw_data = file_get_contents($index_path);
    $hash = $this->getFileHash($index_path);
    if (!$this->checkHash($raw_data, $hash)) {
      $this->throwError('Invalid JSON export index hash: @path, @hash', ['@path' => $path, '@hash' => $hash]);
    }

    try {
      $this->accountSwitcher->switchTo($super_user);

      $index = json_decode($raw_data);
      foreach ($index as $info) {
        $entity_path = $path . '/' . $info->filename;
        $entity = $this->deserializeExportFile($entity_path, $info->entity_languages, $info->hash);
        $this->workspaceManager->executeInWorkspace($workspace->id(), function () use ($entity) {
          $this->saveEntity($entity);
        });
      }

      $this->accountSwitcher->switchBack();
    }
    catch (\InvalidArgumentException $e) {
      if (isset($info)) {
        $this->throwError('Hash check did not match for entity @uuid', ['@uuid' => $info->entity_uuid]);
      }
    }
    catch (\Exception $e) {
      if (isset($info)) {
        $this->throwError('Invalid export for entity @uuid', ['@uuid' => $info->entity_uuid]);
      }
    }
  }

  /**
   * Returns the exported workspace.
   *
   * @param string $path
   *   The path of the workspace export.
   *
   * @return \Drupal\workspaces\WorkspaceInterface|null
   *   A workspace entity or NULL if none could be found.
   */
  protected function getExportedWorkspace(string $path): ?WorkspaceInterface {
    $workspace_export_path = $path . '/data/workspace.json';
    if (!is_file($workspace_export_path)) {
      $this->throwError('Workspace JSON export not found: @path', ['@path' => $workspace_export_path]);
    }

    try {
      $hash = $this->getFileHash($workspace_export_path);
      $workspace = $this->deserializeExportFile($workspace_export_path, [], $hash);
    }
    catch (\Exception $e) {
      $workspace = NULL;
      $this->throwError('Invalid workspace export data.');
    }

    return $workspace instanceof WorkspaceInterface ? $workspace : NULL;
  }

  /**
   * Deserializes an export file.
   *
   * @param string $path
   *   The file path.
   * @param array $langcodes
   *   (optional) The entity translation language codes. Defaults to none,
   *   meaning untranslated entity.
   * @param string|null $hash
   *   (optional) The content hash.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The exported entity.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  protected function deserializeExportFile(string $path, array $langcodes = [], ?string $hash = NULL): ContentEntityInterface {
    $raw_data = file_get_contents($path);
    if ($hash && !$this->checkHash($raw_data, $hash)) {
      $message = sprintf('Invalid hash "%s" for "%s"', $hash, $path);
      throw new \InvalidArgumentException($message);
    }
    $data = json_decode($raw_data, TRUE);

    $all_translation_data = NULL;
    if (count($langcodes) > 1) {
      $all_translation_data = $data;
      $data = $data[reset($langcodes)];
    }

    [$entity_type_id, $bundle] = explode('--', $data['data']['type']);
    $entity = $this->deserialize($data, $entity_type_id, $bundle);

    if ($all_translation_data) {
      $default_langcode = $entity->language()->getId();
      foreach ($all_translation_data as $langcode => $translation_data) {
        if ($langcode !== $default_langcode) {
          $translation = $this->deserialize($translation_data, $entity_type_id, $bundle);
          $entity->addTranslation($translation->language()->getId(), $translation->toArray());
        }
      }
    }

    return $entity;
  }

  /**
   * Returns the stored hash for the specified file.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return string
   *   The file hash as stored in the corresponding hash file.
   */
  protected function getFileHash(string $file_path): string {
    return (string) file_get_contents($file_path . '.hash');
  }

  /**
   * Checks data integrity.
   *
   * @param string $raw_data
   *   The raw exported data.
   * @param string $hash
   *   The content hash.
   *
   * @return bool
   *   TRUE if check passed, FALSE otherwise.
   */
  protected function checkHash(string $raw_data, string $hash): bool {
    return $this->signatureHandler->validateHash($raw_data, $hash);
  }

  /**
   * Deserializes the specified entity.
   *
   * @param array $data
   *   The exported data.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The exported entity.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  protected function deserialize(array $data, string $entity_type_id, string $bundle): ContentEntityInterface {
    $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle);
    $context = ['resource_type' => $resource_type];
    return $this->jsonApiSerializer->denormalize($data, JsonApiDocumentTopLevel::class, 'api_json', $context);
  }

  /**
   * Saves the specified entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be saved.
   * @param bool $update
   *   (optional) Whether the entity should be updated, a new revision will be
   *   created. Defaults to TRUE.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveEntity(ContentEntityInterface $entity, bool $update = TRUE): void {
    $entity_type_id = $entity->getEntityTypeId();

    $stored_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $entity->uuid());
    if ($stored_entity) {
      if (!$update) {
        return;
      }
      $id_key = $entity->getEntityType()->getKey('id');
      $entity->set($id_key, $stored_entity->id());
    }

    $entity->enforceIsNew(!$stored_entity);

    if ($entity->getEntityType()->isRevisionable()) {
      $entity->setNewRevision(TRUE);
      $revision_key = $entity->getEntityType()->getKey('revision');
      $entity->set($revision_key, NULL);
    }

    // The path field item is computed, and if an entity has a path alias it
    // will be exported/imported as a regular entity, so we have to empty the
    // computed value in order to stop
    // \Drupal\path\Plugin\Field\FieldType\PathItem::postSave() from creating a
    // duplicate path alias entity.
    foreach ($entity->getFields() as $field_name => $field_items) {
      if ($field_items instanceof PathFieldItemList) {
        unset($entity->{$field_name});
      }
    }

    $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->save($entity);
  }

  /**
   * Reverts the specified workspace.
   */
  public function revertImportedWorkspace(WorkspaceInterface $workspace, string $import_path): void {
    $transaction = $this->database->startTransaction();
    try {
      // Revert the workspace.
      $this->workspaceReverter->revert($workspace);

      // Delete its files.
      $files_data_path = $import_path . '/data/files.json';
      if (!is_file($files_data_path)) {
        $this->throwError('JSON export files not found: @path', ['@path' => $files_data_path]);
      }

      $files_data = json_decode(file_get_contents($files_data_path), TRUE);
      foreach ($files_data['data'] as $data) {
        $entities = $this->entityTypeManager->getStorage('file')->loadByProperties(['uuid' => $data['id']]);
        $file = reset($entities);
        $file->delete();
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * Logs the given error and then throws it as an exception.
   */
  private function throwError($message, array $context = []) {
    $exception_message = new FormattableMarkup($message, $context);
    throw new \RuntimeException((string) $exception_message);
  }

}
