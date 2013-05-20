<?php

/**
 * @file
 * Contains Drupal\image\PathProcessor.
 */

namespace Drupal\image;

use Drupal;
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
    //$image_style_path = $this->getImagePath();

    return $path;

    // Rewrite {filedir}/image/{style}/path/to/image to
    // image/{style}?path={filedir}/image/{style}/path/to/image
    if (strpos($path, $image_style_path) === 0) {
      $path = "image/style/$style";
      $request->query->set('path', $path);
    }

    return $path;
  }

  protected function getImagePath() {
    array('');
    \Drupal::getContainer()->get('router.route_provider')->getRoutesByNames($routes);
  }
}
