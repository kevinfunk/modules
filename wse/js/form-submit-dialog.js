(function (Drupal, drupalSettings, $, once) {
  Drupal.behaviors.wseFormSubmitDialog = {
    attach(context, settings) {
      if (!settings.wseSubmitDialog.formSelectors.length) {
        return;
      }

      settings.wseSubmitDialog.formSelectors.forEach(function (formSelector) {
        once('wse-form-submit-dialog', `form.${formSelector}`, context).forEach(
          function (form) {
            let submissionConfirmed = false;
            form
              .querySelectorAll('.form-submit')
              .forEach(function (submitButton) {
                submitButton.addEventListener('click', function (event) {
                  if (submissionConfirmed) {
                    return true;
                  }

                  const $confirmDialog = $(
                    `<div>${Drupal.t(
                      'Warning! This form changes configuration that can not be tracked or contained inside a workspace, so it will be applied to the Live site instead. Do you want to continue anyway?',
                    )}</div>`,
                  ).appendTo('body');
                  Drupal.dialog($confirmDialog, {
                    title: Drupal.t(
                      'Form should not be submitted in a workspace',
                    ),
                    minWidth: 600,
                    minHeight: 230,
                    buttons: [
                      {
                        text: Drupal.t('Submit in Live'),
                        click() {
                          submissionConfirmed = true;
                          $(submitButton).click();
                        },
                      },
                      {
                        text: Drupal.t('Cancel'),
                        click() {
                          $(this).dialog('close');
                        },
                      },
                    ],
                  }).showModal();

                  event.preventDefault();
                  return false;
                });
              });
          },
        );
      });
    },
  };
})(Drupal, drupalSettings, jQuery, once);
