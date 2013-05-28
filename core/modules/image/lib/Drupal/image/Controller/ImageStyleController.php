<?php

/**
 * @file
 * Definition of Drupal\image\Controller\ImageStyleController.
 */

namespace Drupal\image\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\image\ImageStyleInterface;
use Drupal\image\Plugin\Core\Entity\ImageStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Controller for Image Style handling.
 */
class ImageStyleController {

  /**
   * Page callback: Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param $style
   *   The image style
   */
  public function deliver(Request $request, ImageStyle $image_style, $scheme) {
    //$args = func_get_args();
    //array_shift($args);
    //array_shift($args);
    //$target = implode('/', $args);
    $target = $request->query->get('path');
    
    //return new Response(json_encode(get_defined_vars()));
  
    // Check that the style is defined, the scheme is valid, and the image
    // derivative token is valid. (Sites which require image derivatives to be
    // generated without a token can set the
    // 'image.settings:allow_insecure_derivatives' configuration to TRUE to bypass
    // the latter check, but this will increase the site's vulnerability to
    // denial-of-service attacks.)
    $valid = !empty($style) && file_stream_wrapper_valid_scheme($scheme);
    if (!config('image.settings')->get('allow_insecure_derivatives')) {
      // TODO Move token to autoloading constant.
      $valid = $valid && isset($_GET[IMAGE_DERIVATIVE_TOKEN]) && $_GET[IMAGE_DERIVATIVE_TOKEN] === image_style_path_token($style->name, $scheme . '://' . $target);
    }
    if (!$valid) {
      throw new AccessDeniedHttpException();
    }
  
    $image_uri = $scheme . '://' . $target;
    $derivative_uri = image_style_path($style->id(), $image_uri);
  
    // If using the private scheme, let other modules provide headers and
    // control access to the file.
    if ($scheme == 'private') {
      if (file_exists($derivative_uri)) {
        file_download($scheme, file_uri_target($derivative_uri));
      }
      else {
        $headers = module_invoke_all('file_download', $image_uri);
        if (in_array(-1, $headers) || empty($headers)) {
          throw new AccessDeniedHttpException();
        }
        if (count($headers)) {
          foreach ($headers as $name => $value) {
            drupal_add_http_header($name, $value);
          }
        }
      }
    }
  
    // Don't try to generate file if source is missing.
    if (!file_exists($image_uri)) {
      watchdog('image', 'Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',  array('%source_image_path' => $image_uri, '%derivative_path' => $derivative_uri));
      throw new NotFoundHttpException(t('Error generating image, missing source file.'));
    }
  
    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    $lock_name = 'image_style_deliver:' . $style->id() . ':' . Crypt::hashBase64($image_uri);
    if (!file_exists($derivative_uri)) {
      $lock_acquired = lock()->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are known
        // to support Retry-After.
        //throw new HttpException(503);
        drupal_add_http_header('Status', '503 Service Unavailable');
        drupal_add_http_header('Retry-After', 3);
        print t('Image generation in progress. Try again shortly.');
        drupal_exit();
      }
    }
  
    // Try to generate the image, unless another thread just did it while we were
    // acquiring the lock.
    $success = file_exists($derivative_uri) || image_style_create_derivative($style, $image_uri, $derivative_uri);
  
    if (!empty($lock_acquired)) {
      lock()->release($lock_name);
    }
  
    if ($success) {
      $image = image_load($derivative_uri);
      $uri = $image->source;
      $headers = array(
        'Content-Type' => $image->info['mime_type'],
        'Content-Length' => $image->info['file_size'],
      );
      return new BinaryFileResponse($uri, 200, $headers);
    }
    else {
      watchdog('image', 'Unable to generate the derived image located at %path.', array('%path' => $derivative_uri));
      return new Response(t('Error generating image.'), 500);
    }
  }
}
