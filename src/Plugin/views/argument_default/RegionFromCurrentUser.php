<?php

namespace Drupal\shops_users\Plugin\views\argument_default;

use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Filter the nodes based on the region user.
 *
 * @ViewsArgumentDefault(
 *   id = "region_from_current_user",
 *   title = @Translation("Region from current user")
 * )
 */
class RegionFromCurrentUser extends ArgumentDefaultPluginBase {

    /**
     * {@inheritdoc}
     */
    public function getArgument() {
        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        if($user->hasField('field_region')){
            return $user->get('field_region')->getString();
        }
        return '';
    }

}
