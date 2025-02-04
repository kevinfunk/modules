<?php

namespace Drupal\wse;

use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityUntranslatableFieldsConstraintValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Validator override.
 */
class WseEntityUntranslatableFieldsConstraintValidator extends EntityUntranslatableFieldsConstraintValidator {

  /**
   * The workspace information service.
   *
   * @var \Drupal\workspaces\WorkspaceInformationInterface
   */
  protected $workspaceInfo;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workspaceInfo = $container->get('workspaces.information');
    $instance->workspaceManager = $container->get('workspaces.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($this->workspaceInfo->isEntitySupported($entity) && $this->workspaceManager->hasActiveWorkspace()) {
      return;
    }

    parent::validate($entity, $constraint);
  }

}
