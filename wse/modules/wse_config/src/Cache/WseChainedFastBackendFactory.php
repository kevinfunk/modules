<?php

namespace Drupal\wse_config\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Site\Settings;
use Drupal\wse_config\WorkspaceIdDetector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the factory for the workspace aware fast chained cache backends.
 */
class WseChainedFastBackendFactory implements CacheFactoryInterface {

  /**
   * The decorated cache backend factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $innerFactory;

  /**
   * The workspace ID detector.
   *
   * @var \Drupal\wse_config\WorkspaceIdDetector
   */
  protected $workspaceIdDetector;

  /**
   * The service name of the consistent backend factory.
   *
   * @var string
   */
  protected $consistentServiceName;

  /**
   * The service name of the fast backend factory.
   *
   * @var string
   */
  protected $fastServiceName;

  /**
   * The service container.
   */
  protected ContainerInterface $container;

  /**
   * Sets the service container.
   */
  public function setContainer(ContainerInterface $container): void {
    $this->container = $container;
  }

  /**
   * Constructs the DatabaseBackendFactory object.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $inner_factory
   *   The inner cache backend factory.
   * @param \Drupal\wse_config\WorkspaceIdDetector $workspace_id_detector
   *   The workspace ID detector.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The decorated cache backend factory.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings object.
   * @param string|null $consistent_service_name
   *   (optional) The service name of the consistent backend factory. Defaults
   *   to:
   *   - $settings->get('cache')['default'] (if specified)
   *   - 'cache.backend.database' (if the above isn't specified)
   * @param string|null $fast_service_name
   *   (optional) The service name of the fast backend factory. Defaults to:
   *   - 'cache.backend.apcu' (if the PHP process has APCu enabled)
   *   - NULL (if the PHP process doesn't have APCu enabled)
   *
   * @throws \BadMethodCallException
   */
  public function __construct(CacheFactoryInterface $inner_factory, WorkspaceIdDetector $workspace_id_detector, ContainerInterface $container, Settings $settings, $consistent_service_name = NULL, $fast_service_name = NULL) {
    $this->innerFactory = $inner_factory;
    $this->workspaceIdDetector = $workspace_id_detector;
    $this->setContainer($container);

    // Default the consistent backend to the site's default backend.
    if (!isset($consistent_service_name)) {
      $cache_settings = $settings->get('cache');
      $consistent_service_name = $cache_settings['default'] ?? 'cache.backend.database';
    }

    // Default the fast backend to APCu if it's available.
    if (!isset($fast_service_name) && function_exists('apcu_fetch')) {
      $fast_service_name = 'cache.backend.apcu';
    }

    $this->consistentServiceName = $consistent_service_name;

    // Do not use the fast chained backend during installation. In those cases,
    // we expect many cache invalidations and writes, the fast chained cache
    // backend performs badly in such a scenario.
    if (!InstallerKernel::installationAttempted()) {
      $this->fastServiceName = $fast_service_name;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    // Excluded bins just use the usual cache backend.
    $allowed_bins = ['config', 'data', 'discovery'];
    if (!in_array($bin, $allowed_bins, TRUE)) {
      return $this->innerFactory->get($bin);
    }

    // Use the chained backend only if there is a fast backend available and it
    // is not the same as the consistent backend; otherwise, just return the
    // consistent backend directly.
    if (
      isset($this->fastServiceName)
      &&
      $this->fastServiceName !== $this->consistentServiceName
    ) {
      return new WseChainedFastBackend(
        $this->container->get($this->consistentServiceName)->get($bin),
        $this->container->get($this->fastServiceName)->get($bin),
        $bin,
        $this->workspaceIdDetector
      );
    }
    else {
      return $this->container->get($this->consistentServiceName)->get($bin);
    }
  }

}
