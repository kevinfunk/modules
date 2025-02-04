<?php

namespace Drupal\wse;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a workspace manager service override.
 */
class WseWorkspaceManager implements WorkspaceManagerInterface {

  /**
   * The decorated workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $inner;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The private tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new WseWorkspaceManager.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $inner
   *   The inner workspace manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(WorkspaceManagerInterface $inner, RequestStack $request_stack, PrivateTempStoreFactory $temp_store_factory, TimeInterface $time) {
    $this->inner = $inner;
    $this->requestStack = $request_stack;
    $this->tempStoreFactory = $temp_store_factory;
    $this->time = $time;
  }

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
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->request->has('wse_bypass_workspace')) {
      return FALSE;
    }

    return $this->getActiveWorkspace() !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspace() {
    $request = $this->requestStack->getCurrentRequest();

    // When there is no request in the stack, return early and allow following
    // calls to try and determine the active workspace.
    if (!$request) {
      return FALSE;
    }

    if ($request && $request->request->has('wse_bypass_workspace')) {
      return FALSE;
    }

    // Don't allow closed workspaces to be activated.
    $negotiated_workspace = $this->inner->getActiveWorkspace();
    if ($negotiated_workspace instanceof WorkspaceInterface && wse_workspace_get_status($negotiated_workspace) == WSE_STATUS_CLOSED) {
      return FALSE;
    }

    return $negotiated_workspace;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    $return = $this->inner->setActiveWorkspace($workspace);

    // Record that we have activated this workspace.
    $temp_store = $this->tempStoreFactory->get('wse');
    $recent_workspaces = $temp_store->get('recent_workspaces') ?: [];
    $recent_workspaces[$workspace->id()] = $this->time->getRequestTime();
    $temp_store->set('recent_workspaces', $recent_workspaces);

    $this->removeWorkspaceQueryParam();

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function switchToLive() {
    $this->removeWorkspaceQueryParam();

    return $this->inner->switchToLive();
  }

  /**
   * Removes any trace of the current workspace from the query parameters.
   */
  private function removeWorkspaceQueryParam() {
    // When switching workspaces, ensure that there's no workspace query
    // parameter, either standalone or in the destination.
    $request = $this->requestStack->getCurrentRequest();
    $request->query->remove('workspace');

    $query_params = $request->query->all();
    if (isset($query_params['destination'])) {
      $destination = UrlHelper::parse($query_params['destination']);
      unset($destination['query']['workspace']);
      $new_destination = $destination['path'] . ($destination['query'] ? ('?' . UrlHelper::buildQuery($destination['query'])) : '');
      $request->query->set('destination', $new_destination);
    }
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
