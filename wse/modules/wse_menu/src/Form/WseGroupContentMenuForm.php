<?php

declare(strict_types=1);

namespace Drupal\wse_menu\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\group_content_menu\Form\GroupContentMenuForm;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse_menu\WseMenuTreeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends GroupContentMenuForm for workspace-specific changes.
 *
 * @internal
 */
class WseGroupContentMenuForm extends GroupContentMenuForm {

  use TrackedLinkValidationTrait;

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected WorkspaceManagerInterface $workspaceManager;

  /**
   * The WSE menu tree storage.
   *
   * @var \Drupal\wse_menu\WseMenuTreeStorageInterface
   */
  protected WseMenuTreeStorageInterface $menuTreeStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->workspaceManager = $container->get('workspaces.manager');
    $instance->menuTreeStorage = $container->get('wse_menu.tree_storage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);

    if ($this->workspaceManager->hasActiveWorkspace()) {
      $actions['rebuild_tree'] = [
        '#type' => 'submit',
        '#value' => $this->t('Rebuild menu tree'),
        '#submit' => ['::rebuildMenuTree'],
      ];
    }

    return $actions;
  }

  /**
   * Form submission handler for the 'rebuild_tree' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function rebuildMenuTree(array $form, FormStateInterface $form_state): void {
    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return;
    }

    try {
      $this->menuTreeStorage->rebuildWorkspaceMenuTree($this->workspaceManager->getActiveWorkspace());
      $this->messenger()->addStatus($this->t('The workspace menu tree has been rebuilt.'));
    }
    catch (\Exception $e) {
      Error::logException($this->logger('wse_menu'), $e);
      $this->messenger()->addError($this->t('The workspace menu tree could not be rebuilt. All errors have been logged.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): ContentEntityInterface {
    $return = parent::validateForm($form, $form_state);
    $this->validateLinks($form_state);
    return $return;
  }

}
