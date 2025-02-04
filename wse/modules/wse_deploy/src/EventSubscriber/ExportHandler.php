<?php

namespace Drupal\wse_deploy\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\wse\Event\WorkspaceEvents;
use Drupal\wse\Event\WorkspaceRevertEvent;
use Drupal\wse_deploy\Event\WorkspaceDeployEvents;
use Drupal\wse_deploy\Event\WorkspaceExportEvent;
use Drupal\wse_deploy\WorkspaceExportPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The publishing export handler.
 */
class ExportHandler implements EventSubscriberInterface {

  /**
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The workspaces export plugin manager.
   *
   * @var \Drupal\wse_deploy\WorkspaceExportPluginManager
   */
  protected $workspaceExportPluginManager;

  /**
   * Constructs a new ExportHandler instance.
   */
  public function __construct(ConfigFactoryInterface $configFactory, WorkspaceExportPluginManager $workspaceExportPluginManager) {
    $this->configFactory = $configFactory;
    $this->workspaceExportPluginManager = $workspaceExportPluginManager;
  }

  /**
   * Acts after a workspace is exported.
   */
  public function onPostExport(WorkspaceExportEvent $event): void {
    if ($export_plugin = $this->getExportPlugin()) {
      $export_plugin->onWorkspaceExport($event->getWorkspace(), $event->getIndexData(), $event->getIndexFiles());
    }
  }

  /**
   * Acts after a workspace is published.
   */
  public function onPostPublish(WorkspacePostPublishEvent $event): void {
    if ($export_plugin = $this->getExportPlugin()) {
      $export_plugin->onWorkspacePublish($event->getWorkspace());
    }
  }

  /**
   * Acts after a workspace is reverted.
   */
  public function onPostRevert(WorkspaceRevertEvent $event): void {
    if ($export_plugin = $this->getExportPlugin()) {
      $export_plugin->onWorkspaceRevert($event->getWorkspace());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // This should run before the pipeline handler.
      // @todo Convert the pipeline handler to a 'local' export plugin.
      WorkspaceDeployEvents::WORKSPACE_POST_EXPORT => [['onPostExport', 10]],
      WorkspacePostPublishEvent::class => [['onPostPublish', 10]],
      WorkspaceEvents::WORKSPACE_POST_REVERT => [['onPostRevert', 10]],
    ];
  }

  /**
   * Gets the configured export plugin, if any.
   *
   * @return \Drupal\wse_deploy\WorkspaceExportInterface|null
   *   The export plugin.
   */
  protected function getExportPlugin() {
    $deploy_settings = $this->configFactory->get('wse_deploy.settings');

    if ($export_plugin_id = $deploy_settings->get('export_plugin')) {
      $export_plugin = $this->workspaceExportPluginManager->createInstance($export_plugin_id, $deploy_settings->get('export_plugin_configuration'));
    }

    /** @var \Drupal\wse_deploy\WorkspaceExportInterface|null $export_plugin */
    return $export_plugin ?? NULL;
  }

}
