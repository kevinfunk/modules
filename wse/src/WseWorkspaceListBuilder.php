<?php

namespace Drupal\wse;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overrides the workspace list builder.
 */
class WseWorkspaceListBuilder extends WorkspaceListBuilder implements FormInterface, WorkspaceSafeFormInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->formBuilder = $container->get('form_builder');
    $instance->requestStack = $container->get('request_stack');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $status = $this->requestStack->getCurrentRequest()->query->get('status');
    $form['#attributes']['class'][] = 'container-inline';
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        WSE_STATUS_OPEN => $this->t('Open'),
        WSE_STATUS_CLOSED => $this->t('Closed'),
      ],
      '#default_value' => $status,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#attributes' => ['style' => 'margin-left: 10px;'],
    ];
    if ($status) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::reset'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = parent::buildHeader();

    $header['updated'] = $this->t('Updated');

    $operations = $header['operations'];
    unset($header['operations']);
    $header['operations'] = $operations;

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);

    $row['data']['updated'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');

    $operations = $row['data']['operations'];
    unset($row['data']['operations']);
    $row['data']['operations'] = $operations;

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    if (isset($build['table'])) {
      // Always hide the 'Live' row.
      if (!$this->isAjax()) {
        unset($build['table']['#rows'][0]);
      }

      $build['filter'] = $this->formBuilder->getForm($this) + [
        '#weight' => -10,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $status = $form_state->getValue('status');
    $url = new Url('<current>', [], [
      'query' => [
        'status' => $status,
      ],
    ]);

    $form_state->setRedirectUrl($url);
  }

  /**
   * Form submission handler for the 'reset' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function reset(array $form, FormStateInterface $form_state) {
    $url = new Url('<current>');

    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    $this->filterByStatus($entities);

    return $entities;
  }

  /**
   * Filters entities by the status query parameter.
   */
  protected function filterByStatus(&$entities) {
    $status = $this->requestStack->getCurrentRequest()->query->get('status', WSE_STATUS_OPEN);

    // Filter workspaces to display only those that have a certain status.
    $entities = array_filter($entities, function ($workspace) use ($status) {
      $workspace_status = wse_workspace_get_status($workspace);
      return !$workspace_status || $workspace_status === $status;
    });

    // Allow other modules to alter the list of workspaces.
    $this->moduleHandler()->alter('wse_workspace_list_builder_entities', $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (wse_workspace_get_status($entity) === WSE_STATUS_CLOSED) {
      // Closed workspaces can not be switched into or published anymore.
      unset($operations['activate'], $operations['publish']);

      // Add the 'revert' operation for the last published workspace.
      if (\Drupal::service('wse.published_revision_storage')->getLastPublishedWorkspaceId() === $entity->id()) {
        $operations['revert'] = [
          'title' => $this->t('Revert'),
          'weight' => -1,
          'url' => Url::fromRoute('entity.workspace.revert_form',
            ['workspace' => $entity->id()],
            ['query' => ['destination' => $entity->toUrl('collection')->toString()]]
          ),
        ];
      }
    }

    return $operations;
  }

}
