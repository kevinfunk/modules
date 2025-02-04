<?php

namespace Drupal\wse\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangesDetectionTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the WseUnsupportedEntityType constraint.
 */
class WseUnsupportedEntityTypeConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use EntityChangesDetectionTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly WorkspaceInformationInterface $workspaceInformation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('workspaces.manager'),
      $container->get('workspaces.information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($this->workspaceManager->hasActiveWorkspace()
        && !$this->workspaceInformation->isEntitySupported($entity)
        && $this->hasFieldsChanges($entity)) {
      $this->context->addViolation($constraint->message);
    }
  }

  /**
   * Checks whether an entity has field changes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity object.
   *
   * @return bool
   *   TRUE if fields have changes, FALSE otherwise.
   */
  protected function hasFieldsChanges(ContentEntityInterface $entity): bool {
    if ($entity->isNew()) {
      return TRUE;
    }

    $skip_fields = $this->getFieldsToSkipFromTranslationChangesCheck($entity);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
    if (isset($entity->original)) {
      $original = $entity->original;
    }
    else {
      $original = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->loadUnchanged($entity->id());
    }

    $langcode = '';
    if ($entity->isTranslatable()) {
      $langcode = $entity->language()->getId();
      $original = $original->getTranslation($langcode);
    }

    foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
      if (in_array($field_name, $skip_fields, TRUE) || $definition->isComputed()) {
        continue;
      }

      $items = $entity->get($field_name)->filterEmptyItems();
      $original_items = $original->get($field_name)->filterEmptyItems();
      if ($items->hasAffectingChanges($original_items, $langcode)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
