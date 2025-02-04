<?php

namespace Drupal\wse_config;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the wse config entity type.
 */
class WseConfigAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view wse_config');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit wse_config', 'administer wse config'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete wse_config', 'administer wse config'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create wse_config', 'administer wse config'], 'OR');
  }

}
