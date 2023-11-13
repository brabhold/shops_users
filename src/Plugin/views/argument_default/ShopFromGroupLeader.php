<?php

namespace Drupal\shops_users\Plugin\views\argument_default;

use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Filter shops nodes to get the ones whos current user is the group leader.
 *
 * @ViewsArgumentDefault(
 *   id = "shop_from_group_leader",
 *   title = @Translation("Shop from group leader")
 * )
 */
class ShopFromGroupLeader extends ArgumentDefaultPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $currentUser = \Drupal::currentUser()->id();
    $ids = \Drupal::service('shops_users.manager')->getUserByGroupLeader($currentUser);
    return implode('+', $ids);
  }

}
