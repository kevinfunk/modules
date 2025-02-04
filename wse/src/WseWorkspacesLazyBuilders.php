<?php

declare(strict_types=1);

namespace Drupal\wse;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workspaces\WorkspacesLazyBuilders;
use Drupal\wse\Form\WseWorkspaceSwitcherForm;

/**
 * Overrides the service for workspaces #lazy_builder callbacks.
 *
 * @internal
 */
final class WseWorkspacesLazyBuilders implements TrustedCallbackInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly WorkspacesLazyBuilders $inner,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Lazy builder callback for rendering the workspace toolbar tab.
   *
   * @return array
   *   A render array.
   */
  public function renderToolbarTab(): array {
    $build = $this->inner->renderToolbarTab();

    $wse_settings = $this->configFactory->get('wse.settings');
    if ($wse_settings->get('simplified_toolbar_switcher')) {
      $build['#attributes'] = [
        'title' => $this->t('Switch workspace'),
        'class' => [
          'toolbar-item',
          'toolbar-icon',
          'toolbar-icon-workspace',
        ],
      ];
      $build['switcher'] = [
        '#heading' => $this->t('Workspace switcher'),
        'form' => $this->formBuilder->getForm(WseWorkspaceSwitcherForm::class),
      ];
    }

    return $build;
  }

  /**
   * Render callback for the workspace toolbar tab.
   */
  public static function removeTabAttributes(array $element): array {
    unset($element['tab']['#attributes']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['removeTabAttributes', 'renderToolbarTab'];
  }

}
