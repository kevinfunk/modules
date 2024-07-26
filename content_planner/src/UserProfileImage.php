<?php

namespace Drupal\content_planner;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Implements UserProfileImage class.
 */
class UserProfileImage {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new UserProfileImage object.
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
   * Helper method that generate image url of the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   * @param string $image_style
   *   Image style ID.
   *
   * @return bool|string
   *   Image url or FALSE on failure.
   */
  public function generateProfileImageUrl(UserInterface $user, $image_style) {

    $image_style_storage = $this->entityTypeManager
      ->getStorage('image_style');

    if (
      ($user->hasField('user_picture')) &&
      ($file_entity = $user->get('user_picture')->entity) &&
      ($style = $image_style_storage->load($image_style))
    ) {
      // Build image style url.
      return $style->buildUrl($file_entity->getFileUri());
    }

    return FALSE;
  }

}
