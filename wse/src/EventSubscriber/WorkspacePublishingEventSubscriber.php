<?php

namespace Drupal\wse\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Site\Settings;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse\Form\WseWorkspacePublishForm;
use Drupal\wse\PublishedRevisionStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to respond to workspace publishing events.
 */
class WorkspacePublishingEventSubscriber implements EventSubscriberInterface {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * A config factory for retrieving required config settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The workspace revision cleaner queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The published revisions storage.
   *
   * @var \Drupal\wse\PublishedRevisionStorage
   */
  protected $publishedRevisionStorage;

  /**
   * Constructs a new WorkspacePublishingEventSubscriber.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving the site front page configuration.
   * @param \Drupal\wse\PublishedRevisionStorage $published_revision_storage
   *   The published revisions storage.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, WorkspaceAssociationInterface $workspace_association, ConfigFactoryInterface $config_factory, PublishedRevisionStorage $published_revision_storage, QueueFactory $queue_factory) {
    $this->workspaceManager = $workspace_manager;
    $this->workspaceAssociation = $workspace_association;
    $this->configFactory = $config_factory;
    $this->publishedRevisionStorage = $published_revision_storage;
    $this->queue = $queue_factory->get('wse_revision_cleaner', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      WorkspacePrePublishEvent::class => ['onPrePublish'],
      WorkspacePostPublishEvent::class => ['onPostPublish'],
    ];
    return $events;
  }

  /**
   * Stores published revision ids if corresponding config is set.
   *
   * @param \Drupal\workspaces\Event\WorkspacePrePublishEvent $event
   *   The workspace pre-publish event.
   */
  public function onPrePublish(WorkspacePrePublishEvent $event): void {
    $workspace = $event->getWorkspace();
    $wse_settings = $this->configFactory->get('wse.settings');

    $save_published_revisions = $wse_settings->get('save_published_revisions') ?? FALSE;
    if ($workspace->_save_published_revisions !== NULL) {
      $save_published_revisions = $workspace->_save_published_revisions;
    }
    if ($save_published_revisions) {
      $this->publishedRevisionStorage->storePublishedRevisions($workspace);
    }

    $squash_on_publish = $wse_settings->get('squash_on_publish') ?? FALSE;
    $squash_on_publish_interval = $wse_settings->get('squash_on_publish_interval') ?? 0;
    if ($squash_on_publish) {
      $tracked_entities = $this->workspaceAssociation->getTrackedEntities($workspace->id());
      $step_size = Settings::get('entity_update_batch_size', 50);
      foreach ($tracked_entities as $entity_type_id => $entities) {
        $associated_revisions = $this->workspaceAssociation->getAssociatedRevisions($workspace->id(), $entity_type_id);

        // Remove the revisions that will be published as default revisions from
        // the list of items to delete.
        $revisions_to_squash = array_keys(array_diff_key($associated_revisions, $entities));
        foreach (array_chunk($revisions_to_squash, $step_size) as $revisions_to_squash_chunk) {
          $data = [
            'process_time' => ($squash_on_publish_interval * 3600) + \Drupal::time()->getCurrentTime(),
            'entity_type_id' => $entity_type_id,
            'items' => $revisions_to_squash_chunk,
          ];
          $this->queue->createItem($data);
        }
      }
    }
  }

  /**
   * Changes the state of a workspace to 'archived' after it is published.
   *
   * @param \Drupal\workspaces\Event\WorkspacePostPublishEvent $event
   *   The workspace post-publish event.
   */
  public function onPostPublish(WorkspacePostPublishEvent $event): void {
    $wse_settings = $this->configFactory->get('wse.settings');
    $workspace = $event->getWorkspace();

    // Create a new workspace with the same configuration (label, owner, etc.)
    $clone_on_publish = $wse_settings->get('clone_on_publish') ?? FALSE;
    if ($workspace->_clone_on_publish !== NULL) {
      $clone_on_publish = (bool) $workspace->_clone_on_publish;
    }
    if ($clone_on_publish) {
      $new_workspace = $workspace->createDuplicate();
      $new_workspace->set('id', $new_workspace->uuid());
      $new_workspace->set('status', WSE_STATUS_OPEN);
      if ($new_workspace->hasField('publish_on')) {
        $new_workspace->set('publish_on', NULL);
      }
      $new_workspace->save();

      // And switch to the new workspace.
      $this->workspaceManager->setActiveWorkspace($new_workspace);
    }
    else {
      $this->workspaceManager->switchToLive();
    }

    // Mark the published workspace as closed.
    $workspace->set('status', WSE_STATUS_CLOSED);
    $workspace->save();

    // Store a snapshot of all default revisions after publishing if the
    // corresponding option is set in config.
    $save_published_revisions = $wse_settings->get('save_published_revisions') ?? FALSE;
    if ($workspace->_save_published_revisions !== NULL) {
      $save_published_revisions = $workspace->_save_published_revisions;
    }
    if ($save_published_revisions && $save_published_revisions == WseWorkspacePublishForm::SAVE_PUBLISHED_REVISIONS_ALL) {
      $this->publishedRevisionStorage->storeAllRevisions($workspace);
    }
  }

}
