<?php

/**
 * @file
 * Contains Drupal\image\PathProcessor.
 */

namespace Drupal\image;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for Image module.
 */
class PathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $image_base_path = $this->getImageBasePath();
    //print json_encode(get_defined_vars());

    // Rewrite filesystem public image path to route with path as a query
    // parameter.
    //
    // {filedir}/image/{style}/path/to/image to
    // image/{style}?path={filedir}/image/{style}/path/to/image
    if (strpos($path, $image_base_path) === 0) {
      $request->query->set('path', $path);

      // Get route arguments from path.
      $parts = explode('/', substr($path, strlen($image_base_path)));
      $style = $parts[0];
      $scheme = $parts[1];

      $request->query->set('path', $path);
      $path = "system/files/styles/$style/$scheme";
    }
//     print json_encode(get_defined_vars());
//     exit();

    return $path;
  }

  /**
   * A/B this W / W/O cache / skip entirely.
   */
  protected function getImageBasePath() {
    // Might be nice to inject this...
    $directory_path = file_stream_wrapper_get_instance_by_scheme('public')->getDirectoryPath();
    return $directory_path . '/styles/';
  }
}
