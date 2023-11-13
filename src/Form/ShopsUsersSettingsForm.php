<?php

namespace Drupal\shops_users\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ip_login settings.
 */
class ShopsUsersSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shops_users_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shops_users.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('shops_users.settings');

    $form['xml_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('XML path'),
      '#description' => $this->t('Location to the Shops Users XML (verdeling).'),
      '#default_value' => $config->get('xml_uri'),
      '#required' => TRUE,
    ];

    $form['email_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email domain'),
      '#description' => $this->t('The domain to put in the users email addresses'),
      '#field_prefix' => '@',
      '#default_value' => $config->get('email_domain'),
      '#required' => TRUE,
      '#size' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('shops_users.settings');
    $form_state->cleanValues();

    $config->set('xml_uri', $form_state->getValue('xml_uri'));
    $config->set('email_domain', $form_state->getValue('email_domain'));

    $config->save();
  }

}
