(function ($) {

	$.pkp.plugins.generic = $.pkp.plugins.generic || {};

	$.pkp.plugins.generic.dataverse = {

		pageRootComponent: null,

		errors: [],

		downloadFileUrl: null,

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

		defineTermsOfUseErrors: function() {
			$('input[name="termsOfUse"]').on('change', (e) => {
				$.pkp.plugins.generic.dataverse.validateTermsOfUse(
					$(e.target).is(':checked')
				);
			});
		},

		validateTermsOfUse: function(value) {
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

		openDeleteModal: function(id) {
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
						success: function (r) {
							self.components.datasetFiles.items = r.items;

							self.$modal.hide('delete');

							pageRootComponent.components.datasetFiles.isLoading = false;

							self.setFocusIn(self.$el);
						}
					});
				}
			});
		},

		openDeleteDatasetModal: function() {
			const pageRootComponent =
				$.pkp.plugins.generic.dataverse.pageRootComponent;

			pageRootComponent.openDialog({
				cancelLabel: pageRootComponent.__('common.no'),
				modalName: 'delete',
				title: pageRootComponent.deleteDatasetLabel,
				message: pageRootComponent.confirmDeleteDatasetMessage,
				callback: () => {
					$.ajax({
						url: appDataverse.datasetApiUrl,
						type: 'POST',
						headers: {
							'X-Csrf-Token': pkp.currentUser.csrfToken,
							'X-Http-Method-Override': 'DELETE'
						},
						error: pageRootComponent.ajaxErrorCallback,
						success: function (r) {
							pageRootComponent.$modal.hide('delete');
							location.reload();
						}
					});
				}
			});
		},

		getFileDownloadUrl: function(item) {
			const pageRootComponent =
				$.pkp.plugins.generic.dataverse.pageRootComponent;

			if (pageRootComponent) {
				return pageRootComponent.components.datasetFiles.apiUrl.replace('__id__', item.id) + '&filename=' + item.title;
			}

			return;
		}
	};
	
})(jQuery);
