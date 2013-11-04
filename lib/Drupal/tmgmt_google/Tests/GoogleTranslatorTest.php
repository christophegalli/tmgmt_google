<?php

/**
 * @file
 * Test cases for the google translator module.
 */

namespace Drupal\tmgmt_google\Tests;

use Drupal\tmgmt\Tests\TMGMTTestBase;
use Drupal\tmgmt_google\Plugin\tmgmt\Translator\GoogleTranslator;
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
  public static function getInfo()  {
    return array(
      'name' => 'Google Translator tests',
      'description' => 'Tests the google translator plugin integration.',
      'group' => 'Translation Management',
    );
  }

  /**
   * Tests basic API methods of the plugin.
   */
  protected function testGoogle() {
    $this->addLanguage('de');
    $translator = $this->createTranslator();
    $translator->plugin = 'google';
    $translator->save();

    $plugin = $translator->getController();
    $this->assertTrue($plugin instanceof GoogleTranslator,
      'Plugin initialization - we expect TMGMTGoogleTranslatorPluginController type.');

    // Override plugin params to query tmgmt_google_test mock service instead
    // of Google Translate service.
    $translator->settings = array(
      'url' => url('tmgmt_google_test', array('absolute' => TRUE)),
    );

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
    $translator->settings['api_key'] = 'wrong key';
    $translator->save();

    $t = $job->getTranslator();
    $languages = $t->getSupportedTargetLanguages('en');
    $this->assertTrue(empty($languages), t('We can not get the languages using wrong api parameters.'));

    // Save a correct api key.
    $translator->settings['api_key'] = 'correct key';
    $translator->save();

    // Make sure the translator returns the correct supported target languages.
    $t = $job->getTranslator();
    cache('tmgmt')->deleteAll();
    $languages = $t->getSupportedTargetLanguages('en');
    $this->assertTrue(isset($languages['de']));
    $this->assertTrue(isset($languages['fr']));
    // As we requested source language english it should not be included.
    $this->assertTrue(!isset($languages['en']));

    $this->assertTrue($job->isTranslatable());

    $job->requestTranslation();

    // Now it should be needs review.
    foreach ($job->getItems() as $item) {
      $this->assertTrue($item->isNeedsReview());
    }
    $items = $job->getItems();
    $item = end($items);
    $data = $item->getData();
    $this->assertEqual('Hallo Welt', $data['wrapper']['#translation']['#text']);

  }
}

