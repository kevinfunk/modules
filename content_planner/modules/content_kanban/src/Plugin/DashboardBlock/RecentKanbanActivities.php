<?php

namespace Drupal\content_kanban\Plugin\DashboardBlock;

use Drupal\content_kanban\Entity\KanbanLog;
use Drupal\content_planner\DashboardBlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a user block for Content Planner Dashboard.
 *
 * @DashboardBlock(
 *   id = "recent_kanban_activities",
 *   name = @Translation("Recent Kanban Activities")
 * )
 */
class RecentKanbanActivities extends DashboardBlockBase {

  use StringTranslationTrait;

  /**
   * An integer representing the default query limit.
   *
   * @var int
   */
  protected $defaultLimit = 10;

  /**
   * The date formatter object.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The kanban log service.
   *
   * @var \Drupal\content_kanban\KanbanLogService
   */
  protected $kanbanLogService;

  /**
   * The kanban workflow service.
   *
   * @var \Drupal\content_kanban\KanbanWorkflowService
   */
  protected $kanbanWorkflowService;

  /**
   * The user profile image service.
   *
   * @var \Drupal\content_planner\UserProfileImage
   */
  protected $userProfileImage;

  /**
   * The Content Moderation service.
   *
   * @var \Drupal\content_planner\ContentModerationService
   */
  protected $contentModerationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->configFactory = $container->get('config.factory');
    $instance->kanbanLogService = $container->get('content_kanban.kanban_log_service');
    $instance->kanbanWorkflowService = $container->get('content_kanban.kanban_workflow_service');
    $instance->userProfileImage = $container->get('content_planner.user_profile_image');
    $instance->contentModerationService = $container->get('content_planner.content_moderation_service');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(FormStateInterface &$form_state, Request &$request, array $block_configuration) {

    $form = [];

    $limit_default_value = $this->getCustomConfigByKey($block_configuration, 'limit', $this->defaultLimit);

    // Limit.
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity'),
      '#required' => TRUE,
      '#default_value' => $limit_default_value,
    ];

    $user_picture_field_exists = !$this->configFactory->get('field.field.user.user.user_picture')->isNew();

    $show_user_thumb_default_value = $this->getCustomConfigByKey($block_configuration, 'show_user_thumb', 0);

    $form['show_user_thumb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show thumbnail image of User image'),
      '#description' => $this->t('This option is only available, if the User account has the "user_picture" field. See Account configuration.'),
      '#disabled' => !$user_picture_field_exists,
      '#default_value' => $show_user_thumb_default_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];

    // Get config.
    $config = $this->getConfiguration();

    // Get limit.
    $limit = $this->getCustomConfigByKey($config, 'limit', $this->defaultLimit);

    // Get Logs.
    if ($logs = $this->kanbanLogService->getRecentLogs($limit, ['exclude_anonymous_users' => TRUE])) {
      $entries = $this->buildKanbanLogActivities($logs);
      $build = [
        '#theme' => 'content_kanban_log_recent_activity',
        '#entries' => $entries,
        '#show_user_thumb' => $this->getCustomConfigByKey($config, 'show_user_thumb', 0),
      ];

    }

    return $build;
  }

  /**
   * Builds the log entries.
   *
   * @param array $logs
   *   An array with the logs.
   *
   * @return array
   *   Returns an array with the logs.
   */
  protected function buildKanbanLogActivities(array $logs) {

    $entries = [];

    foreach ($logs as $log) {

      // Get User object.
      $user = $log->getOwner();
      // Get Entity object.
      $entity = $log->getEntityObject();
      // If the Entity or user cannot be found, then continue with the next log.
      if (!$entity || !$user) {
        continue;
      }

      if ($message = $this->composeMessage($log, $user, $entity)) {

        $entry = [
          'user_profile_image' => $this->userProfileImage->generateProfileImageUrl($user, 'content_kanban_user_thumb'),
          'username' => $user->getAccountName(),
          'message' => $message,
        ];

        $entries[] = $entry;

      }

    }

    return $entries;
  }

  /**
   * Composes the message.
   *
   * @param \Drupal\content_kanban\Entity\KanbanLog $log
   *   The Kanban log object.
   * @param \Drupal\user\Entity\User $user
   *   The User object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return string
   *   Returns a string containing the composed message.
   */
  protected function composeMessage(KanbanLog $log, User $user, EntityInterface $entity) {

    $state_from = $log->getStateFrom();
    $state_to = $log->getStateTo();
    $workflow_states = $this->contentModerationService->getWorkflowStates($log->getWorkflow());

    if ($state_from == $state_to) {

      $message = $this->t(
        '@username has updated @entity_type "@entity" @time ago',
        [
          '@username' => $user->getAccountName(),
          '@entity' => $entity->label(),
          '@entity_type' => ucfirst($entity->getEntityTypeId()),
          '@time' => $this->calculateTimeAgo($log),
        ]
      );

    }
    else {

      $message = $this->t(
        '@username has changed the state of @entity_type "@entity" from "@state_from" to "@state_to" @time ago',
        [
          '@username' => $user->getAccountName(),
          '@entity' => $entity->label(),
          '@entity_type' => ucfirst($entity->getEntityTypeId()),
          '@time' => $this->calculateTimeAgo($log),
          '@state_from' => $workflow_states[$state_from],
          '@state_to' => $workflow_states[$state_to],
        ]
          );

    }

    return $message;
  }

  /**
   * Gets the time difference for the given log since the created time.
   *
   * @param \Drupal\content_kanban\Entity\KanbanLog $log
   *   The Kanban log object.
   *
   * @return mixed
   *   Returns the calculated time ago for the given Kanban log.
   */
  protected function calculateTimeAgo(KanbanLog $log) {
    return $this->dateFormatter->formatTimeDiffSince($log->getCreatedTime());
  }

}
