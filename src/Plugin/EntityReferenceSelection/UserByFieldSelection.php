<?php

namespace Drupal\shops_users\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:user_by_field",
 *   label = @Translation("User by field selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 3
 * )
 */
class UserByFieldSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $handler_settings = $this->getConfiguration();
    if (!isset($handler_settings['filter_field'])) {
      return $query;
    }
    $filter_settings = $handler_settings['filter_field'];
    foreach ($filter_settings as $field_name => $value) {
      if (is_array($value)) {
        $orCondition = $query->orConditionGroup();
        foreach ($value as $v) {
          $orCondition->condition($field_name, $v, '=');
        }
        $query->condition($orCondition);
      }
      else {
        $query->condition($field_name, $value, '=');
      }
    }
    return $query;
  }

}
