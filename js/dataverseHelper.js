(function ($) {
  $.pkp.plugins.generic.dataverse = {
    pageRootComponent: null,
    formSuccess: function (data) {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

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
  };

  $(document).ready(function () {
    const pageRootComponent = pkp.registry._instances.app;

    $.pkp.plugins.generic.dataverse.pageRootComponent = pageRootComponent;

    pkp.registry._instances.app.components.datasetMetadata.action =
      appDataverse.datasetApiUrl;

    const workingPublication = pkp.registry._instances.app.workingPublication;

    pkp.eventBus.$on('form-success', (formId, newPublication) => {
      if (formId === 'datasetMetadata') {
        pkp.registry._instances.app.workingPublication = workingPublication;
      }
    });

    const datasetMetadataForm = $('#dataset_metadata > form');

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.attributeName === 'action') {
          pkp.registry._instances.app.components.datasetMetadata.action =
            appDataverse.datasetApiUrl;
        }
      });
    });

    observer.observe(datasetMetadataForm.get(0), {
      attributeFilter: ['action'],
    });
  });
})(jQuery);
