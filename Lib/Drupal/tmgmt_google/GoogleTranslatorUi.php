<?php

/**
 * @file
 * Contains \Drupal\tmgmt_google\GoogleTranslatorUi.
 */

namespace Drupal\tmgmt_google;

use Drupal\tmgmt\Plugin\Core\Entity\Translator;
use Drupal\tmgmt\TranslatorPluginUiBase;
use TMGMTDefaultTranslatorUIController;

/**
 * Google translator UI.
 */
class GoogleTranslatorUi extends TranslatorPluginUiBase {

  /**
   * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
   */
  public function pluginSettingsForm($form, &$form_state, Translator $translator, $busy = FALSE) {
    $generate_url = 'https://datamarket.azure.com/dataset/1899a118-d202-492c-aa16-ba21c33c06cb';
    $form['clientid'] = array(
      '#type' => 'textfield',
      '#title' => t('Microsoft Customer ID'),
      '#default_value' => $translator->getSetting('clientid'),
      '#description' => t('Please enter your Microsoft Customer ID, or follow this <a href="!link">link</a> to generate one.', array('!link' => $generate_url)),
    );
    $form['clientsecret'] = array(
      '#type' => 'textfield',
      '#title' => t('Primary Account Key'),
      '#default_value' => $translator->getSetting('clientsecret'),
      '#description' => t('Please enter your Microsoft Primary Account Key, or follow this <a href="!link">link</a> to generate one.', array('!link' => $generate_url)),
    );
    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

}
