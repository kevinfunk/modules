<?php

namespace Drupal\wse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Workspace Extras routes.
 */
class SwitchToLiveController extends ControllerBase {

  /**
   * The workspaces manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspaces.manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    WorkspaceManagerInterface $workspace_manager,
    MessengerInterface $messenger,
  ) {
    $this->workspaceManager = $workspace_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Switch to Live and redirect to the previous page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to home of the live version of the site.
   */
  public function switchToLive() {
    $this->workspaceManager->switchToLive();
    $this->messenger->addMessage($this->t('You are now viewing the live version of the site.'));
    // Redirecting to the frontpage for now because redirecting to the referring
    // page would require a lazy builder for the redirect destination in order
    // to avoid poor cacheability of the "Switch to live" block.
    return new RedirectResponse(Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString());
  }

}
