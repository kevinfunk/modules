<?php

namespace Drupal\wse_deploy;

use Drupal\workspaces\Negotiator\WorkspaceIdNegotiatorInterface;
use Drupal\workspaces\Negotiator\WorkspaceNegotiatorInterface;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a query parameter workspace negotiator for JSON:API.
 */
class JsonApiWorkspaceNegotiator implements WorkspaceNegotiatorInterface, WorkspaceIdNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return is_string($request->query->get('Workspace'));
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    return $request->query->get('Workspace');
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace() {
    // Nothing to do here.
  }

}
