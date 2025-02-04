<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for an entity being edited in a closed workspace.
 *
 * @Constraint(
 *   id = "WseClosedWorkspace",
 *   label = @Translation("Closed Workspace", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class WseClosedWorkspaceConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The %label workspace is closed, its content can no longer be edited.';

}
