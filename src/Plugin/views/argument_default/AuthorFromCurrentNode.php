<?php

namespace Drupal\shops_users\Plugin\views\argument_default;

use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Filter the nodes based on the node author.
 *
 * @ViewsArgumentDefault(
 *   id = "author_from_current_node",
 *   title = @Translation("Author from current node")
 * )
 */
class AuthorFromCurrentNode extends ArgumentDefaultPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    return \Drupal::currentUser()->id();
  }

}
