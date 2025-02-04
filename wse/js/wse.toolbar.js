/**
 * @file wse.toolbar.js
 */
(($, Drupal) => {
  /**
   * Allows the WSE workspace switcher to be shown in the toolbar.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to toggle the toolbar workspace switcher.
   */
  Drupal.behaviors.WseSwitcherToggle = {
    attach(context) {
      const wseSwitcherToggle = once(
        'wse-switcher-toggle',
        '.toolbar-icon-workspace',
      );

      $(wseSwitcherToggle).on('click', (e) => {
        e.preventDefault();
        $(e.currentTarget)
          .parent()
          .find('.wse-workspace-switcher-form')
          .toggleClass('is-active');
      });

      // Any click on a link inside the switcher form should hide it.
      $('.wse-workspace-switcher-form a').on('click', (e) => {
        $(e.currentTarget)
          .closest('.wse-workspace-switcher-form')
          .removeClass('is-active');
      });
    },
  };
})(jQuery, Drupal);
