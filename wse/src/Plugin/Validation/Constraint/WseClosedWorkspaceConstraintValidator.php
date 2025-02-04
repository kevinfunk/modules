<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EntityWorkspaceConflict constraint.
 */
class WseClosedWorkspaceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected readonly WorkspaceManagerInterface $workspaceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if (!$entity->isNew()) {
      $active_workspace = $this->workspaceManager->getActiveWorkspace();

      if ($active_workspace && wse_workspace_get_status($active_workspace) === WSE_STATUS_CLOSED) {
        $this->context->buildViolation($constraint->message)
          ->setParameter('%label', $active_workspace->label())
          ->addViolation();
      }
    }
  }

}
