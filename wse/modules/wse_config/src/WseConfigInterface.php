<?php

namespace Drupal\wse_config;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a wse config entity type.
 */
interface WseConfigInterface extends EntityPublishedInterface, ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the wse config creation timestamp.
   *
   * @return int
   *   Creation timestamp of the wse config.
   */
  public function getCreatedTime();

  /**
   * Sets the wse config creation timestamp.
   *
   * @param int $timestamp
   *   The wse config creation timestamp.
   *
   * @return \Drupal\wse_config\WseConfigInterface
   *   The called wse config entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the wse config status.
   *
   * @return bool
   *   TRUE if the wse config is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the wse config status.
   *
   * @param bool $status
   *   TRUE to enable this wse config, FALSE to disable.
   *
   * @return \Drupal\wse_config\WseConfigInterface
   *   The called wse config entity.
   */
  public function setStatus($status);

}
