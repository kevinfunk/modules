<?php

namespace Drupal\wse_config\Event;

/**
 * Defines events for the wse_config module.
 */
final class WseConfigEvents {

  /**
   * Event fired when config storage is collecting applicable config.
   *
   * @Event
   *
   * @see \Drupal\wse\Event\WseConfigOptOutEvent
   */
  const WSE_CONFIG_OPT_OUT = 'wse_config.opt_out';

}
