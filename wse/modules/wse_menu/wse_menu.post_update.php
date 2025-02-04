<?php

/**
 * @file
 * Post update functions for Workspaces Menu.
 */

/**
 * Update the hierarchy fields to revisionable for custom menu links.
 */
function wse_menu_post_update_make_hierarchy_revisionable(&$sandbox): void {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('menu_link_content');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('menu_link_content');
  if (!$field_storage_definitions['parent']->isRevisionable()) {
    $field_storage_definitions['expanded']->setRevisionable(TRUE);
    $field_storage_definitions['parent']->setRevisionable(TRUE);
    $field_storage_definitions['weight']->setRevisionable(TRUE);

    $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
  }
}

/**
 * Empty update.
 */
function wse_menu_post_update_remove_menu_tree_definitions_for_deleted_menu_links(): void {
  // This update function has been removed.
}
