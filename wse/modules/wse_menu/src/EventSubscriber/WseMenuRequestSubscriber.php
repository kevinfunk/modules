<?php

declare(strict_types=1);

namespace Drupal\wse_menu\EventSubscriber;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse_menu\WseMenuTreeStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a event subscriber for rebuilding the workspace menu tree.
 */
class WseMenuRequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Name of the flags state global lock.
   */
  public const LOCK_REBUILD = 'wse_menu_tree_rebuild';

  /**
   * Name of the flags state.
   */
  public const STATE_REBUILD_FLAGS = 'wse_menu_tree_needs_rebuild';

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly WseMenuTreeStorageInterface $menuTreeStorage,
    protected readonly MessengerInterface $messenger,
    protected readonly LoggerInterface $logger,
    protected readonly LockBackendInterface $lock,
  ) {}

  /**
   * Rebuilds the workspace menu tree if necessary.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   An event object.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if ($this->workspaceManager->hasActiveWorkspace()) {
      $rebuild_menu = $this->state->get(static::STATE_REBUILD_FLAGS, []);
      $workspace_id = $this->workspaceManager->getActiveWorkspace()->id();
      $workspace_lock_name = 'wse_menu_tree_rebuild_' . $workspace_id;
      // Acquire a ws-specific lock here to avoid performing the rebuild
      // multiple times, and just skip this logic altogether if the lock cannot
      // be acquired, knowing that menu is being rebuilt in another request.
      if (!empty($rebuild_menu[$workspace_id]) && $this->lock->acquire($workspace_lock_name)) {
        try {
          $this->menuTreeStorage->rebuildWorkspaceMenuTree($this->workspaceManager->getActiveWorkspace());
          $this->messenger->addStatus($this->t('The workspace menu tree has been rebuilt.'));

          // Since the state value contains multiple flags, we should wait
          // until we can acquire a global lock, fetch the value from state
          // again, and only then we can safely set it, otherwise we may
          // override other flag values having been updated while rebuilding
          // was happening.
          while (!$this->lock->acquire(static::LOCK_REBUILD)) {
            $this->lock->wait(static::LOCK_REBUILD);
          }

          $rebuild_menu = $this->state->get(static::STATE_REBUILD_FLAGS, []);
          $rebuild_menu[$workspace_id] = FALSE;
          $this->state->set(static::STATE_REBUILD_FLAGS, $rebuild_menu);
        }
        catch (\Exception $e) {
          Error::logException($this->logger, $e);
          $this->messenger->addError($this->t('The workspace menu tree could not be rebuilt. All errors have been logged.'));
        }
      }
      $this->lock->release(static::LOCK_REBUILD);
      $this->lock->release($workspace_lock_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 0];
    return $events;
  }

}
