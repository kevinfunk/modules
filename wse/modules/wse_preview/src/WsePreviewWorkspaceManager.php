<?php

namespace Drupal\wse_preview;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse_preview\Negotiator\CookieWorkspaceNegotiator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a workspace manager service override.
 */
class WsePreviewWorkspaceManager implements WorkspaceManagerInterface {

  public function __construct(
    protected WorkspaceManagerInterface $inner,
    protected CookieWorkspaceNegotiator $cookieWorkspaceNegotiator,
    protected RequestStack $requestStack,
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
    return $this->inner->getActiveWorkspace();
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    $this->removePreviewCookie();

    return $this->inner->setActiveWorkspace($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function switchToLive() {
    $this->removePreviewCookie();

    return $this->inner->switchToLive();
  }

  /**
   * Removes the workspace preview cookie.
   */
  private function removePreviewCookie() {
    $request = $this->requestStack->getMainRequest();
    // Forcefully unset the request cookie so that the session negotiator
    // is able to set the active workspace.
    // @todo Open a core issue for making the workspace manager emit an event
    //   when switching between workspaces or to Live.
    $request->cookies->remove('wse_preview');
    $this->cookieWorkspaceNegotiator->unsetActiveWorkspace();
  }

  /**
   * {@inheritdoc}
   */
  public function executeInWorkspace($workspace_id, callable $function) {
    return $this->inner->executeInWorkspace($workspace_id, $function);
  }

  /**
   * {@inheritdoc}
   */
  public function executeOutsideWorkspace(callable $function) {
    return $this->inner->executeOutsideWorkspace($function);
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

}
