<?php

namespace Drupal\wse_scheduler\Event;

use Drupal\workspaces\Event\WorkspacePublishEvent;

/**
 * Defines the workspace scheduled publish event.
 */
class WorkspaceScheduledPublishEvent extends WorkspacePublishEvent {

  /**
   * Determines whether a scheduled publishing is eligible.
   *
   * @var bool
   */
  protected $isEligible = TRUE;

  /**
   * Sets eligibility of the scheduled publish event.
   *
   * @param bool $value
   *   Whether the publishing is eligible.
   */
  public function setEligible($value) {
    $this->isEligible = $value;
  }

  /**
   * Gets eligibility of the scheduled publish event.
   *
   * @return bool
   *   Whether the publishing is eligible.
   */
  public function isPublishingEligible() {
    return $this->isEligible;
  }

}
