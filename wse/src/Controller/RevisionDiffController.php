<?php

namespace Drupal\wse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\diff\DiffLayoutManager;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for the workspace revision diff.
 */
class RevisionDiffController extends ControllerBase {

  /**
   * The workspaces manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field diff layout plugin manager service.
   *
   * @var \Drupal\diff\DiffLayoutManager
   */
  protected $diffLayoutManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\diff\DiffLayoutManager $diff_layout_manager
   *   The diff layout service.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager, DiffLayoutManager $diff_layout_manager) {
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->diffLayoutManager = $diff_layout_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.diff.layout')
    );
  }

  /**
   * Returns a table which shows the differences between two entity revisions.
   */
  public function getRevisionDiff(?RouteMatchInterface $route_match = NULL, ?WorkspaceInterface $source_workspace = NULL) {
    $entity = $route_match->getParameter($route_match->getParameter('entity_type_id'));
    $workspace = $source_workspace;

    $left_revision = $this->workspaceManager->executeOutsideWorkspace(function () use ($entity) {
      $revision = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
      // Instantiate the entity's field values, including computed ones like
      // 'path' so the diff engine can use the proper values.
      $revision->toArray();
      return $revision;
    });
    $right_revision = $this->workspaceManager->executeInWorkspace($workspace->id(), function () use ($entity) {
      $revision = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
      // Instantiate the entity's field values, including computed ones like
      // 'path' so the diff engine can use the proper values.
      $revision->toArray();
      return $revision;
    });

    // Build the diff comparison with the plugin.
    $build = [
      '#title' => $this->t('Changes to: %label', ['%label' => $left_revision->label()]),
    ];
    if ($plugin = $this->diffLayoutManager->createInstance('wse_unified_fields')) {
      $build = array_merge_recursive($build, $plugin->build($left_revision, $right_revision, $entity));
      $build['diff']['#prefix'] = '<div class="diff-responsive-table-wrapper">';
      $build['diff']['#suffix'] = '</div>';
      $build['diff']['#attributes']['class'][] = 'diff-responsive-table';

      $build['header']['#access'] = FALSE;
      $build['controls']['#access'] = FALSE;
      $build['#attached']['library'][] = 'diff/diff.general';
    }

    return $build;
  }

}
