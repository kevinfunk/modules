/**
 * @file
 * Javascript to integrate the clipboard.js library with Drupal.
 *
 * Copied from:
 * https://git.drupalcode.org/project/clipboardjs/-/blob/2.0.x/js/clipboard.js
 */

// eslint-disable-next-line no-undef
window.ClipboardJS = window.ClipboardJS || Clipboard;

(function ($, Drupal) {
  Drupal.behaviors.wsePreviewForm = {
    attach(context, settings) {
      const elements = context.querySelectorAll('a.clipboardjs-button');

      $(elements).click(function (event) {
        event.preventDefault();
      });

      // eslint-disable-next-line no-undef
      Drupal.clipboard = new ClipboardJS(elements);

      // Process successful copy.
      Drupal.clipboard.on('success', function (e) {
        let alertText = e.trigger.dataset.clipboardAlertText;
        alertText = alertText || Drupal.t('Copied.');

        // Display as tooltip.
        const $tooltip = $('.tooltip', e.trigger);
        const tooltipText = $('.tooltiptext', $tooltip)[0];

        // Show custom tooltip.
        tooltipText.textContent = alertText;
        tooltipText.style.visibility = 'visible';

        // Remove tooltip after delay.
        setTimeout(function () {
          tooltipText.style.visibility = 'hidden';
        }, 1500);
      });

      // Process unsuccessful copy.
      Drupal.clipboard.on('error', function (e) {
        let actionMsg = '';
        const actionKey = e.action === 'cut' ? 'X' : 'C';

        if (/iPhone|iPad/i.test(navigator.userAgent)) {
          actionMsg = Drupal.t(
            'This device does not support HTML5 Clipboard Copying. Please copy manually.',
          );
        } else if (/Mac/i.test(navigator.userAgent)) {
          actionMsg = Drupal.t('Press ⌘-@key to @action', {
            '@key': actionKey,
            '@action': e.action,
          });
        } else {
          actionMsg = Drupal.t('Press Ctrl-@key to @action', {
            '@key': actionKey,
            '@action': e.action,
          });
        }

        const $tooltip = $('.tooltip', e.trigger);
        const tooltipText = $('.tooltiptext', $tooltip)[0];

        // Show custom tooltip.
        tooltipText.textContent = actionMsg;
        tooltipText.style.visibility = 'visible';

        // Remove tooltip after delay.
        setTimeout(function () {
          tooltipText.style.visibility = 'hidden';
        }, 1500);
      });
    },
  };
})(jQuery, Drupal);
