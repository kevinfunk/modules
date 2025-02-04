<?php

namespace Drupal\wse\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a switch to live block.
 *
 * @Block(
 *   id = "wse_switch_to_live",
 *   admin_label = @Translation("Switch To Live"),
 *   category = @Translation("Workspaces")
 * )
 */
class SwitchToLiveBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new SwitchToLiveBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    WorkspaceManagerInterface $workspace_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($this->workspaceManager->hasActiveWorkspace());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t(
        'You are viewing the %workspace workspace, switch to the <a href="@url">Live version</a> of the site.',
        [
          '%workspace' => $this->workspaceManager->getActiveWorkspace()->label(),
          '@url' => Url::fromRoute('wse.switch_to_live')->toString(),
        ]
      ),
    ];
    return $build;
  }

}
