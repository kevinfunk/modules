<?php

namespace Drupal\wse_config;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides a workspace manager service override.
 */
class WseConfigWorkspaceManager implements WorkspaceManagerInterface {

  public function __construct(
    protected readonly WorkspaceManagerInterface $inner,
    protected readonly WorkspaceIdDetector $idDetector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type) {
    return $this->inner->isEntityTypeSupported($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    return $this->inner->getSupportedEntityTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function hasActiveWorkspace() {
    return $this->inner->hasActiveWorkspace();
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspace() {
    $workspace = $this->inner->getActiveWorkspace();
    $this->idDetector->setActiveWorkspaceId($workspace ? $workspace->id() : NULL);

    return $workspace;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    $this->inner->setActiveWorkspace($workspace);
    $this->idDetector->setActiveWorkspaceId($workspace->id());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function switchToLive() {
    $this->inner->switchToLive();
    $this->idDetector->setActiveWorkspaceId(NULL);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function executeInWorkspace($workspace_id, callable $function) {
    $current_workspace_id = $this->idDetector->getActiveWorkspaceId();

    $this->idDetector->setActiveWorkspaceId($workspace_id);
    $return = $this->inner->executeInWorkspace($workspace_id, $function);
    $this->idDetector->setActiveWorkspaceId($current_workspace_id);

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function executeOutsideWorkspace(callable $function) {
    $current_workspace_id = $this->idDetector->getActiveWorkspaceId();

    $this->idDetector->setActiveWorkspaceId(NULL);
    $return = $this->inner->executeOutsideWorkspace($function);
    $this->idDetector->setActiveWorkspaceId($current_workspace_id);

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAlterOperations(EntityTypeInterface $entity_type) {
    return $this->inner->shouldAlterOperations($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeDeletedWorkspacesBatch() {
    return $this->inner->purgeDeletedWorkspacesBatch();
  }

  /**
   * {@inheritdoc}
   */
  public function shouldSkipPreOperations(EntityTypeInterface $entity_type) {
    return $this->inner->shouldSkipPreOperations($entity_type);
  }

}
