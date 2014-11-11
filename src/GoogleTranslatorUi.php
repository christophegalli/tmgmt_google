<?php

/**
 * @file
 * Contains \Drupal\tmgmt_google\GoogleTranslatorUi.
 */

namespace Drupal\tmgmt_google;

use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Google translator UI.
 */
class GoogleTranslatorUi extends TranslatorPluginUiBase {

  /**
   * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
   */
  public function pluginSettingsForm(array $form, FormStateInterface $form_state, Translator $translator, $busy = FALSE) {
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Google API key'),
      '#default_value' => $translator->getSetting('api_key'),
      '#description' => t('Please enter your Google API key or visit <a href="@url">Google APIs console</a> to create new one.',
        array('@url' => 'https://code.google.com/apis/console')),
    );
    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

}
