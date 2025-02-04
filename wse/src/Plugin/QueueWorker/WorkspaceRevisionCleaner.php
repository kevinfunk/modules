<?php

namespace Drupal\wse\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cleans up content revisions.
 *
 * @QueueWorker(
 *  id = "wse_revision_cleaner",
 *  title = @Translation("Workspace revision cleaner"),
 *  cron = {"time" = 30}
 * )
 */
class WorkspaceRevisionCleaner extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new WorkspaceRevisionCleaner object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data['process_time'] > $this->time->getCurrentTime()) {
      throw new DelayedRequeueException($data['process_time'] - $this->time->getCurrentTime());
    }

    $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
    $revisions = $storage->loadMultipleRevisions($data['items']);
    foreach ($revisions as $revision) {
      // Default revisions shouldn't normally end up in this queue, but in case
      // they do, ensure that we don't try to delete them.
      if (!$revision->isDefaultRevision()) {
        $storage->deleteRevision($revision->getRevisionId());
      }
    }
  }

}
