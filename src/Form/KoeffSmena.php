<?php

namespace Drupal\node_smena\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * SimpleForm.
 */
class KoeffSmena extends FormBase {

  /**
   * F ajaxModeDev.
   */
  public function ajaxKoeffSmena(array &$form, &$form_state) {
    $response = new AjaxResponse();
    $uid = \Drupal::currentUser()->id();
    $user = User::load($uid);
    if ($user->hasPermission('smena-form')) {
      $nid = $form_state->getValue('smena');
      $node = Node::load($nid);
      $get_values = $form_state->getValues();
      $num = $node->id();
      $people = $node->field_smena_ref_team;
      $cost_sum = $node->field_smena_cost_sum->value;
      $proc = 0;
      foreach ($people as $key => $value) {
        $node_people = $value->entity;
        $nid = $node_people->id();
        $form_key1 = 'team1-' . $nid;
        $procent = str_replace(',', '.', $get_values[$form_key1]);
        $proc = $proc + $procent;
      }
      if ($proc <= 100) {
        foreach ($people as $key => $value) {
          $node_people = $value->entity;
          $nid = $node_people->id();
          $form_key1 = 'team1-' . $nid;
          $procent = str_replace(',', '.', $get_values[$form_key1]);
          $sum_procent = $get_values[$form_key1];
          $form_key2 = 'team2-' . $nid;
          $shtraf = str_replace(',', '.', $get_values[$form_key2]);
          $zplata = ($cost_sum * $procent / 100) - $shtraf;
          $query = \Drupal::entityQuery('node');
          $query->condition('status', 1);
          $query->condition('type', 'oplata');
          $query->condition('field_oplata_ref_smena', $num);
          $query->condition('field_oplata_ref_team', $nid);
          $entity_ids = $query->execute();
          if (empty($entity_ids)) {
            $source = [
              'type' => 'oplata',
              'title' => 'title',
              'field_oplata_ref_smena' => $num,
              'field_oplata_ref_team' => $nid,
              'field_oplata_procent' => $procent,
              'field_oplata_shtraf' => $shtraf,
              'field_oplata' => $zplata,
              'uid' => \Drupal::currentUser()->id(),
            ];
            $node_oplata = Node::create($source);
            $node_oplata->save();
          }
          else {
            $money = Node::loadMultiple($entity_ids);
            $k = 0;
            foreach ($money as $key => $node_oplata) {
              if ($k == 0) {
                $node_oplata->field_oplata_procent->setValue($procent);
                $node_oplata->field_oplata_shtraf->setValue($shtraf);
                $node_oplata->field_oplata->setValue($zplata);
              }
              else {
                $node_oplata->setPublished(FALSE);
              }
              $node_oplata->save();
              $k++;
            }
          }
        }
        $response->addCommand(new HtmlCommand("#button-koeff-smena-form .form-actions",
        "З/плата работникам смены рассчитана"));
        $response->addCommand(new RedirectCommand('/node/' . $node->id()));
        $node->save();
      }
      else {
        $response->addCommand(new HtmlCommand("#button-koeff-smena-form .form-actions",
        "Суммарный процент участия работников не должен быть более 100%"));
      }
    }
    else {
      $response->addCommand(new HtmlCommand("#button-koeff-smena-form .form-actions",
      "Доступ запрещен"));
    }
    return $response;
  }

  /**
   * Build the simple form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $extra = NULL) {
    $node = $extra;
    $form_state->node_smena = $node;
    $form_state->setCached(FALSE);
    $form['smena'] = [
      '#type' => 'hidden',
      '#title' => 'номер распиловки: ',
      "#default_value" => $node->id(),
    ];
    foreach ($node->field_smena_ref_team as $key => $value) {
      $people = $value->entity->title->value;
      $num = $node->id();
      $id = $value->entity->id();
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', 'oplata');
      $query->condition('field_oplata_ref_smena', $num);
      $query->condition('field_oplata_ref_team', $id);
      $entity_ids = $query->execute();
      $array_oplata = Node::loadMultiple($entity_ids);
      $node_oplata = array_shift($array_oplata);
      if (is_object($node_oplata)) {
        $procent = $node_oplata->field_oplata_procent->value;
        $shtraf = $node_oplata->field_oplata_shtraf->value;
      }
      else {
        $procent = 0;
        $shtraf = 0;
      }
      $form['team-' . $id] = [
        '#type' => 'label',
        '#title' => 'Работник: ' . $people,
      ];
      $form['team1-' . $id] = [
        '#type' => 'textfield',
        '#title' => 'коэффициент участия (%): ',
        '#default_value' => $procent,
        "#prefix" => '<div class="row"><div class="col-md-6">',
        "#suffix" => '</div>',
      ];
      $form['team2-' . $id] = [
        '#type' => 'textfield',
        '#title' => 'штраф работника: ',
        '#default_value' => number_format($shtraf, 0, ",", " "),
        "#prefix" => '<div class="col-md-6">',
        "#suffix" => '</div></div>',
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Рассчитать з/плату работникам',
      '#attributes' => ['class' => ['btn', 'btn-xs', 'btn-danger']],
      '#ajax' => [
        'callback' => '::ajaxKoeffSmena',
        'effect' => 'fade',
        'progress' => ['type' => 'throbber', 'message' => ""],
      ],
    ];
    return $form;
  }

  /**
   * Getter method for Form ID.
   */
  public function getFormId() {
    return 'button_koeff_smena_form';
  }

  /**
   * Implements a form submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

}
