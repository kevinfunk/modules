<?php

namespace Drupal\wse_config;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service wrapper for hooks relating to entity access control.
 *
 * @internal
 */
class EntityAccess implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new EntityAccess instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('workspaces.manager')
    );
  }

  /**
   * Implements a hook bridge for hook_entity_access().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access for.
   * @param string $operation
   *   The operation being performed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account making the to check access for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The result of the access check.
   *
   * @see hook_entity_access()
   */
  public function entityOperationAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // We only need to act here if we're dealing with a config entity.
    if ($operation != 'delete' || !($entity instanceof ConfigEntityInterface) || !$this->workspaceManager->hasActiveWorkspace()) {
      return AccessResult::neutral();
    }

    $result = $this->entityTypeManager->getStorage('wse_config')->getQuery()
      ->accessCheck(TRUE)
      ->condition('workspace', $this->workspaceManager->getActiveWorkspace()->id())
      ->condition('name', [$entity->getConfigDependencyName()], 'IN')
      ->execute();
    if ($result) {
      return AccessResult::allowedIfHasPermission($account, 'delete wse_config');
    }
    return AccessResult::forbidden();
  }

}
