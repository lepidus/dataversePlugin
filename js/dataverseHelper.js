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

      pageRootComponent.components.datasetFiles.isLoading = true;

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
          pageRootComponent.components.datasetFiles.isLoading = false;
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
    openDeleteModal(id) {
      const pageRootComponent =
        $.pkp.plugins.generic.dataverse.pageRootComponent;

			const datasetFile = pageRootComponent.components.datasetFiles.items.find(d => d.id === id);
			
      if (typeof datasetFile === 'undefined') {
				pageRootComponent.openDialog({
					confirmLabel: pageRootComponent.__('common.ok'),
					modalName: 'unknownError',
					message: pageRootComponent.__('common.unknownError'),
					title: pageRootComponent.__('common.error'),
					callback: () => {
						pageRootComponent.$modal.hide('unknownError');
					}
				});
				return;
			}
			pageRootComponent.openDialog({
				cancelLabel: pageRootComponent.__('common.no'),
				modalName: 'delete',
				title: pageRootComponent.deleteDatasetFileLabel,
				message: pageRootComponent.replaceLocaleParams(pageRootComponent.confirmDeleteMessage, {
					title: datasetFile.title
				}),
				callback: () => {
					var self = pageRootComponent;
          pageRootComponent.components.datasetFiles.isLoading = true;
					$.ajax({
						url: pageRootComponent.components.datasetFiles.apiUrl.replace('__id__', id),
						type: 'POST',
						headers: {
							'X-Csrf-Token': pkp.currentUser.csrfToken,
							'X-Http-Method-Override': 'DELETE'
						},
						error: self.ajaxErrorCallback,
						success: function(r) {
							self.components.datasetFiles.items = r.items;
              
							self.$modal.hide('delete');

              pageRootComponent.components.datasetFiles.isLoading = false;

							self.setFocusIn(self.$el);
						}
					});
				}
			});
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
