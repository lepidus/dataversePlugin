(function ($) {	

	$(document).ready(function () {

		const pageRootComponent = pkp.registry._instances.app;
		const workingPublication = pageRootComponent.workingPublication;

		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.attributeName === 'action') {
					pageRootComponent.components.datasetMetadata.action =
					pageRootComponent.datasetApiUrl;
				}
			});
		});

		pageRootComponent.components.datasetMetadata.action = pageRootComponent.datasetApiUrl;
		
		const datasetMetadataForm = $('#datasetTab > form');
	
		observer.observe(datasetMetadataForm.get(0), {
			attributes: true, subtree: true
		});
	
		pkp.eventBus.$on('form-success', (formId) => {
			if (formId === 'datasetMetadata') {
				pageRootComponent.workingPublication = workingPublication;
				location.reload();
			}
		});

	});
	
})(jQuery);
