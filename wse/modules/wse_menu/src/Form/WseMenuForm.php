<?php

namespace Drupal\wse_menu\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\menu_ui\MenuForm;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse_menu\WseMenuTreeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends MenuForm for workspace-specific changes.
 *
 * @internal
 */
class WseMenuForm extends MenuForm {

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
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form_state->set('workspace_safe', TRUE);
    return $form;
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
  protected function buildOverviewForm(array &$form, FormStateInterface $form_state): array {
    // Ensure that menu_overview_form_submit() knows the parents of this form
    // section.
    if (!$form_state->has('menu_overview_form_parents')) {
      $form_state->set('menu_overview_form_parents', []);
    }

    $form['#attached']['library'][] = 'menu_ui/drupal.menu_ui.adminforms';

    $tree = $this->menuTree->load($this->entity->id(), new MenuTreeParameters());

    // We indicate that a menu administrator is running the menu access check.
    $this->getRequest()->attributes->set('_menu_admin', TRUE);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $this->getRequest()->attributes->set('_menu_admin', FALSE);

    // Determine the delta; the number of weights to be made available.
    $count = function (array $tree) {
      $sum = function ($carry, MenuLinkTreeElement $item) {
        return $carry + $item->count();
      };
      return array_reduce($tree, $sum);
    };
    $delta = max($count($tree), 50);

    $form['links'] = [
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => [
        $this->t('Menu link'),
        [
          'data' => $this->t('Enabled'),
          'class' => ['checkbox'],
        ],
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ],
      ],
      '#attributes' => [
        'id' => 'menu-overview',
      ],
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'menu-parent',
          'subgroup' => 'menu-parent',
          'source' => 'menu-id',
          'hidden' => TRUE,
          'limit' => $this->menuTree->maxDepth() - 1,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ],
      ],
    ];

    $form['links']['#empty'] = $this->t('There are no menu links yet. <a href=":url">Add link</a>.', [
      ':url' => Url::fromRoute('entity.menu.add_link_form', ['menu' => $this->entity->id()], [
        'query' => ['destination' => $this->entity->toUrl('edit-form')->toString()],
      ])->toString(),
    ]);
    $links = $this->buildOverviewTreeForm($tree, $delta);

    foreach (Element::children($links) as $id) {
      if (isset($links[$id]['#item'])) {
        $element = $links[$id];

        $form['links'][$id]['#item'] = $element['#item'];

        // TableDrag: Mark the table row as draggable.
        $form['links'][$id]['#attributes'] = $element['#attributes'];
        $form['links'][$id]['#attributes']['class'][] = 'draggable';

        // TableDrag: Sort the table row according to its existing/configured
        // weight.
        $form['links'][$id]['#weight'] = $element['#item']->link->getWeight();

        // Add special classes to be used for tabledrag.js.
        $element['parent']['#attributes']['class'] = ['menu-parent'];
        $element['weight']['#attributes']['class'] = ['menu-weight'];
        $element['id']['#attributes']['class'] = ['menu-id'];

        $form['links'][$id]['title'] = [
          [
            '#theme' => 'indentation',
            '#size' => $element['#item']->depth - 1,
          ],
          $element['title'],
        ];
        $form['links'][$id]['enabled'] = $element['enabled'];
        $form['links'][$id]['enabled']['#wrapper_attributes']['class'] = ['checkbox', 'menu-enabled'];

        $form['links'][$id]['weight'] = $element['weight'];

        // Operations (dropbutton) column.
        $form['links'][$id]['operations'] = $element['operations'];

        $form['links'][$id]['id'] = $element['id'];
        $form['links'][$id]['parent'] = $element['parent'];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $this->validateLinks($form_state);
  }

}
