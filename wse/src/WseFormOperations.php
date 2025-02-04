<?php

namespace Drupal\wse;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to form operations.
 *
 * @internal
 */
class WseFormOperations implements ContainerInjectionInterface {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Collects altered form ids to pass them down to the form submit dialog JS.
   *
   * @var array
   */
  protected static array $alteredFormIds = [];

  /**
   * Constructs a new WseFormOperations instance.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, ConfigFactoryInterface $config_factory) {
    $this->workspaceManager = $workspace_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Alters forms to add confirm modal for submission in non-default workspaces.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form ID.
   *
   * @see wse_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // No alterations are needed if we're not in a workspace context.
    if (!$this->workspaceManager->hasActiveWorkspace() || $form_state->get('workspace_safe')) {
      return;
    }

    // @todo Fix in core.
    $safe_form_ids = [
      'node_revision_revert_confirm',
      'node_revision_delete_confirm',
    ];
    // Allow a few forms that we know are safe to submit.
    if (in_array($form_id, $safe_form_ids, TRUE)) {
      $form_state->set('workspace_safe', TRUE);
      return;
    }

    $ignored_forms = $this->configFactory->get('wse.settings')->get('safe_forms') ?? [];
    if (!in_array($form_id, $ignored_forms)) {
      // The alterations here make core's validation obsolete, so remove it.
      $this->removeWorkspaceValidation($form);

      // Add a form element that will be used by WseWorkspaceManager to disable
      // any active workspace for the duration of the form submission request.
      $form['wse_bypass_workspace'] = [
        '#type' => 'hidden',
        '#value' => 1,
        '#name' => 'wse_bypass_workspace',
        // Form processing and validation require this value. Ensure the
        // submitted form value appears literally, regardless of custom #tree
        // and #parents being set elsewhere.
        '#parents' => ['wse_bypass_workspace'],
      ];

      // The confirm submission dialog is implemented on the client side.
      $form['#attached']['library'][] = 'wse/form-submit-dialog';
      static::$alteredFormIds[] = Html::getId($form_id);
      $form['#attached']['drupalSettings']['wseSubmitDialog']['formSelectors'] = static::$alteredFormIds;
    }
  }

  /**
   * Removes workspaces core's validation handler recursively on each element.
   *
   * @param array &$element
   *   An associative array containing the structure of the form.
   */
  protected function removeWorkspaceValidation(array &$element) {
    // Recurse through all children and add our validation handler if needed.
    foreach (Element::children($element) as $key) {
      if (isset($element[$key]) && $element[$key]) {
        $this->removeWorkspaceValidation($element[$key]);
      }
    }

    if (isset($element['#validate'])) {
      foreach ($element['#validate'] as $key => $validation_callback) {
        if (is_array($validation_callback) && in_array('validateDefaultWorkspace', $validation_callback)) {
          unset($element['#validate'][$key]);
          return;
        }
      }
    }
  }

}
