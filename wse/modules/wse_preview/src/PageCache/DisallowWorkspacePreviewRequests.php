<?php

namespace Drupal\wse_preview\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cache policy for pages served from workspace previews.
 */
class DisallowWorkspacePreviewRequests implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    // Ensure that pages accessed through workspace previews are not cached.
    if ($request->cookies->has('wse_preview')) {
      return self::DENY;
    }
  }

}
