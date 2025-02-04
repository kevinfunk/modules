<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * The entity reference supported new entities constraint.
 *
 * @Constraint(
 *   id = "WseEntityReferenceSupportedNewEntities",
 *   label = @Translation("Entity Reference Supported New Entities", context = "Validation"),
 * )
 */
class WseEntityReferenceSupportedNewEntitiesConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = '%collection_label can only be created in the default workspace.';

}
