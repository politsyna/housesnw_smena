<?php

namespace Drupal\node_smena\Controller;

/**
 * @file
 * Contains \Drupal\node_orders\Controller\Page.
 */
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for page example routes.
 */
class KubaturaSum extends ControllerBase {

  /**
   * Page Callback.
   */
  public static function kubatura($node) {
    if (method_exists($node, 'getType') && $node->getType() == 'smena') {
      $kolvo_sum = 0;
      foreach ($node->field_smena_ref_vyhod_pilom as $entity_reference_item) {
        $node_pilom = $entity_reference_item->entity;
        $kolvo = $node_pilom->field_pilom_kubatura->value;
        $kolvo_sum = $kolvo_sum + $kolvo;
      }
      $node->field_smena_kubatura_sum->setValue($kolvo_sum);
      return $kolvo_sum;
    }

  }

}
