<?php

/**
 * @file
 * Contains \Drupal\block\Controller\CategoryAutocompleteController.
 */

namespace Drupal\tmgmt_google_test\Controller;

use Drupal\block\Plugin\Type\BlockManager;
use Drupal\Component\Utility\String;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for block categories.
 */
class GoogleTranslatorTestController {

  /**
   * Mock service to get available languages.
   */
  public function availableLanguages(Request $request) {
    if ($response = $this->validateKey($request)) {
      return $response;
    }

    $response = array(
      'data' => array(
        'languages' => array(
          array('language' => 'en'),
          array('language' => 'de'),
          array('language' => 'fr'),
        ),
      ),
    );

    return new JsonResponse($response);
  }

  /**
   * Key validator helper.
   */
  function validateKey(Request $request) {
    if ($request->get('key') != 'correct key') {
      return $this->trigger_response_error('usageLimits', 'keyInvalid', 'Bad Request');
    }
  }

  /**
   * Helper to trigger mok response error.
   *
   * @param string $domain
   * @param string $reason
   * @param string $message
   * @param string $locationType
   * @param string $location
   */
  function trigger_response_error($domain, $reason, $message, $locationType = NULL, $location = NULL) {

    $response = array(
      'error' => array(
        'errors' => array(
          'domain' => $domain,
          'reason' => $reason,
          'message' => $message,
        ),
        'code' => 400,
        'message' => $message,
      ),
    );

    if (!empty($locationType)) {
      $response['error']['errors']['locationType'] = $locationType;
    }
    if (!empty($location)) {
      $response['error']['errors']['location'] = $location;
    }

    return new JsonResponse($response);
  }



//    return new JsonResponse($matches); Keep for Copy/learning


}
