(function ($) {
  $.pkp.plugins.generic.dataverse = {
    pageRootComponent: null,
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
      pageRootComponent.$modal.show('datasetFileModal');
    },
    refreshItems: function () {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

      console.log('ok');

      $.ajax({
        url: pageRootComponent.components.datasetFileForm.action.replace(
          'file',
          'files'
        ),
        type: 'GET',

        error: function (r) {
          console.log('error');
          pageRootComponent.ajaxErrorCallback(r);
        },

        success: function (r) {
          console.log(r);
          pageRootComponent.components.datasetFiles.items = r.items;
        },
      });
    },
  };

  $(document).ready(function () {
    const pageRootComponent = pkp.registry._instances.app;

    $.pkp.plugins.generic.dataverse.pageRootComponent = pageRootComponent;

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
