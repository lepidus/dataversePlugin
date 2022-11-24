(function ($) {
  $.pkp.plugins.generic.dataverse = {
    pageRootComponent: null,
    errors: [],
    formSuccess: function (data) {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

      $.pkp.plugins.generic.dataverse.refreshItems();
      pageRootComponent.$modal.hide('datasetFileModal');
    },
    datasetFileModalOpen: function () {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

      pageRootComponent.components.datasetFileForm.fields.map(
        (f) => (f.value = '')
      );

      pageRootComponent.components.datasetFileForm.errors =
        $.pkp.plugins.generic.dataverse.formErrors;

      pageRootComponent.$modal.show('datasetFileModal');
    },
    refreshItems: function () {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

      $.ajax({
        url: pageRootComponent.components.datasetFileForm.action.replace(
          'file',
          'files'
        ),
        type: 'GET',
        error: function (r) {
          pageRootComponent.ajaxErrorCallback(r);
        },
        success: function (r) {
          pageRootComponent.components.datasetFiles.items = r.items;
        },
      });
    },
    defineTermsOfUseErrors() {
      $('input[name="termsOfUse"]').on('change', (e) => {
        $.pkp.plugins.generic.dataverse.validateTermsOfUse(
          $(e.target).is(':checked')
        );
      });
    },
    validateTermsOfUse(value) {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

      let newErrors = {
        ...pageRootComponent.components.datasetFileForm.errors,
      };

      if (!!value) {
        if (
          !pageRootComponent.components.datasetFileForm.errors['termsOfUse']
        ) {
          return;
        }
        delete newErrors['termsOfUse'];
        pageRootComponent.components.datasetFileForm.errors = newErrors;
      } else {
        if (pageRootComponent.components.datasetFileForm.errors['termsOfUse']) {
          return;
        }
        newErrors['termsOfUse'] =
          $.pkp.plugins.generic.dataverse.formErrors['termsOfUse'];

        pageRootComponent.components.datasetFileForm.errors = newErrors;
      }
    },
  };

  $(document).ready(function () {
    const pageRootComponent = pkp.registry._instances.app;

    $.pkp.plugins.generic.dataverse.pageRootComponent = pageRootComponent;
    $.pkp.plugins.generic.dataverse.formErrors =
      pageRootComponent.components.datasetFileForm.errors;

    pageRootComponent.components.datasetMetadata.action =
      appDataverse.datasetApiUrl;

    const workingPublication = pageRootComponent.workingPublication;

    pkp.eventBus.$on('form-success', (formId, newPublication) => {
      if (formId === 'datasetMetadata') {
        pageRootComponent.workingPublication = workingPublication;
      }
    });

    const datasetMetadataForm = $('#dataset_metadata > form');

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.attributeName === 'action') {
          pageRootComponent.components.datasetMetadata.action =
            appDataverse.datasetApiUrl;
        }
      });
    });

    observer.observe(datasetMetadataForm.get(0), {
      attributeFilter: ['action'],
    });
  });
})(jQuery);
