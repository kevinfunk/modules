<?php

namespace Drupal\wse\PathProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for WSE.
 */
class WsePathProcessor implements OutboundPathProcessorInterface {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * A config factory for retrieving required config settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a WsePathProcessor object.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   A config factory for retrieving the site front page configuration.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, ConfigFactoryInterface $config) {
    $this->workspaceManager = $workspace_manager;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!$request) {
      return $path;
    }

    $wse_settings = $this->config->get('wse.settings');
    if (!$wse_settings->get('append_current_workspace_to_url')) {
      return $path;
    }

    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return $path;
    }

    $request_query = $request->query->all();
    if (isset($request_query['workspace']) || UrlHelper::isExternal($path)) {
      return $path;
    }

    $options['query']['workspace'] = $this->workspaceManager->getActiveWorkspace()->id();
    return $path;
  }

}
