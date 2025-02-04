<?php

namespace Drupal\wse_deploy\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse\Event\WorkspaceEvents;
use Drupal\wse\Event\WorkspaceRevertEvent;
use Drupal\wse_deploy\Event\WorkspaceDeployEvents;
use Drupal\wse_deploy\Event\WorkspaceExportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The publishing pipeline handler.
 */
class PipelineHandler implements EventSubscriberInterface {

  public const STATE_READY = 'ready';
  public const STATE_PUBLISH = 'publish';
  public const STATE_REVERT = 'unpublish';

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ExportHandler instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Acts after a workspace is exported.
   */
  public function onPostExport(WorkspaceExportEvent $event): void {
    $this->setState($event->getWorkspace(), static::STATE_READY);
  }

  /**
   * Acts after a workspace is published.
   */
  public function onPostPublish(WorkspacePostPublishEvent $event): void {
    $this->setState($event->getWorkspace(), static::STATE_PUBLISH);
  }

  /**
   * Acts after a workspace is rolled back.
   */
  public function onPostRevert(WorkspaceRevertEvent $event): void {
    $this->setState($event->getWorkspace(), static::STATE_REVERT);
  }

  /**
   * Sets the pipeline state.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace being processed.
   * @param string $state
   *   The pipeline state.
   */
  protected function setState(WorkspaceInterface $workspace, string $state): void {
    $deploy_path = $this->configFactory->get('wse_deploy.settings')->get('deploy_path');
    file_put_contents($deploy_path . '/' . $workspace->id() . '.' . $state, '');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      WorkspaceDeployEvents::WORKSPACE_POST_EXPORT => [['onPostExport']],
      WorkspacePostPublishEvent::class => [['onPostPublish']],
      WorkspaceEvents::WORKSPACE_POST_REVERT => [['onPostRevert']],
    ];
  }

}
