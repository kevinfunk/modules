<?php

namespace Drupal\content_kanban\Plugin\DashboardBlock;

use Drupal\content_planner\DashboardBlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a Dashboard block for Content Planner Dashboard.
 *
 * @DashboardBlock(
 *   id = "content_state_statistic",
 *   name = @Translation("Content Status Widget")
 * )
 */
class ContentStateStatistic extends DashboardBlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Kanban Statistic service.
   *
   * @var \Drupal\content_kanban\KanbanStatisticService
   */
  protected $kanbanStatisticService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    $instance->kanbanStatisticService = $container->get('content_kanban.kanban_statistic_service');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(FormStateInterface &$form_state, Request &$request, array $block_configuration) {

    $form = [];

    $workflow_options = [];

    // Get all workflows.
    $workflows = $this->entityTypeManager->getStorage('workflow')->loadMultiple();

    /** @var \Drupal\workflows\Entity\Workflow $workflow */
    foreach ($workflows as $workflow) {

      if ($workflow->status()) {
        $workflow_options[$workflow->id()] = $workflow->label();
      }
    }

    $form['workflow_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select workflow'),
      '#required' => TRUE,
      '#options' => $workflow_options,
      '#default_value' => $this->getCustomConfigByKey($block_configuration, 'workflow_id', ''),
    ];

    $form['allowed_roles'] = $this->buildAllowedRolesSelectBox($block_configuration);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->currentUserHasRole()) {
      return [];
    }

    // Get config.
    $config = $this->getConfiguration();

    // Get Workflow ID from config.
    $workflow_id = $this->getCustomConfigByKey($config, 'workflow_id', '');

    // Load workflow.
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($workflow_id);

    // If workflow does not exist.
    if (!$workflow) {
      $message = $this->t('Content Status Statistic: Workflow with ID @id does not exist. Block will not be shown.', ['@id' => $workflow_id]);
      $this->messenger->addError($message);
      return [];
    }

    // Get data.
    $data = $this->kanbanStatisticService->getWorkflowStateContentCounts($workflow);

    $build = [
      '#theme' => 'content_state_statistic',
      '#data' => $data,
      '#attached' => [
        'library' => ['content_kanban/content_state_statistic'],
      ],
    ];

    return $build;
  }

}
