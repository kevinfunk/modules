<?php

namespace Drupal\piwik_pro;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Creates the snippet to embed.
 */
class PiwikProSnippet {

  use StringTranslationTrait;

  /**
   * The Piwik PRO configuration object with original configuration data.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  private $aliasManager;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private $pathMatcher;

  /**
   * The current path for the current request.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  private $currentPath;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * PiwikProSnippet constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager service.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path for the current request.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    CurrentPathStack $current_path,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
  ) {
    $this->config = $configFactory->get('piwik_pro.settings');
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->currentPath = $current_path;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * Returns the snippet.
   *
   * @return string|null
   *   The configured snippet if the display conditions are met or
   *   NULL if not or no snippet is available.
   */
  public function getSnippet(): string|null {
    $script = $this->getScript();
    if ($script) {
      return "<script type=\"text/javascript\">" . $script['script'] . "</script>" . $script['noscript'];
    }
    return NULL;
  }

  /**
   * Get the Piwik PRO script.
   *
   * @return array|null
   *   Array with [script, noscript] part of the snippet.
   */
  public function getScript(): array|null {
    $piwik_domain = (string) $this->config->get('piwik_domain');
    $site_id = (string) $this->config->get('site_id');
    $data_layer = (string) $this->config->get('data_layer');
    $title = $this->t('Piwik PRO embed snippet', [], ['context' => 'Piwik PRO']);

    if (!empty($data_layer) && !empty($site_id) && !empty($piwik_domain)) {
      $script = sprintf('(function(window, document, dataLayerName, id) {
window[dataLayerName]=window[dataLayerName]||[],window[dataLayerName].push({start:(new Date).getTime(),event:"stg.start"});var scripts=document.getElementsByTagName(\'script\')[0],tags=document.createElement(\'script\');
function stgCreateCookie(a,b,c){var d="";if(c){var e=new Date;e.setTime(e.getTime()+24*c*60*60*1e3),d="; expires="+e.toUTCString()}document.cookie=a+"="+b+d+"; path=/"}
var isStgDebug=(window.location.href.match("stg_debug")||document.cookie.match("stg_debug"))&&!window.location.href.match("stg_disable_debug");stgCreateCookie("stg_debug",isStgDebug?1:"",isStgDebug?14:-1);
var qP=[];dataLayerName!=="dataLayer"&&qP.push("data_layer_name="+dataLayerName),isStgDebug&&qP.push("stg_debug");var qPString=qP.length>0?("?"+qP.join("&")):"";
tags.async=!0,tags.src="%s"+id+".js"+qPString,scripts.parentNode.insertBefore(tags,scripts);
!function(a,n,i){a[n]=a[n]||{};for(var c=0;c<i.length;c++)!function(i){a[n][i]=a[n][i]||{},a[n][i].api=a[n][i].api||function(){var a=[].slice.call(arguments,0);"string"==typeof a[0]&&window[dataLayerName].push({event:n+"."+i+":"+a[0],parameters:[].slice.call(arguments,1)})}}(i[c])}(window,"ppms",["tm","cm"]);
})(window, document, \'%s\', \'%s\');',
        $piwik_domain, $data_layer, $site_id
      );
      $noscript = sprintf('<noscript><iframe src="%s%s/noscript.html" title="%s" height="0" width="0" style="display:none;visibility:hidden" aria-hidden="true"></iframe></noscript>',
        $piwik_domain, $site_id, $title
      );

      if ($this->isVisible()) {
        return [
          'script' => "\n// <![CDATA[\n" . $script . "\n// ]]>\n",
          'noscript' => $noscript,
        ];
      }
    }
    return NULL;
  }

  /**
   * Check if the snippet should be visible.
   *
   * @return bool
   *   TRUE if the snippet should be visible, FALSE otherwise.
   */
  public function isVisible(): bool {
    return $this->getVisibilityPages() && $this->getVisibilityRoles() && $this->getVisibilityContentTypes();
  }

  /**
   * Visibility check on pages.
   *
   * Check if the snippet should be shown on this page (TRUE) or not (FALSE).
   */
  public function getVisibilityPages(): bool {
    static $is_visible;

    // Cache visibility result if function is called more than once.
    if (isset($is_visible)) {
      return $is_visible;
    }

    $visibility_request_path_mode = $this->config->get('visibility.request_path_mode');
    $visibility_request_path_pages = mb_strtolower((string) $this->config->get('visibility.request_path_pages'));

    $path = $this->currentPath->getPath();
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));
    $page_match = $this->pathMatcher->matchPath($path_alias, $visibility_request_path_pages) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $visibility_request_path_pages));

    if ($visibility_request_path_mode === 1) {
      // The listed pages only.
      if ($page_match) {
        return $is_visible = TRUE;
      }
      else {
        return $is_visible = FALSE;
      }
    }
    else {
      // Every page except the listed pages.
      if ($page_match) {
        return $is_visible = FALSE;
      }
      else {
        return $is_visible = TRUE;
      }
    }
  }

  /**
   * Visibility check on roles.
   *
   * Check if the snippet should be shown for the current
   * user (TRUE) or not (FALSE).
   */
  public function getVisibilityRoles(): bool {
    static $is_visible;

    // Cache visibility result if function is called more than once.
    if (isset($is_visible)) {
      return $is_visible;
    }

    $visibility_user_roles = $this->config->get('visibility.user_roles');
    $visibility_user_role_mode = $this->config->get('visibility.user_role_mode');
    $role_matches = array_intersect($this->currentUser->getRoles(), $visibility_user_roles);

    if ($visibility_user_role_mode === 1) {
      // The listed roles only.
      if ($role_matches) {
        return $is_visible = TRUE;
      }
      else {
        return $is_visible = FALSE;
      }
    }
    else {
      // Every role except the listed roles.
      if ($role_matches) {
        return $is_visible = FALSE;
      }
      else {
        return $is_visible = TRUE;
      }
    }
  }

  /**
   * Visibility check on content types.
   *
   * Check if the snippet should be shown for the current
   * content type (TRUE) or not (FALSE).
   */
  public function getVisibilityContentTypes(): bool {
    static $is_visible;

    if (isset($is_visible)) {
      return $is_visible;
    }

    $node = $this->routeMatch->getParameter('node');
    if (!($node instanceof NodeInterface)) {
      return $is_visible = TRUE;
    }

    $visibility_content_types = $this->config->get('visibility.content_types') ?? [];
    $visibility_content_type_mode = $this->config->get('visibility.content_type_mode') ?? 0;
    $node_type = $node->getType();
    if ($visibility_content_type_mode === 1) {
      // The listed roles only.
      if (in_array($node_type, $visibility_content_types)) {
        return $is_visible = TRUE;
      }
      else {
        return $is_visible = FALSE;
      }
    }
    else {
      // Every content type except the selected.
      if (in_array($node_type, $visibility_content_types)) {
        return $is_visible = FALSE;
      }
      else {
        return $is_visible = TRUE;
      }
    }
  }

}
