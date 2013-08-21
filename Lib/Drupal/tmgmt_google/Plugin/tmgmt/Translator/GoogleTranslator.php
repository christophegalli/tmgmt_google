<?php

/**
 * @file
 * Contains \Drupal\tmgmt_microsoft\Plugin\tmgmt\Translator\MicrosoftTranslator.
 */

namespace Drupal\tmgmt_google\Plugin\tmgmt\Translator;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt\Annotation\TranslatorPlugin;
use Drupal\Core\Annotation\Translation;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Google translator plugin.
 *
 * @TranslatorPlugin(
 *   id = "google",
 *   label = @Translation("Google translator"),
 *   description = @Translation("Google Translator service."),
 *   ui = "Drupal\tmgmt_google\GoogleTranslatorUi"
 * )
 */
class GoogleTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Translation service URL.
   *
   * @var string
   */
  protected $translatorUrl = 'https://www.googleapis.com/language/translate/v2';

  /**
   * Name of parameter that contains source string to be translated.
   *
   * @var string
   */
  protected $qParamName = 'q';

  /**
   * Maximum supported characters.
   *
   * @var int
   */
  protected $maxCharacters = 5000;

  /**
   * Available actions for Google translator.
   *
   * @var array
   */
  protected $availableActions = array('translate', 'languages', 'detect');

  /**
   * Max number of text queries for translation sent in one request.
   *
   * @var int
   */
  protected $qChunkSize = 5;

  /**
   * Guzzle HTTP client.
   *
   * @var \Guzzle\Http\ClientInterface
   */
  protected $client;

  /**
   * Constructs a LocalActionBase object.
   *
   * @param \Guzzle\Http\ClientInterface $client
   *   The Guzzle HTTP client.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(ClientInterface $client, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $container->get('http_default_client'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::isAvailable().
   */
  public function isAvailable(Translator $translator) {
    if ($translator->getSetting('api_key')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::canTranslate().
   */
  public function canTranslate(Translator $translator, Job $job) {
    if (!parent::canTranslate($translator, $job)) {
      return FALSE;
    }
    foreach (array_filter(tmgmt_flatten_data($job->getData()), '_tmgmt_filter_data') as $value) {
      // If one of the texts in this job exceeds the max character count the job
      // can't be translated.
      if (drupal_strlen($value['#text']) > $this->maxCharacters) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::requestTranslation().
   */
  public function requestTranslation(Job $job) {
    // Pull the source data array through the job and flatten it.
    $data = array_filter(tmgmt_flatten_data($job->getData()), '_tmgmt_filter_data');

    $translation = array();
    $q = array();
    $keys_sequence = array();
    $i = 0;

    // Build Google q param and preserve initial array keys.
    foreach ($data as $key => $value) {
      $q[] = $value['#text'];
      $keys_sequence[] = $key;
    }

    try {

      // Split $q into chunks of self::qChunkSize.
      foreach (array_chunk($q, $this->qChunkSize) as $_q) {

        // Get translation from Google.
        $result = $this->googleRequestTranslation($job, $_q);

        // Collect translated texts with use of initial keys.
        foreach ($result['data']['translations'] as $translated) {
          $translation[$keys_sequence[$i]]['#text'] = $translated['translatedText'];
          $i++;
        }
      }

      // The translation job has been successfully submitted.
      $job->submitted('The translation job has been submitted.');

      // Save the translated data through the job.
      // NOTE that this line of code is reached only in case all translation
      // requests succeeded.
      $job->addTranslatedData(tmgmt_unflatten_data($translation));
    }
    catch (TMGMTGoogleException $e) {
      $job->rejected('Translation has been rejected with following error: !error',
        array('!error' => $e->getMessage()), 'error');
    }
  }

  /**
   * Helper method to do translation request.
   *
   * @param TMGMTJob $job
   * @param array|string $q
   *   Text/texts to be translated.
   *
   * @return array
   *   Userialized JSON containing translated texts.
   */
  protected function googleRequestTranslation(Job $job, $q) {
    $translator = $job->getTranslator();
    return $this->doRequest($translator, 'translate', array(
      'source' => $translator->mapToRemoteLanguage($job->source_language),
      'target' => $translator->mapToRemoteLanguage($job->target_language),
      $this->qParamName => $q,
    ), array(
      'headers' => array(
        'Content-Type' => 'text/plain',
      ),
    ));
  }



  /**
   * Overrides TMGMTDefaultTranslatorPluginController::getSupportedRemoteLanguages().
   */
  public function getSupportedRemoteLanguages(Translator $translator) {
    $languages = array();
    // Prevent access if the translator isn't configured yet.
    if (!$translator->getSetting('clientid')) {
      return $languages;
    }
    try {
      $request = $this->doRequest($translator, 'GetLanguagesForTranslate');
      if ($request) {
        $dom = new \DOMDocument;
        $dom->loadXML($request->getBody(TRUE));
        foreach ($dom->getElementsByTagName('string') as $item) {
          $languages[$item->nodeValue] = $item->nodeValue;
        }
      }
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return $languages;
    }

    return $languages;
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::getDefaultRemoteLanguagesMappings().
   */
  public function getDefaultRemoteLanguagesMappings() {
    return array(
      'zh-hans' => 'zh-CHS',
      'zh-hant' => 'zh-CHT',
    );
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::getSupportedTargetLanguages().
   */
  public function getSupportedTargetLanguages(Translator $translator, $source_language) {

    $remote_languages = $this->getSupportedRemoteLanguages($translator);
    $languages = array();

    foreach ($remote_languages as $remote_language) {
      $local_language = $translator->mapToLocalLanguage($remote_language);
      $languages[$local_language] = $local_language;
    }

    // Check if the source language is available.
    if (array_key_exists($source_language, $languages)) {
      unset($languages[$source_language]);
      return $languages;
    }

    return array();
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::hasCheckoutSettings().
   */
  public function hasCheckoutSettings(Job $job) {
    return FALSE;
  }

  /**
   * Local method to do request to Google Translate service.
   *
   * @param TMGMTTranslator $translator
   *   The translator entity to get the settings from.
   * @param string $action
   *   Action to be performed [translate, languages, detect]
   * @param array $query
   *   (Optional) Additional query params to be passed into the request.
   * @param array $options
   *   (Optional) Additional options that will be passed into drupal_http_request().
   *
   * @return array object
   *   Unserialized JSON response from Google.
   *
   * @throws TMGMTGoogleException
   *   - Invalid action provided
   *   - Unable to connect to the Google Service
   *   - Error returned by the Google Service
   */
  protected function doRequest(Translator $translator, $action, array $query = array(), array $options = array()) {

    if (!in_array($action, $this->availableActions)) {
      throw new TMGMTGoogleException('Invalid action requested: @action', array('@action' => $action));
    }

    // Translate action is requested without this argument.
    if ($action == 'translate') {
      $action = '';
    }

    $query['key'] = $translator->getSetting('api_key');
    $q = NULL;

    // If we have q param for translation as an array, we have to process it
    // in different way as does url() as Google does not accept typical
    // q[0] & q[1] ... syntax.
    if (isset($query[$this->qParamName]) && is_array($query[$this->qParamName])) {
      $q = $query[$this->qParamName];
      unset($query[$this->qParamName]);
    }

    $url = url($this->translatorUrl . '/' . $action, array('query' => $query));

    // Append q params to the url.
    if (!empty($q)) {
      foreach ($q as $source_text) {
        $url .= "&{$this->qParamName}=" . str_replace('%2F', '/', rawurlencode($source_text));
      }
    }

    $response = drupal_http_request($url, $options);

    if ($response->code != 200) {
      throw new TMGMTGoogleException('Unable to connect to Google Translate service due to following error: @error at @url',
        array('@error' => $response->error, '@url' => $url));
    }

    // Process the JSON result into array.
    $response = drupal_json_decode($response->data);

    // If we do not have data - we got error.
    if (!isset($response['data'])) {
      throw new TMGMTGoogleException('Google Translate service returned following error: @error',
        array('@error' => $response['error']['message']));
    }

    return $response;
  }

}
