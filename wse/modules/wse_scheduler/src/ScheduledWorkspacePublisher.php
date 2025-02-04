<?php

namespace Drupal\wse_scheduler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\wse_scheduler\Event\WorkspaceScheduledPublishEvent;
use Drupal\wse_scheduler\Event\WorkspaceSchedulerEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The scheduled workspace publisher.
 */
class ScheduledWorkspacePublisher {

  use LoggerChannelTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  private WorkspaceAssociationInterface $workspaceAssociation;

  /**
   * Constructs a ScheduledWorkspacePublisher object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    WorkspaceAssociationInterface $workspace_association,
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->workspaceAssociation = $workspace_association;
    $this->setLoggerFactory($logger_factory);
  }

  /**
   * Publishes due workspaces scheduled for publishing.
   */
  public function publishScheduledWorkspaces() {
    $logger = $this->getLogger('wse_scheduler');
    try {
      /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
      foreach ($this->loadScheduledWorkspaces() as $workspace) {
        $tracked_entities = $this->workspaceAssociation->getTrackedEntities($workspace->id());

        /** @var \Drupal\wse_scheduler\Event\WorkspaceScheduledPublishEvent $event */
        $event = $this->eventDispatcher->dispatch(
          new WorkspaceScheduledPublishEvent($workspace, $tracked_entities),
          WorkspaceSchedulerEvents::SCHEDULED_PUBLISHING_ELIGIBLE
        );

        if ($event->isPublishingEligible()) {
          $workspace->publish();
          $logger->notice(
            'Workspace @workspace scheduled for publishing was successfully published.',
            ['@workspace' => $workspace->label()]
          );
        }
        else {
          $logger->error(
            'Workspace @workspace scheduled for publishing was not eligible for publishing.',
            ['@workspace' => $workspace->label()]
          );
        }
      }
    }
    catch (\Exception $exception) {
      Error::logException($logger, $exception);
    }
  }

  /**
   * Loads workspaces which were scheduled and are due for publishing.
   *
   * @return \Drupal\workspaces\WorkspaceInterface[]
   *   An array of scheduled workspaces.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadScheduledWorkspaces() {
    $storage = $this->entityTypeManager->getStorage('workspace');
    $result = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', WSE_STATUS_OPEN)
      ->condition('publish_on', $this->time->getCurrentTime(), '<')
      ->execute();

    return $result ? $storage->loadMultiple($result) : [];
  }

}
