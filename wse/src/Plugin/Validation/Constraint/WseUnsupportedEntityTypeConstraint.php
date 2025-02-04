<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for entity types that are not supported by Workspaces.
 *
 * @Constraint(
 *   id = "WseUnsupportedEntityType",
 *   label = @Translation("Unsupported Entity Type", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class WseUnsupportedEntityTypeConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'This entity can only be changed in the Live workspace.';

}
