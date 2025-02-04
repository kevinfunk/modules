<?php

/**
 * @file
 * Post update functions for wse_config.
 */

/**
 * Update a few fields for the wse_config entity type.
 */
function wse_config_post_update_update_base_fields(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('wse_config');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('wse_config');

  // Update a couple field storage definitions.
  $field_storage_definitions['collection']->setRevisionable(TRUE);
  $field_storage_definitions['collection']->setTranslatable(TRUE);

  $field_storage_definitions['data']->setRevisionable(TRUE);
  $field_storage_definitions['data']->setTranslatable(TRUE);

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  return t('The "collection" and "data" fields have been updated.');
}
