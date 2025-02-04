<?php

namespace Drupal\wse_scheduler\Commands;

use Drupal\wse_scheduler\ScheduledWorkspacePublisher;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the wse_scheduler module.
 */
class WseSchedulerCommands extends DrushCommands {

  /**
   * The scheduled workspace publisher service.
   *
   * @var \Drupal\wse_scheduler\ScheduledWorkspacePublisher
   */
  protected $scheduledWorkspacePublisher;

  public function __construct(ScheduledWorkspacePublisher $publisher) {
    $this->scheduledWorkspacePublisher = $publisher;
  }

  /**
   * Publishes workspaces that are scheduled to be published.
   *
   * @param string $workspace
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option dry-run
   *   Perform a dry run of publishing of scheduled workspaces, don't actually
   *   publish anything yet.
   * @usage wse_scheduler:publish wse-ps
   *   Publishes workspaces that are due to be published according to the
   *   value in the published_on base field.
   *
   * @command wse_scheduler:publish
   * @aliases wse-ps
   */
  public function publishScheduled($workspace = 'all', $options = ['dry-run' => FALSE]) {
    $this->scheduledWorkspacePublisher->publishScheduledWorkspaces();
  }

}
