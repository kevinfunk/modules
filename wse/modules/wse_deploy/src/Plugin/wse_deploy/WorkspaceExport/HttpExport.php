<?php

namespace Drupal\wse_deploy\Plugin\wse_deploy\WorkspaceExport;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse_deploy\EncryptionHandler;
use Drupal\wse_deploy\WorkspaceExportBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a workspace export plugin which relies on HTTP requests.
 *
 * @WorkspaceExport(
 *   id = "http",
 *   label = @Translation("HTTP")
 * )
 */
class HttpExport extends WorkspaceExportBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client to POST the files with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The hash handler.
   *
   * @var \Drupal\wse_deploy\EncryptionHandler
   */
  protected $encryptionHandler;

  /**
   * Constructs a HttpExport object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, ConfigFactoryInterface $config_factory, EncryptionHandler $encryption_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->encryptionHandler = $encryption_handler;
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
      $container->get('wse_deploy.encryption_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspaceExport(WorkspaceInterface $workspace, array $index_data, array $index_files) {
    $deploy_path = $this->configFactory->get('wse_deploy.settings')->get('deploy_path');
    $workspace_export_dir = $deploy_path . '/export/' . $workspace->id();

    // Send the archive containing the workspace data.
    $file_info = [
      'name' => 'upload',
      'filename' => 'export.tar.gz',
      'contents' => file_get_contents($workspace_export_dir . '/export.tar.gz'),
    ];
    $this->sendFile($workspace, 'data', $file_info);

    // Send all the files used by the workspace-tracked entities.
    /** @var \Drupal\file\FileInterface[] $index_files */
    foreach ($index_files as $file) {
      $file_info = [
        'name' => 'upload',
        // In order to support files with the same filename but in different
        // directories, the uploaded filename is the file UUID and its
        // extension. The import process will take care of renaming it back to
        // its original filename when moving it to the final location.
        'filename' => $file->uuid() . '.' . pathinfo($file->getFilename(), PATHINFO_EXTENSION),
        'contents' => file_get_contents($file->getFileUri()),
      ];
      $this->sendFile($workspace, 'files', $file_info);
    }

    // Inform the target that we finished uploading all the data and files.
    $this->updateWorkspaceStatus($workspace, 'ready');
  }

  /**
   * Sends the specified workspace file.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace the file belongs to.
   * @param string $type
   *   The type of file being sent.
   * @param array $file_info
   *   The file information as expected by the HTTP client.
   */
  protected function sendFile(WorkspaceInterface $workspace, string $type, array $file_info) {
    // @todo Consider computing the token based on the file content itself.
    //   This may be way more expensive but it is more secure.
    $options = [
      RequestOptions::MULTIPART => [$file_info],
      RequestOptions::QUERY => [
        'token' => $this->encryptionHandler->getExpirableToken($workspace->id(), $file_info['filename']),
      ],
    ];
    $this->httpClient->post($this->configuration['remote_endpoint'] . '/wse-deploy/import/' . $type . '/' . $workspace->id(), $options);
  }

  /**
   * Updates the workspace status on the target environment.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace being updated.
   * @param string $status_type
   *   The new status.
   */
  protected function updateWorkspaceStatus(WorkspaceInterface $workspace, string $status_type) {
    $options = [
      RequestOptions::QUERY => [
        'token' => $this->encryptionHandler->getExpirableToken($workspace->id(), $status_type),
      ],
    ];
    $this->httpClient->post($this->configuration['remote_endpoint'] . '/wse-deploy/status/' . $status_type . '/' . $workspace->id(), $options);
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspacePublish(WorkspaceInterface $workspace) {
    $this->updateWorkspaceStatus($workspace, 'publish');
  }

  /**
   * {@inheritdoc}
   */
  public function onWorkspaceRevert(WorkspaceInterface $workspace) {
    $this->updateWorkspaceStatus($workspace, 'revert');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'remote_endpoint' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['remote_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Remote endpoint'),
      '#description' => $this->t('The URL of the endpoint to POST workspace export content to. <strong>NOTE: it is strongly suggested to use a HTTPS endpoint</strong>.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['remote_endpoint'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['remote_endpoint'] = rtrim($form_state->getValue('remote_endpoint'), '/');
  }

}
