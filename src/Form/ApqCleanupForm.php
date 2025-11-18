<?php
namespace Drupal\custom_graphql_apq_plus\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ApqCleanupForm extends FormBase {

  public function getFormId() {
    return 'custom_graphql_apq_plus_cleanup_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Remove entries not accessed in the last X days'),
      '#default_value' => 90,
      '#min' => 1,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cleanup'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $days = (int) $form_state->getValue('days');
    $seconds = $days * 86400;
    $storage = \Drupal::service('custom_graphql_apq_plus.storage');
    $removed = $storage->cleanupOlderThan($seconds);
    \Drupal::messenger()->addMessage($this->t('@n entries removed.', ['@n' => $removed]));
  }
}
