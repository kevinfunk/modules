<?php

namespace Drupal\wse_menu;

use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * RecursiveIterator implementation that can recurse into Drupal menu trees.
 */
class MenuIterator extends \ArrayIterator implements \RecursiveIterator {

  /**
   * The menu tree array.
   */
  private array $tree;

  /**
   * Whether to append the menu item's status to the title.
   */
  private bool $appendStatus;

  /**
   * Whether to append the custom menu link ID to the title.
   */
  private bool $appendId;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $tree, bool $append_status = FALSE, bool $append_id = FALSE) {
    $this->tree = $tree;
    $this->appendStatus = $append_status;
    $this->appendId = $append_id;
    parent::__construct($tree);
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    $item = parent::current();
    $title = $item->link->getTitle();

    if ($this->appendStatus) {
      $title .= ' [' . ($item->link->isEnabled() ? 'Enabled' : 'Disabled') . ']';
    }

    if ($this->appendId && $item->link instanceof MenuLinkContent) {
      $title .= ' [' . $item->link->getEntity()->id() . ']';
    }

    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren(): ?self {
    $item = $this->tree[$this->key()];
    return new self($item->subtree);
  }

  /**
   * {@inheritdoc}
   */
  public function hasChildren(): bool {
    $item = $this->tree[$this->key()];
    return !empty($item->subtree);
  }

}
