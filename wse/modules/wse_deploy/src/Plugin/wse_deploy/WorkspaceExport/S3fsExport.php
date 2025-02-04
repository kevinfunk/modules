<?php

namespace Drupal\wse_deploy\Plugin\wse_deploy\WorkspaceExport;

use Aws\CommandPool;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\s3fs\S3fsServiceInterface;
use Drupal\workspaces\WorkspaceInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a workspace export plugin which relies on S3FS.
 *
 * @WorkspaceExport(
 *   id = "s3",
 *   label = @Translation("AWS S3")
 * )
 */
class S3fsExport extends HttpExport implements ContainerFactoryPluginInterface {

  /**
   * The S3FS configuration.
   *
   * @var array
   */
  protected $s3Config;

  /**
   * S3 Client Interface.
   *
   * @var \Aws\S3\S3ClientInterface
   */
  protected $s3Service;

  /**
   * Constructs a HttpExport object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, ConfigFactoryInterface $config_factory, S3fsServiceInterface $s3fs) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client, $config_factory);

    $this->s3Config = $config_factory->get('s3fs.settings')->get();
    $this->s3Service = $s3fs;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('s3fs')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return \Drupal::moduleHandler()->moduleExists('s3fs');
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspaceExport(WorkspaceInterface $workspace, array $index_data, array $index_files) {
    $deploy_path = $this->configFactory->get('wse_deploy.settings')->get('deploy_path');
    $workspace_export_dir = $this->getS3Path($deploy_path) . '/export/' . $workspace->id();
    $workspace_import_dir = $this->getS3Path($deploy_path) . '/import/' . $workspace->id();
    $source_bucket = $this->configFactory->get('s3fs.settings')->get('bucket');
    $target_bucket = $this->configuration['target_bucket'];
    $s3_client = $this->getS3Client();

    // Send the archive containing the workspace data.
    $batch = [];
    $batch[] = $s3_client->getCommand('CopyObject', [
      'Bucket'     => $target_bucket,
      'Key'        => "{$workspace_import_dir}/data/export.tar.gz",
      'CopySource' => "{$source_bucket}/{$workspace_export_dir}/export.tar.gz",
    ]);

    // Send all the files used by the workspace-tracked entities.
    /** @var \Drupal\file\FileInterface[] $index_files */
    foreach ($index_files as $file) {
      // In order to support files with the same filename but in different
      // directories, the uploaded filename is the file UUID and its
      // extension. The import process will take care of renaming it back to
      // its original filename when moving it to the final location.
      $target_key_name = $file->uuid() . '.' . pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $source_key_name = $this->getS3Path($file->getFileUri());

      $batch[] = $s3_client->getCommand('CopyObject', [
        'Bucket'     => $target_bucket,
        'Key'        => "{$workspace_import_dir}/files/{$target_key_name}",
        'CopySource' => "{$source_bucket}/{$source_key_name}",
      ]);
    }

    try {
      $results = CommandPool::batch($s3_client, $batch);
      foreach ($results as $result) {
        if ($result instanceof ResultInterface) {
          // Nothing to do here.
        }
        if ($result instanceof AwsException) {
          // Throw it as an actual exception, calling code should handle it.
          throw $result;
        }
      }
    }
    catch (\Exception $e) {
      // Rethrow the exception, calling code should handle it.
      throw $e;
    }

    // Inform the target that we finished uploading all the data and files.
    $this->httpClient->post($this->configuration['remote_endpoint'] . '/wse-deploy/status/ready/' . $workspace->id());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'target_bucket' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // @todo Should we validate that the target bucket is writable?
    $form['target_bucket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target bucket'),
      '#description' => $this->t('The target S3 bucket.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['target_bucket'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['target_bucket'] = $form_state->getValue('target_bucket');
  }

  /**
   * Instantiates the S3 client.
   *
   * @return \Aws\S3\S3Client
   *   The S3 client.
   */
  private function getS3Client() {
    return $this->s3Service->getAmazonS3Client($this->s3Config);
  }

  /**
   * Converts a filesystem URI to its S3 path.
   *
   * @param string $uri
   *   The URI to resolve.
   *
   * @return string
   *   The filesystem URI.
   */
  private function getS3Path(string $uri) {
    $scheme = StreamWrapperManager::getScheme($uri);
    $s3_path = StreamWrapperManager::getTarget($uri);

    if ($scheme === 'public') {
      $target_folder = !empty($this->s3Config['public_folder']) ? $this->s3Config['public_folder'] . '/' : 's3fs-public/';
      $s3_path = $target_folder . $s3_path;
    }
    elseif ($scheme === 'private') {
      $target_folder = !empty($this->s3Config['private_folder']) ? $this->s3Config['private_folder'] . '/' : 's3fs-private/';
      $s3_path = $target_folder . $s3_path;
    }

    if (!empty($this->s3Config['root_folder'])) {
      $s3_path = $this->s3Config['root_folder'] . '/' . $s3_path;
    }

    return $s3_path;
  }

}
