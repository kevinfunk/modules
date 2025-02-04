(function ($, Drupal, once) {
  Drupal.WSE = {};

  // Helper function to add/update query parameter. There are libraries to
  // generalize URL handling, but seem too heavy weight for what we need.
  Drupal.WSE.getWorkspaceUrl = function (url, workspaceId, args) {
    const defaults = {
      // Set to false to leave any existing workspace ID in the passed-in URL,
      // rather than replacing it with the new workspace ID. This is important
      // for link-rewriting code, because some links (for example, on the Site
      // Versions page) deliberately have a specific workspace ID in the URL
      // already.
      replace_existing: true,
      // Set to true to add a parameter to the URL that (if the URL is
      // followed) will tell the server not to write the workspace to the
      // user's session.
      temporary_override: false,
    };
    args = $.extend({}, defaults, args);

    // Replace or ignore the value if a workspace ID is already in the URL.
    if (url.indexOf('workspace') !== -1) {
      if (args.replace_existing) {
        url = url.replace(/([?&])(workspace=)[^&#]*/, `$1$2${workspaceId}`);
      }
    } else {
      const separator = url.indexOf('?') !== -1 ? '&' : '?';

      // Ensure that hash comes after query string.
      if (url.indexOf('#') !== -1) {
        const split = url.split('#');
        split[0] += `${separator}workspace=${workspaceId}`;
        url = split.join('#');
      } else {
        url += `${separator}workspace=${workspaceId}`;
      }
    }

    // Tell the server to only override the workspace temporarily for the
    // current request.
    if (
      args.temporary_override &&
      url.indexOf('workspace_temporary_override=1') === -1
    ) {
      url += '&workspace_temporary_override=1';
    }

    return url;
  };

  Drupal.behaviors.wse = {
    ajax_overridden: false,

    attach(context, settings) {
      // Add the active workspace ID to the query parameter.
      if ('wse' in settings && 'workspace_id' in settings.wse) {
        const url = Drupal.WSE.getWorkspaceUrl(
          window.location.href,
          settings.wse.workspace_id,
        );
        if (url !== window.location.href) {
          window.history.replaceState(null, null, url);
        }

        // Rewrite links to preserve the current workspace. This is done
        // server-side in \Drupal\wse\PathProcessor\WsePathProcessor also, but
        // that will not catch all links (for example, links embedded in
        // user-generated content).
        $(context)
          .find('a')
          .each(function () {
            const $link = $(this);
            const href = $link.attr('href');
            // Only rewrite non-anchor links that point to the current site.
            if (href && href.charAt(0) !== '#' && Drupal.url.isLocal(href)) {
              const newHref = Drupal.WSE.getWorkspaceUrl(
                href,
                settings.wse.workspace_id,
                { replace_existing: false },
              );
              if (href !== newHref) {
                $link.attr('href', newHref);
              }
            }
          });
        // Because some links can be dynamically added to the page without
        // Drupal.behaviors running (for example, JavaScript which adds a link
        // entirely client-side), also check the link when it is clicked and
        // rewrite it then. This is used in addition to the above code rather
        // than instead of it because it is not as robust (on many browsers it
        // will not detect scenarios like right-clicking and choosing "Open
        // Link in New Tab", although it will detect keyboard shortcuts for
        // opening links in a new tab). This code is heavily copied from
        // Drupal.overlay.eventhandlerOverrideLink; see that function for
        // additional code comments.
        // eslint-disable-next-line no-jquery/no-bind
        $(document).bind(
          'click.wse-workspace mouseup.wse-workspace',
          function (event) {
            // Handle right-clicks correctly.
            if (
              (event.type === 'click' && event.button === 2) ||
              (event.type === 'mouseup' && event.button !== 2)
            ) {
              return;
            }
            let $target = $(event.target);
            // Make sure this is a link.
            if ($target[0].tagName !== 'A') {
              $target = $target.closest('a');
              if (!$target.length) {
                return;
              }
            }
            const target = $target[0];
            const href = target.href;
            // Skip non-links and non-HTTP(S) links.
            if (
              href === undefined ||
              href === '' ||
              !target.protocol.match(/^https?:/)
            ) {
              return;
            }
            // Skip anchor links.
            const anchor = href.replace(target.ownerDocument.location.href, '');
            if (anchor.length === 0 || anchor.charAt(0) === '#') {
              return;
            }
            // Skip links to other sites.
            if (!Drupal.url.isLocal(href)) {
              return;
            }
            // If the link already has a workspace ID, do not rewrite it again.
            const newHref = Drupal.WSE.getWorkspaceUrl(
              href,
              settings.wse.workspace_id,
              { replace_existing: false },
            );
            if (href === newHref) {
              return;
            }
            // For normal clicks, override the click behavior and set the window
            // to the new location.
            if (
              event.button === 0 &&
              !event.altKey &&
              !event.ctrlKey &&
              !event.metaKey &&
              !event.shiftKey
            ) {
              window.location.href = newHref;
              return false;
            }
            // When pressing a special mouse button or keyboard key (for example,
            // to open the link in a new window or tab) temporarily alter the
            // clicked link's href.

            $target
              .one('blur mousedown', { target, href }, function (event) {
                $(event.data.target).attr('href', event.data.href);
              })
              .attr('href', newHref);
          },
        );

        // Rewrite Ajax requests to preserve the current workspace.
        if (!Drupal.behaviors.wse.ajax_overridden) {
          Drupal.behaviors.wse.ajax_overridden = true;
          const originalXMLHttpRequestOpen = XMLHttpRequest.prototype.open;
          XMLHttpRequest.prototype.open = function (...args) {
            // Rewrite the Ajax request URL to add the workspace ID. Ajax
            // requests can easily happen in a browser tab that is not active,
            // so tell the server to only override the workspace temporarily
            // for the current request (so it doesn't change the current active
            // editing session).
            args[1] = Drupal.WSE.getWorkspaceUrl(
              args[1],
              settings.wse.workspace_id,
              { temporary_override: true, replace_existing: false },
            );
            originalXMLHttpRequestOpen.apply(this, args);
          };
        }

        // Add a hidden element to forms to preserve the current workspace when
        // the form is submitted.
        once(
          'wse-workspace-form',
          'form:not(#wse-workspace-preview-form):not(.wse-workspace-switcher-form)',
          context,
        ).forEach(function (element) {
          $(element).append(
            `<input type="hidden" name="workspace_id" value="${
              settings.wse.workspace_id
            }">`,
          );
        });
      }
    },
  };
})(jQuery, Drupal, once);
