<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Drupal\workspaces\Plugin\Validation\Constraint\EntityReferenceSupportedNewEntitiesConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Checks if new entities created for entity reference fields are supported.
 */
class WseEntityReferenceSupportedNewEntitiesConstraintValidator extends EntityReferenceSupportedNewEntitiesConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    // The validator should run only if we are in a active workspace context.
    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return;
    }

    $target_entity_type_id = $value->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
    $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);

    if ($value->hasNewEntity() && !$target_entity_type->isInternal() && !\Drupal::service('workspaces.information')->isEntityTypeSupported($target_entity_type)) {
      $this->context->addViolation($constraint->message, ['%collection_label' => $target_entity_type->getCollectionLabel()]);
    }
  }

}
