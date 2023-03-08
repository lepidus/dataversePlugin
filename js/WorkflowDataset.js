(function ($) {

	$(document).ready(function () {

		const pageRootComponent = pkp.registry._instances.app;
		const workingPublication = pageRootComponent.workingPublication;
		const datasetMetadataForm = $('#datasetTab > form, #dataset_metadata > form');
		const disabled = datasetMetadataForm.find('.pkpFormPage__buttons button').prop('disabled');

		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.attributeName === 'action') {
					pageRootComponent.components.datasetMetadata.action =
						pageRootComponent.datasetApiUrl;
				}
				if (mutation.attributeName === 'disabled') {
					let disabled = mutation.target.disabled;

					$('#datasetData > .pkpHeader > .pkpHeader__actions > button').prop('disabled', disabled);
					$('#datasetFiles .pkpHeader > .pkpHeader__actions button').prop('disabled', disabled);
					$('#datasetFiles .listPanel__item .listPanel__itemActions button').prop('disabled', disabled);
				}
			});
		});

		pageRootComponent.components.datasetMetadata.action = pageRootComponent.datasetApiUrl;

		$('#datasetData > .pkpHeader > .pkpHeader__actions > button').prop('disabled', disabled);
		$('#datasetFiles .pkpHeader > .pkpHeader__actions button').prop('disabled', disabled);
		$('#datasetFiles .listPanel__item .listPanel__itemActions button').prop('disabled', disabled);

		observer.observe(datasetMetadataForm.get(0), {
			attributes: true, subtree: true
		});

		pkp.eventBus.$on('form-success', (formId) => {
			if (formId === 'datasetMetadata') {
				pageRootComponent.workingPublication = workingPublication;
				if ($.pkp.plugins.generic.dataverse) {
					insertCitationInTemplate();
				} else {
					location.reload();
				}
			}
		});

		if ($.pkp.plugins.generic.dataverse) {
			$.pkp.plugins.generic.dataverse.pageRootComponent = pageRootComponent;
			$.pkp.plugins.generic.dataverse.formErrors =
				pageRootComponent.components.datasetFileForm.errors;
		}

	});

})(jQuery);
