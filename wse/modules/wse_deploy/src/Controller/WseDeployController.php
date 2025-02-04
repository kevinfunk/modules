<?php

namespace Drupal\wse_deploy\Controller;

use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wse_deploy\EncryptionHandler;
use Drupal\wse_deploy\WorkspaceImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides responses for wse_deploy.
 */
class WseDeployController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The archiver plugin manager service.
   *
   * @var \Drupal\Core\Archiver\ArchiverManager
   */
  protected $archiverManager;

  /**
   * The workspace importer.
   *
   * @var \Drupal\wse_deploy\WorkspaceImporter
   */
  protected $workspaceImporter;

  /**
   * The encryption handler.
   *
   * @var \Drupal\wse_deploy\EncryptionHandler
   */
  protected $encryptionHandler;

  /**
   * Constructs a new WseDeployController.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ArchiverManager $archiver_manager, WorkspaceImporter $workspace_importer, EncryptionHandler $encryption_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->archiverManager = $archiver_manager;
    $this->workspaceImporter = $workspace_importer;
    $this->encryptionHandler = $encryption_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('plugin.manager.archiver'),
      $container->get('wse_deploy.workspace_importer'),
      $container->get('wse_deploy.encryption_handler')
    );
  }

  /**
   * Uploads and saves files from a source export POST.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the token cannot be validated.
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when file system errors occur.
   */
  public function upload(Request $request) {
    // Get the uploaded file directly from the request.
    $upload = $request->files->get('upload');
    $upload_type = $request->get('upload_type');
    $workspace_id = $request->get('workspace_id');
    $filename = $upload->getClientOriginalName();

    $token = $request->query->get('token');
    $verified = $this->encryptionHandler->validateExpirableToken($token, $workspace_id, $filename);
    if (!$verified) {
      throw new AccessDeniedHttpException();
    }

    $deploy_path = $this->config('wse_deploy.settings')->get('deploy_path');
    $destination = "$deploy_path/import/$workspace_id/$upload_type";

    // Cleanup filesystem. Data is always transferred first, so we only need to
    // do this once.
    if ($upload_type === 'data' && is_dir("$deploy_path/import/$workspace_id")) {
      $this->fileSystem->deleteRecursive("$deploy_path/import/$workspace_id");
    }

    // Check the destination file path is writable.
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Destination file path is not writable');
    }

    // Create the file.
    $file_uri = "{$destination}/{$filename}";
    $file_uri = $this->fileSystem->getDestinationFilename($file_uri, FileExists::Replace);

    try {
      $this->fileSystem->move($upload->getRealPath(), $file_uri, FileExists::Error);
    }
    catch (FileException $e) {
      throw new HttpException(500, 'Temporary file could not be moved to file location');
    }

    // HTTP 204 is "No content", meaning "I did what you asked, and we're done".
    return new Response('', 204);
  }

  /**
   * Executes various actions on a workspace.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the token cannot be validated.
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when the workspace can't be imported, published or reverted.
   */
  public function status(Request $request) {
    $status_type = $request->get('status');
    $workspace_id = $request->get('workspace_id');

    $token = $request->query->get('token');
    $verified = $this->encryptionHandler->validateExpirableToken($token, $workspace_id, $status_type);
    if (!$verified) {
      throw new AccessDeniedHttpException();
    }

    $workspace = $this->entityTypeManager
      ->getStorage('workspace')
      ->load($workspace_id);

    switch ($status_type) {
      case 'ready':
        try {
          $import_path = $this->config('wse_deploy.settings')->get('deploy_path') . "/import/$workspace_id";
          $this->workspaceImporter->importWorkspace($import_path);
        }
        catch (\Exception $e) {
          throw new HttpException(500, 'Workspace import failed: ' . $e->getMessage());
        }
        break;

      case 'publish':
        // Check that the workspace exists.
        if (!$workspace) {
          throw new HttpException(500, 'Workspace not found');
        }

        try {
          $workspace->publish();
        }
        catch (\Exception $e) {
          throw new HttpException(500, 'Workspace publication failed: ' . $e->getMessage());
        }
        break;

      case 'revert':
        // Check that the workspace exists.
        if (!$workspace) {
          throw new HttpException(500, 'Workspace not found');
        }

        try {
          $import_path = $this->config('wse_deploy.settings')->get('deploy_path') . "/import/$workspace_id";
          $this->workspaceImporter->revertImportedWorkspace($workspace, $import_path);
        }
        catch (\Exception $e) {
          throw new HttpException(500, 'Workspace revert failed: ' . $e->getMessage());
        }
        break;
    }

    // HTTP 204 is "No content", meaning "I did what you asked, and we're done".
    return new Response('', 204);
  }

}
