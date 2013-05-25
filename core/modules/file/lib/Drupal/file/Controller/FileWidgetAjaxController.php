<?php

/**
 * @file
 * Definition of Drupal\file\FileWidgetAjaxController.
 */

namespace Drupal\file\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\system\Controller\FormAjaxController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Defines a controller to respond to file widget AJAX requests.
 */
class FileWidgetAjaxController extends FormAjaxController {

  /**
   * Handle form AHAH request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Symfony response object.
   */
  public function upload(Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));
    $form_build_id = $request->query->get('form_build_id');
    $request_form_build_id = $request->request->get('form_build_id');

    if (empty($request_form_build_id) || $form_build_id !== $request_form_build_id) {
      // Invalid request.
      drupal_set_message(t('An unrecoverable error occurred. The uploaded file likely exceeded the maximum file size (@size) that this server supports.', array('@size' => format_size(file_upload_max_size()))), 'error');
      $response = new AjaxResponse();
      return $response->addCommand(new ReplaceCommand(NULL, theme('status_messages')));
    }

    try {
      list($form, $form_state) = $this->getForm($request);
    }
    catch (HttpExceptionInterface $e) {
      // Invalid form_build_id.
      drupal_set_message(t('An unrecoverable error occurred. Use of this form has expired. Try reloading the page and submitting again.'), 'error');
      $response = new AjaxResponse();
      return $response->addCommand(new ReplaceCommand(NULL, theme('status_messages')));
    }

    // Get the current element and count the number of files.
    $current_element = NestedArray::getValue($form, $form_parents);
    $current_file_count = isset($current_element['#file_upload_delta']) ? $current_element['#file_upload_delta'] : 0;

    // Process user input. $form and $form_state are modified in the process.
    drupal_process_form($form['#form_id'], $form, $form_state);

    // Retrieve the element to be rendered.
    $form = NestedArray::getValue($form, $form_parents);

    // Add the special Ajax class if a new file was added.
    if (isset($form['#file_upload_delta']) && $current_file_count < $form['#file_upload_delta']) {
      $form[$current_file_count]['#attributes']['class'][] = 'ajax-new-content';
    }
    // Otherwise just add the new content class on a placeholder.
    else {
      $form['#suffix'] .= '<span class="ajax-new-content"></span>';
    }

    $form['#prefix'] .= theme('status_messages');
    $output = drupal_render($form);
    $js = drupal_add_js();
    $settings = drupal_merge_js_settings($js['settings']['data']);

    $response = new AjaxResponse();
    return $response->addCommand(new ReplaceCommand(NULL, $output, $settings));
  }

  /**
   * Ajax callback: Retrieves upload progress.
   *
   * @param $key
   *   The unique key for this upload process.
   */
  public function progress($key) {
    $progress = array(
        'message' => t('Starting upload...'),
        'percentage' => -1,
    );

    $implementation = file_progress_implementation();
    if ($implementation == 'uploadprogress') {
      $status = uploadprogress_get_info($key);
      if (isset($status['bytes_uploaded']) && !empty($status['bytes_total'])) {
        $progress['message'] = t('Uploading... (@current of @total)', array('@current' => format_size($status['bytes_uploaded']), '@total' => format_size($status['bytes_total'])));
        $progress['percentage'] = round(100 * $status['bytes_uploaded'] / $status['bytes_total']);
      }
    }
    elseif ($implementation == 'apc') {
      $status = apc_fetch('upload_' . $key);
      if (isset($status['current']) && !empty($status['total'])) {
        $progress['message'] = t('Uploading... (@current of @total)', array('@current' => format_size($status['current']), '@total' => format_size($status['total'])));
        $progress['percentage'] = round(100 * $status['current'] / $status['total']);
      }
    }

    return new JsonResponse($progress);
  }
}
