<?php

namespace Drupal\wse\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceAccessException;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form that activates a different workspace.
 */
class WseWorkspaceSwitcherForm extends FormBase implements WorkspaceSafeFormInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly WorkspaceManagerInterface $workspaceManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wse_workspace_switcher_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $active_workspace = $this->workspaceManager->hasActiveWorkspace() ? $this->workspaceManager->getActiveWorkspace() : NULL;
    $form['workspace_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Current workspace'),
      '#options' => $this->getWorkspaceOptions(),
      '#default_value' => $active_workspace ? $active_workspace->id() : '',
      '#attributes' => ['onchange' => 'this.form.submit();'],
    ];

    $operations = [];
    if ($active_workspace && $active_workspace->access('view')) {
      $operations['workspace_action'] = [
        'title' => $this->t('Manage workspace'),
        'url' => $active_workspace->toUrl(),
        'attributes' => [
          'class' => ['wse-action-link', 'wse-action-link--icon-cog'],
          'title' => $this->t('Manage workspace'),
        ],
      ];
    }
    elseif ($this->entityTypeManager->getAccessControlHandler('workspace')->createAccess()) {
      $operations['workspace_action'] = [
        'title' => $this->t('Add new workspace'),
        'url' => Url::fromRoute('entity.workspace.add_form'),
        'options' => ['query' => $this->getRedirectDestination()->getAsArray()],
        'attributes' => [
          'class' => ['wse-action-link', 'wse-action-link--icon-plus', 'use-ajax'],
          'title' => $this->t('Add new workspace'),
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
          ]),
        ],
      ];
    }

    $collection_url = Url::fromRoute('entity.workspace.collection');
    if ($collection_url->access()) {
      $operations['workspace_list'] = [
        'title' => $this->t('View all workspaces'),
        'url' => $collection_url,
        'attributes' => [
          'class' => ['wse-action-link', 'wse-action-link--icon-list'],
          'title' => $this->t('View all workspaces'),
        ],
      ];
    }
    $form['operations'] = [
      '#theme' => 'links__wse_action_links',
      '#links' => $operations,
      '#attributes' => [
        'class' => ['operations', 'clearfix', 'wse-action-links'],
      ],
    ];

    if ($active_workspace) {
      $status_label = wse_workspace_get_status($active_workspace);
    }
    else {
      $status_label = $this->t('Live');
    }
    $form['workspace_status'] = [
      '#type' => 'item',
      '#markup' => $this->t('Status: @status', [
        '@status' => $status_label,
      ]),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Activate'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['js-hide']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('workspace_id');

    try {
      if ($id) {
        /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
        $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);

        $this->workspaceManager->setActiveWorkspace($workspace);
        $this->messenger()->addMessage($this->t('%workspace_label is now the active workspace.', ['%workspace_label' => $workspace->label()]));
      }
      else {
        $this->workspaceManager->switchToLive();
        $this->messenger()->addMessage($this->t('You are now viewing the live version of the site.'));
      }
    }
    catch (WorkspaceAccessException $e) {
      $this->messenger()->addError($this->t('You do not have access to activate the %workspace_label workspace.', ['%workspace_label' => $workspace->label()]));
    }
  }

  /**
   * Collects options for the workspace switcher widget.
   *
   * @return array
   *   The grouped workspace options, keyed by workspace ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getWorkspaceOptions() {
    $recent = (string) $this->t('Recent workspaces');
    $my_open = (string) $this->t('My open workspaces');
    $other_open = (string) $this->t('Other workspaces');
    $workspace_options = [
      '' => $this->t('Live'),
      $recent => [],
      $my_open => [],
      $other_open => [],
    ];

    $max_options = $this->config('wse.settings')->get('switcher_max_options') ?: NULL;
    $storage = $this->entityTypeManager->getStorage('workspace');

    $recent_workspaces = $this->getRecentWorkspaces($max_options);
    if ($recent_workspaces) {
      $workspace_options[$recent] = $recent_workspaces;

      // Return early if we've reached the limit of workspaces to display.
      if (count($recent_workspaces) == $max_options) {
        return array_filter($workspace_options);
      }
    }

    // Prepare a query that loads only open workspaces.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', WSE_STATUS_OPEN, '=');

    // Don't include recent workspaces in the results.
    if ($recent_workspaces) {
      $query->condition('id', array_keys($recent_workspaces), 'NOT IN');
    }

    // Limit the number of results if a maximum is set.
    if (!empty($max_options)) {
      $limit = $max_options - count($recent_workspaces);
      $query->range(0, $limit);
    }
    $result = $query->sort('changed', 'DESC')->execute();

    if ($result) {
      $workspaces = $storage->loadMultiple($result);
      foreach ($workspaces as $workspace_id => $workspace) {
        if ($workspace->getOwnerId() == $this->currentUser()->id()) {
          $workspace_options[$my_open][$workspace_id] = $workspace->label();
        }
        elseif ($workspace->access('view')) {
          $workspace_options[$other_open][$workspace_id] = $workspace->label();
        }
      }
    }

    return array_filter($workspace_options);
  }

  /**
   * Collects recently used workspaces, omitting closed ones.
   *
   * @param int $max_options
   *   (optional) The maximum number of workspace options to return. Defaults to
   *   NULL, which means all recent workspaces are returned.
   *
   * @return array
   *   An array of workspace labels, keyed by workspace ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function getRecentWorkspaces($max_options = NULL) {
    $recent_ids = [];
    $options = [];

    // Add the user's recently opened workspaces to the list.
    $recent_workspaces = $this->tempStoreFactory->get('wse')->get('recent_workspaces');
    if ($recent_workspaces) {
      $recent_max_age = $this->config('wse.settings')->get('recent_workspaces_max_age') * 3600;
      foreach ($recent_workspaces as $id => $timestamp) {
        $expiration_time = $timestamp + $recent_max_age;
        // If a maximum time for expiration is configured and the workspace
        // wasn't accessed since the last access + expiration time, remove it.
        if ($recent_max_age > 0 && $expiration_time < $this->time->getRequestTime()) {
          unset($recent_workspaces[$id]);
        }
        else {
          $recent_ids[] = $id;
        }
      }

      if (!empty($max_options)) {
        $recent_ids = array_slice($recent_ids, 0, $max_options);
      }

      if ($recent_ids) {
        $workspaces = $this->entityTypeManager->getStorage('workspace')->loadMultiple($recent_ids);
        foreach ($workspaces as $id => $workspace) {
          if (wse_workspace_get_status($workspace) === WSE_STATUS_OPEN) {
            $options[$workspace->id()] = $workspace->label();
          }
          else {
            unset($recent_workspaces[$id]);
          }
        }
      }

      // Update the stored recent workspaces after possible removal done above.
      $this->tempStoreFactory->get('wse')->set('recent_workspaces', $recent_workspaces);
    }

    return $options;
  }

}
