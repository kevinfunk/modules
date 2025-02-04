<?php

namespace Drupal\wse_config\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the wse_config opt out event.
 *
 * @see \Drupal\wse_config\Event\WseConfigEvents
 */
class WseConfigOptOutEvent extends Event {

  /**
   * The list of configs that aren't stored in wse_config entities.
   *
   * @var array
   */
  protected $ignoreList = [];

  /**
   * Constructs a new WseConfigOptOutEvent.
   *
   * @param array $ignored
   *   The basic list of ignored configs.
   */
  public function __construct(array $ignored = []) {
    $this->ignoreList = $ignored;
  }

  /**
   * Adds a list of config names to the ignored list.
   *
   * @param array $config_names
   *   The config names.
   */
  public function setIgnored(string ...$config_names) {
    $this->ignoreList += $config_names;
  }

  /**
   * Returns the list of ignored config names.
   *
   * @return array
   *   The list of ignored configs.
   */
  public function getIgnored() {
    return $this->ignoreList;
  }

}
