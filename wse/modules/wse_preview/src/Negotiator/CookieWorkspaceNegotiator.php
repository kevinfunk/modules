<?php

namespace Drupal\wse_preview\Negotiator;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\workspaces\Negotiator\WorkspaceIdNegotiatorInterface;
use Drupal\workspaces\Negotiator\WorkspaceNegotiatorInterface;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the session workspace negotiator.
 */
class CookieWorkspaceNegotiator implements WorkspaceNegotiatorInterface, WorkspaceIdNegotiatorInterface, EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly RequestStack $requestStack,
    protected readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // This negotiator only applies if a 'wse_preview' cookie exists.
    return $request->cookies->has('wse_preview');
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId(Request $request): ?string {
    $preview_id = $request->cookies->get('wse_preview');
    $preview = $this->keyValueExpirableFactory->get('wse_preview')->get($preview_id);

    if (!$preview) {
      return NULL;
    }

    return $preview['workspace'];
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    // This negotiator can not set the active workspace by itself.
  }

  /**
   * {@inheritdoc}
   */
  public function unsetActiveWorkspace() {
    $this->requestStack->getMainRequest()->getSession()->set('clear_wse_preview', TRUE);
  }

  /**
   * Removes the 'wse_preview' cookie if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    if ($event->getRequest()->getSession()->has('clear_wse_preview')) {
      $event->getResponse()->headers->clearCookie('wse_preview');
      $event->getResponse()->headers->clearCookie('NO_CACHE');
      $event->getRequest()->getSession()->remove('clear_wse_preview');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

}
