<?php

namespace Drupal\wse_scheduler\Event;

/**
 * Defines events for the WSE module.
 */
final class WorkspaceSchedulerEvents {

  /**
   * Name of the event fired before a scheduled workspace gets published.
   *
   * @Event
   *
   * @see \Drupal\wse_scheduler\Event\WorkspaceScheduledPublishEvent
   */
  const SCHEDULED_PUBLISHING_ELIGIBLE = 'wse_scheduler.scheduled_publishing_eligible';

}
