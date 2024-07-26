<?php

namespace Drupal\content_calendar;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Implements ContentService class.
 */
class ContentService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get Content Configuration Type.
   *
   * @return \Drupal\content_calendar\Entity\ContentTypeConfig[]
   */
  public function getContentTypeConfig(): array {
    return $this->entityTypeManager
      ->getStorage('content_type_config')
      ->loadMultiple();
  }

  /**
   * Get the recent content.
   */
  public function getRecentContent($limit): array {
    $configs = $this->getContentTypeConfig();
    $types = [];

    foreach ($configs as $config) {
      $types[] = $config->getOriginalId();
    }

    if (empty($types)) {
      return [];
    }

    $storage = $this->entityTypeManager
      ->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('status', 1)
      ->condition('type', $types, 'IN')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->accessCheck()
      ->execute();

    if ($ids === []) {
      return [];
    }

    return $storage->loadMultiple($ids);
  }

  /**
   * Get the following content.
   */
  public function getFollowingContent($limit): array {
    $configs = $this->getContentTypeConfig();
    $types = [];

    foreach ($configs as $config) {
      $types[] = $config->getOriginalId();
    }

    if (empty($types)) {
      return [];
    }

    $storage = $this->entityTypeManager
      ->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('status', 0)
      ->condition('type', $types, 'IN')
      ->condition('publish_on', NULL, 'IS NOT NULL')
      ->sort('publish_on', 'ASC')
      ->range(0, $limit)
      ->accessCheck()
      ->execute();

    if ($ids === []) {
      return [];
    }

    return $storage->loadMultiple($ids);
  }

}
