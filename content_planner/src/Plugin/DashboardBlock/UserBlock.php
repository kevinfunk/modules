<?php

namespace Drupal\content_planner\Plugin\DashboardBlock;

use Drupal\content_planner\DashboardBlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a user block for Content Planner Dashboard.
 *
 * @DashboardBlock(
 *   id = "user_block",
 *   name = @Translation("User Widget")
 * )
 */
class UserBlock extends DashboardBlockBase {

  use StringTranslationTrait;

  /**
   * The user profile image service.
   *
   * @var \Drupal\content_planner\UserProfileImage
   */
  protected $userProfileImage;

  /**
   * The content moderation service.
   *
   * @var \Drupal\content_planner\ContentModerationService
   */
  protected $contentModerationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userProfileImage = $container->get('content_planner.user_profile_image');
    $instance->contentModerationService = $container->get('content_planner.content_moderation_service');

    return $instance;
  }

  /**
   * Builds the render array for a dashboard block.
   *
   * @return array
   *   The markup for the dashboard block.
   */
  public function build() {
    if (!$this->currentUserHasRole()) {
      return [];
    }

    $config = $this->getConfiguration();
    $users = $this->getUsers($config);

    if (!$users) {
      return [];
    }

    /** @var \Drupal\workflows\WorkflowInterface[] $workflows */
    $workflows = array_filter(
      Workflow::loadMultiple(),
      function (WorkflowInterface $workflow) {
        return $this->contentModerationService->isValidContentModerationWorkflow($workflow);
      }
    );

    $userData = [];
    foreach ($users as $user) {
      $userWorkflows = array_map(
        function (WorkflowInterface $workflow) use ($user) {
          $type = $workflow->getTypePlugin();
          $count = $this->getUserContentCountByWorkflowAndStates($user->id(), $workflow->id());

          if ($count === 0) {
            return NULL;
          }

          return [
            'label' => $this->formatPlural(
              $count,
              '@count contribution for %workflowName',
              '@count contributions for %workflowName',
              ['%workflowName' => $workflow->label()],
              ['context' => 'Content planner user dashboard widget']
            ),
            'states' => array_map(
              function (string $stateId) use ($type, $user, $workflow) {
                return $this->formatPlural(
                  $this->getUserContentCountByWorkflowAndStates($user->id(), $workflow->id(), $stateId),
                  '@count in %stateName',
                  '@count in %stateName',
                  ['%stateName' => $type->getState($stateId)->label()],
                  ['context' => 'Content planner user dashboard widget']
                );
              },
              $type->getRequiredStates()
            ),
          ];
        },
        $workflows
      );

      $userData[] = [
        'name' => $user->label(),
        'image' => $this->userProfileImage->generateProfileImageUrl($user, 'content_planner_user_block_profile_image'),
        'roles' => implode(', ', $this->getUserRoles($user)),
        'workflows' => array_filter($userWorkflows),
      ];
    }

    return [
      '#theme' => 'content_planner_dashboard_user_block',
      '#users' => $userData,
    ];
  }

  /**
   * Gets the content for a given user by workflow id and state id.
   *
   * @param int $userId
   *   The id of the user.
   * @param string $workflowId
   *   The id of the workflow.
   * @param null|string $stateId
   *   The state id, by default is optional.
   *
   * @return int
   *   Returns the number of the data in the specific state and workflow.
   */
  public function getUserContentCountByWorkflowAndStates($userId, $workflowId, $stateId = NULL) {
    $query = $this->database->select('content_moderation_state_field_data', 'cm');
    $query->addField('cm', 'id');
    $query->condition('cm.uid', $userId);
    $query->condition('cm.workflow', $workflowId);
    if (!empty($stateId)) {
      $query->condition('cm.moderation_state', $stateId);
    }
    $count_query = $query->countQuery();
    $result = $count_query->execute()->fetchObject();

    return !empty($result->expression) ? $result->expression : 0;
  }

  /**
   * Gets the users with specific roles.
   *
   * @param array $config
   *   An array containing the config data.
   *
   * @return \Drupal\user\UserInterface[]
   *   Returns the user entity, otherwise an empty array.
   */
  protected function getUsers(array $config) {
    $configuredRoles = array_filter($config['plugin_specific_config']['roles'] ?? []);
    $displayBlocked = $config['plugin_specific_config']['blocked'] ?? TRUE;

    $storage = $this->entityTypeManager->getStorage('user');
    $query = $storage->getQuery();
    if ($configuredRoles !== []) {
      $query->condition('roles', array_values($configuredRoles), 'IN');
    }
    $query->sort('access', 'desc');
    if (!$displayBlocked) {
      $query->condition('status', 1);
    }
    $query->accessCheck();

    $result = $query->execute();
    if ($result === []) {
      return [];
    }

    return $storage->loadMultiple($result);
  }

  /**
   * Get roles for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   Returns an array containing the roles for that user.
   */
  protected function getUserRoles(UserInterface $user) {
    $storage = $this->entityTypeManager->getStorage('user_role');
    $roles = [];

    foreach ($user->getRoles(TRUE) as $role_id) {
      $role = $storage->load($role_id);
      if ($role === NULL) {
        continue;
      }

      $roles[] = $role->label();
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(
    FormStateInterface &$form_state,
    Request &$request,
    array $block_configuration
  ) {
    $form = [];

    $form['roles'] = $this->buildRoleSelectBox($block_configuration);

    $form['blocked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display blocked users'),
      '#default_value' => $block_configuration['plugin_specific_config']['blocked'] ?? TRUE,
    ];

    $form['allowed_roles'] = $this->buildAllowedRolesSelectBox($block_configuration);

    return $form;
  }

  /**
   * Build role select box.
   *
   * @param array $block_configuration
   *   An array containing the user widget configuration.
   *
   * @return array
   *   Returns an array with the form item.
   */
  protected function buildRoleSelectBox(array $block_configuration) {
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    $options = [];
    foreach ($roles as $id => $role) {
      if ($id == 'anonymous') {
        continue;
      }

      $options[$id] = $role->label();
    }

    return [
      '#type' => 'checkboxes',
      '#title' => $this->t('Which roles to display'),
      '#description' => $this->t('The user roles that should be displayed in the widget. Leave blank to display all roles.'),
      '#options' => $options,
      '#default_value' => $block_configuration['plugin_specific_config']['roles'] ?? [],
    ];
  }

}
