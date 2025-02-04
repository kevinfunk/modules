<?php

namespace Drupal\wse_config\EventSubscriber;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse\Event\WorkspaceEvents;
use Drupal\wse\Event\WorkspaceRevertEvent;
use Drupal\wse_config\Event\WseConfigEvents;
use Drupal\wse_config\Event\WseConfigOptOutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Wse config event subscriber.
 */
class WseConfigSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The WseConfigDatabaseStorage configuration storage.
   *
   * @var \Drupal\wse_config\WseConfigDatabaseStorage
   */
  protected $wseConfigStorage;

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\StorageInterface $wse_config_storage
   *   The WseConfigDatabaseStorage configuration storage.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(MessengerInterface $messenger, StorageInterface $wse_config_storage, WorkspaceManagerInterface $workspace_manager, ModuleHandlerInterface $module_handler) {
    $this->messenger = $messenger;
    $this->wseConfigStorage = $wse_config_storage;
    $this->workspaceManager = $workspace_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Deploys wse_config to the active config after a workspace got published.
   *
   * @param \Drupal\workspaces\Event\WorkspacePrePublishEvent $event
   *   The workspace pre-publish event.
   */
  public function onWorkspacePrePublish(WorkspacePrePublishEvent $event): void {
    try {
      $this->workspaceManager->executeInWorkspace($event->getWorkspace()->id(), function () {
        $this->wseConfigStorage->publishWseConfig();
      });

      $this->moduleHandler->invokeAll('cache_flush');
      foreach (Cache::getBins() as $cache_backend) {
        $cache_backend->deleteAll();
      }
    }
    catch (\Exception $exception) {
      Error::logException($this->getLogger('wse_config'), $exception);
      $this->messenger->addError(new FormattableMarkup('Error during deployment of config changed in the workspace: @message', [
        '@message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Deploys wse_config to the active config after a workspace is reverted.
   *
   * @param \Drupal\wse\Event\WorkspaceRevertEvent $event
   *   The event object passed with the post revert event.
   */
  public function onWorkspacePostRevert(WorkspaceRevertEvent $event) {
    try {
      $revert_to_revisions = $event->getRevertToRevisions();
      if (isset($revert_to_revisions['wse_config'])) {
        $this->wseConfigStorage->revertWseConfig($revert_to_revisions['wse_config']);
        drupal_flush_all_caches();
      }
    }
    catch (\Exception $exception) {
      Error::logException($this->getLogger('wse_config'), $exception);
      $this->messenger->addError(new FormattableMarkup('Error during deployment of config reverted in the workspace: @message', [
        '@message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Provides a default list of ignored configs.
   *
   * @param \Drupal\wse_config\Event\WseConfigOptOutEvent $event
   *   The wse config opt out event.
   */
  public function onWseConfigOptOut(WseConfigOptOutEvent $event) {
    $config_names = [
      'core.extension',
      'core.base_field_override.*',
      'field.field.*',
      'field.storage.*',
      'system.*',
      'node.type.*',
      'taxonomy.vocabulary.*',
      'user.*',
      'block_content.type.*',
      'comment.type.*',
      'media.*',
      'pathauto.*',
      'menu_item_extras.utility',
      'wse_config.*',
      'wse.*',
      'variants.settings',
      'contact.form.*',
      'shortcut.set.*',
      'variants.variant_type.*',
      'workflows.workflow.*',
      'trash.settings',
      'language.*',
    ];
    $event->setIgnored(...$config_names);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[WorkspacePrePublishEvent::class][] = 'onWorkspacePrePublish';
    $events[WorkspaceEvents::WORKSPACE_POST_REVERT][] = 'onWorkspacePostRevert';
    $events[WseConfigEvents::WSE_CONFIG_OPT_OUT][] = 'onWseConfigOptOut';
    return $events;
  }

}
