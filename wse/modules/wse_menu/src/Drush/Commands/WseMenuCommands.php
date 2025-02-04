<?php

namespace Drupal\wse_menu\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\wse_menu\MenuIterator;
use Drupal\wse_menu\WseMenuTreeStorageInterface;
use Drush\Commands\DrushCommands;
use Graphp\Graph\Graph;
use Graphp\GraphViz\GraphViz;

/**
 * Drush commands for the wse_menu module.
 */
class WseMenuCommands extends DrushCommands {

  public function __construct(
    protected string $appRoot,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WseMenuTreeStorageInterface $menuTreeStorage,
    protected MenuLinkTreeInterface $menuLinkTree,
    protected FileSystemInterface $fileSystem,
  ) {
    parent::__construct();
  }

  /**
   * Rebuilds the menu tree for a workspace.
   *
   * @param string|null $workspace_id
   *   The workspace ID.
   * @param array $options
   *   (optional) An array of options.
   *
   * @usage wse-menu:rebuild-tree workspace_id
   *   Rebuilds the menu tree for a given workspace.
   * @usage wse-menu:rebuild-tree --all
   *   Rebuilds the menu tree for all open workspaces.
   *
   * @command wse-menu:rebuild-tree
   * @aliases wsm:rt
   *
   * @option all
   *   Rebuilds the menu tree for all open workspaces.
   */
  public function rebuildTree(?string $workspace_id = NULL, array $options = ['all' => FALSE]): void {
    if ($options['all']) {
      $workspaces = $this->entityTypeManager->getStorage('workspace')
        ->loadByProperties(['status' => WSE_STATUS_OPEN]);

      if (empty($workspaces)) {
        $this->logger()->notice(dt('There are no open workspaces.'));
        return;
      }
    }
    else {
      if (empty($workspace_id)) {
        $this->logger()->error(dt('No workspace specified. Did you intend to use the --all option?'));
        return;
      }

      $workspace = Workspace::load($workspace_id);
      if (!$workspace || wse_workspace_get_status($workspace) == WSE_STATUS_CLOSED) {
        $this->logger()->error(dt('The @id workspace does not exist or is closed.', ['@id' => $workspace_id]));
        return;
      }

      $workspaces = [$workspace_id => $workspace];
    }

    foreach ($workspaces as $workspace) {
      $this->menuTreeStorage->rebuildWorkspaceMenuTree($workspace);
      $this->logger()->success(dt('The menu tree has been rebuilt for the @label workspace.', ['@label' => $workspace->label()]));
    }
  }

  /**
   * Generates the ASCII representation of menu trees.
   *
   * @param string $menu_name
   *   The name of the menu for which to generate the text representation.
   * @param array $options
   *   (optional) An array of options.
   *
   * @usage wse-menu:dump-tree main
   *   Generates the ASCII representation of the Main menu.
   *
   * @command wse-menu:dump-tree
   * @aliases wsm:dt
   *
   * @option all
   *   Generates data for all menus.
   * @option exclude
   *   List of menus to exclude when the '--all' option is used.
   * @option workspace
   *   Generate the data in the context of a workspace.
   * @option status
   *   Appends the menu item's status to the title.
   * @option id
   *   Appends the ID to the title if the item is a custom menu link entity.
   * @option file
   *   Whether to dump the menu tree in a text file. The default location is
   *   '../menu_tree', which places the file outside the web root.
   * @option dir
   *   Saves the file in the given directory, relative to Drupal's app root.
   */
  public function generateMenuTreeAsText(
    string $menu_name = 'main',
    array $options = [
      'all' => FALSE,
      'exclude' => [
        'account',
        'admin',
        'tools',
      ],
      'workspace' => NULL,
      'status' => FALSE,
      'id' => FALSE,
      'file' => FALSE,
      'dir' => '../menu_tree',
    ],
  ): void {
    $this->generateTreeCommand('doGenerateMenuTreeAsText', $menu_name, $options);
  }

  /**
   * Generates a PNG image for a menu tree using GraphViz.
   *
   * @param string $menu_name
   *   The name of the menu for which to generate the image.
   * @param array $options
   *   (optional) An associative array of options.
   *
   * @usage wse-menu:dump-tree-image main
   *   Generates a PNG image for the Main menu.
   *
   * @command wse-menu:dump-tree-image
   * @aliases wsm:dti
   *
   * @option all
   *   Generates data for all menus.
   * @option exclude
   *   List of menus to exclude when the '--all' option is used.
   * @option workspace
   *   Generate the data in the context of a workspace.
   * @option dir
   *   Saves the file in the given directory, relative to Drupal's app root.
   */
  public function generateMenuTreeAsImage(
    string $menu_name,
    array $options = [
      'all' => FALSE,
      'exclude' => [
        'account',
        'admin',
        'tools',
      ],
      'workspace' => NULL,
      'dir' => '../menu_tree',
    ],
  ): void {
    if (!class_exists('Graphp\Graph\Graph') || !class_exists('Graphp\GraphViz\GraphViz')) {
      throw new \RuntimeException("The 'graphp/graph' and 'graphp/graphviz' packages are required to run this command.");
    }

    $this->generateTreeCommand('doGenerateMenuTreeAsImage', $menu_name, $options);
  }

  /**
   * Helper for the tree generation commands.
   *
   * @param string $function_name
   *   The function name for the output type, either 'doGenerateMenuTreeAsText'
   *   or 'doGenerateMenuTreeAsImage'.
   * @param string $menu_name
   *   The name of the menu for which to generate the tree.
   * @param array $options
   *   (optional) An array of options.
   */
  protected function generateTreeCommand(string $function_name, string $menu_name, array $options): void {
    if ($options['all']) {
      $menu_names = $this->menuTreeStorage->getMenuNames();
      $menu_names = array_diff_key($menu_names, array_flip($options['exclude']));
    }
    else {
      $menu_names = [$menu_name];
    }

    $callable = function () use ($function_name, $menu_names, $options): void {
      foreach ($menu_names as $menu_name) {
        $this->$function_name($menu_name, $options);
      }
    };

    if ($options['workspace']) {
      if ($workspace = Workspace::load($options['workspace'])) {
        $this->workspaceManager->executeInWorkspace($workspace->id(), $callable);
      }
      else {
        $this->logger()->error(dt('The @id workspace does not exist.', ['@id' => $options['workspace']]));
      }
    }
    else {
      $callable();
    }
  }

  /**
   * Generates the ASCII representation for a given menu.
   *
   * @param string $menu_name
   *   The name of the menu for which to generate the text representation.
   * @param array $options
   *   (optional) An array of options.
   */
  protected function doGenerateMenuTreeAsText(string $menu_name, array $options): void {
    $tree = $this->menuLinkTree->load($menu_name, new MenuTreeParameters());
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    $tree_iterator = new \RecursiveTreeIterator(new MenuIterator($tree, $options['status'], $options['id']));
    $file_data = implode(PHP_EOL, iterator_to_array($tree_iterator)) . PHP_EOL;

    if (!$options['file']) {
      $this->io()->section($menu_name);
      $this->io()->write($file_data);
    }
    else {
      // Store the files in a directory outside the web root.
      $target_dir = $this->appRoot . '/' . $options['dir'];
      $this->fileSystem->prepareDirectory($target_dir, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->saveData($file_data, $target_dir . '/' . $menu_name . '.txt', FileExists::Replace);
    }
  }

  /**
   * Generates a PNG image for a menu tree using GraphViz.
   *
   * @param string $menu_name
   *   The name of the menu for which to generate the image.
   * @param array $options
   *   (optional) An array of options.
   */
  protected function doGenerateMenuTreeAsImage(string $menu_name, array $options) {
    $tree = $this->menuLinkTree->load($menu_name, new MenuTreeParameters());
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'menu.default_tree_manipulators:flatten'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    $graph = new Graph();
    $graph->setAttribute('graphviz.graph.rankdir', 'LR');
    foreach ($tree as $item) {
      $key = $item->link->getPluginId();
      $$key = $graph->createVertex([
        'graphviz.label' => $item->link->getTitle(),
      ]);
      if ($parent = $item->link->getParent()) {
        $graph->createEdgeDirected($$parent, $$key);
      }
    }

    $graphviz = new GraphViz();
    $tmp_file = $graphviz->createImageFile($graph);

    // Store the files in a directory outside the web root.
    $target_dir = $this->appRoot . '/' . $options['dir'];
    $this->fileSystem->prepareDirectory($target_dir, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->move($tmp_file, $target_dir . '/' . $menu_name . '.png', FileExists::Replace);
  }

}
