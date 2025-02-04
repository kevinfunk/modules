<?php

namespace Drupal\wse_preview\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactory;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Workspace Extras routes.
 */
class WsePreviewController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly KeyValueExpirableFactory $keyValueExpirableFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('keyvalue.expirable')
    );
  }

  /**
   * Activates a workspace based on the given preview ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function preview($preview_id) {
    $preview = $this->keyValueExpirableFactory->get('wse_preview')->get($preview_id);
    if (!$preview) {
      throw new AccessDeniedHttpException();
    }

    $workspace = Workspace::load($preview['workspace']);
    if (!$workspace instanceof WorkspaceInterface) {
      throw new AccessDeniedHttpException();
    }

    // Support legacy redirects containing absolute URLs.
    $destination = $preview['redirect_url'] ?: '/';
    if (UrlHelper::isExternal($destination)) {
      $parsed_url = parse_url($destination);
      $query = $parsed_url['query'] ?? '';
      $destination = $parsed_url['path'] . ($query ? '?' . $query : '');
    }
    $response = new LocalRedirectResponse($destination);
    $response->addCacheableDependency((new CacheableMetadata())->addCacheContexts(['cookies:wse_preview']));

    // Set a cookie with expiry time of 0, so it gets deleted when the browser
    // is closed.
    $response->headers->setCookie(new Cookie('wse_preview', $preview_id));

    // It is ok to store this redirect on the server side but, for better
    // security, user agents and especially proxies should not cache it.
    $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

    return $response;
  }

}
