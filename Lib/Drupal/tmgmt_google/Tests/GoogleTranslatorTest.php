<?php

/**
 * @file
 * Test cases for the google translator module.
 */

namespace Drupal\tmgmt_google\Tests;

use Drupal\tmgmt\Tests\TMGMTTestBase;
use TMGMTGoogleTranslatorPluginController;

/**
 * Basic tests for the google translator.
 */
class GoogleTranslatorTest extends TMGMTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt_google', 'tmgmt_google_test');

  /**
   * Implements getInfo().
   */
  static function getInfo()
  {
    return array(
      'name' => 'Google Translator tests',
      'description' => 'Tests the google translator plugin integration.',
      'group' => 'Translation Management',
    );
  }

  /**
   * Tests basic API methods of the plugin.
   */
  function testGoogle()
  {
    $this->addLanguage('de');
    $translator = $this->createTranslator();
    $translator->plugin = 'google';
    $translator->settings = array(
      'url' => url('https://www.googleapis.com/language/translate/v2', array(
        'absolute' => TRUE,
      )),
    );
    $translator->save();

    $job = $this->createJob();
    $job->translator = $translator->name;
    $item = $job->addItem('test_source', 'test', '1');
    $item->data = array(
      'wrapper' => array(
        '#text' => 'Hello world',
      ),
    );
    $item->save();

    $this->assertFalse($job->isTranslatable(), 'Check if the translator is not
                       available at this point because we did not define the API
                       parameters.');

    // Save a wrong api key.
    $translator->settings['api'] = 'wrong key';
    $translator->settings['clientid'] = 'wrong clientid';
    $translator->settings['clientsecret'] = 'wrong secret';
    $translator->save();

    $t = $job->getTranslator();
    $languages = $t->getSupportedTargetLanguages('en');
    $this->assertTrue(empty($languages), t('We can not get the languages using wrong api parameters.'));

    // Save a correct api key.
    $translator->settings['api'] = 'correct key';
    $translator->settings['clientid'] = 'correct clientid';
    $translator->settings['clientsecret'] = 'correct secret';
    $translator->save();

    // Make sure the translator returns the correct supported target languages.
    $t = $job->getTranslator();
    cache('tmgmt')->deleteAll();
    $languages = $t->getSupportedTargetLanguages('en');
    $this->assertTrue(isset($languages['de']));
    $this->assertTrue(isset($languages['es']));
    $this->assertTrue(isset($languages['it']));
    $this->assertTrue(isset($languages['zh-hans']));
    $this->assertTrue(isset($languages['zh-hant']));
    $this->assertFalse(isset($languages['zh-CHS']));
    $this->assertFalse(isset($languages['zh-CHT']));
    $this->assertFalse(isset($languages['en']));

    $this->assertTrue($job->canRequestTranslation());

    $job->requestTranslation();

    // Now it should be needs review.
    foreach ($job->getItems() as $item) {
      $this->assertTrue($item->isNeedsReview());
    }
    $items = $job->getItems();
    $item = end($items);
    $data = $item->getData();
    $this->assertEqual('de_Hello world', $data['wrapper']['#translation']['#text']);


  }
}

