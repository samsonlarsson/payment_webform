<?php 
/**
 * @file
 * Contains \Drupal\payment_webform\Controller\DefaultController.
 */

namespace Drupal\payment_webform\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the payment_webform module.
 */
class DefaultController extends ControllerBase {

  public function payment_webform_page_finish_access(Drupal\Core\Session\AccountInterface $account) {
    return isset($_SESSION['payment_webform_pid']) && payment_webform_load_nid($_SESSION['payment_webform_pid']);
  }

  public function payment_webform_page_finish() {
    $pid = $_SESSION['payment_webform_pid'];
    unset($_SESSION['payment_webform_pid']);

    $payment = entity_load_single('payment', $pid);
    $node = node_load(payment_webform_load_nid($pid));
    drupal_set_title(node_page_title($node));
    return [
      '#type' => 'markup',
      '#markup' => t('Your payment is %status. You can now <span class="paymentreference-window-close">close this window</span>.', [
        '%status' => payment_status_info($payment->getStatus()->status, TRUE)->title
        ]),
      '#attached' => [
        'js' => [
          drupal_get_path('module', 'paymentreference') . '/js/paymentreference.js'
          ]
        ],
    ];
  }

  public function payment_webform_page_payment_access(\Drupal\node\NodeInterface $node, $cid, Drupal\Core\Session\AccountInterface $account = NULL) {
    $user = \Drupal::currentUser();

    if (!$account) {
      $account = $user;
    }

    return node_access('view', $node, $account) && isset($node->webform['components'][$cid]) && !payment_webform_load($cid, $account->uid);
  }

  public function payment_webform_page_payment(\Drupal\node\NodeInterface $node, $cid) {
    $component = $node->webform['components'][$cid];
    $payment = new Payment([
      'context' => 'payment_webform_' . $node->id() . '_' . $cid,
      'context_data' => [
        'cid' => $cid
        ],
      'currency_code' => $component['extra']['payment_currency_code'],
      'description' => $component['extra']['payment_description'],
      'finish_callback' => 'payment_webform_payment_finish',
    ]);
    foreach ($component['extra']['payment_line_items'] as $line_item) {
      $line_item->name = 'payment_webform_' . $line_item->name;
      $payment->setLineItem($line_item);
    }

    return \Drupal::formBuilder()->getForm('payment_form_standalone', $payment);
  }

}