var DraftDatasetFilesPage = $.extend(true, {}, pkp.controllers.Page, {
    data() {
        return {
            notifications: [],
			errors: [],
            latestGetRequest: '',
            isLoading: false
        }
    },
    methods: {
        refreshItems() {
            var self = this;

            this.isLoading = true;

            this.latestGetRequest = $.pkp.classes.Helper.uuid();

            $.ajax({
				url: this.components.draftDatasetFileForm.action,
				type: 'GET',
				_uuid: this.latestGetRequest,
				error: function(r) {
					if (self.latestGetRequest !== this._uuid) {
						return;
					}
					self.ajaxErrorCallback(r);
				},
				success: function(r) {
					if (self.latestGetRequest !== this._uuid) {
						return;
					}
					self.setItems(r.items);
				},
				complete() {
					if (self.latestGetRequest !== this._uuid) {
						return;
					}
					self.isLoading = false;
				}
			});
        },
        setItems(items) {
			this.components.draftDatasetFilesList.items = items;
		},
        datasetFileModalClose() {
            this.setFocusToRef('datasetModalButton');
        },
        datasetFileModalOpen() {
            this.components.draftDatasetFileForm.fields.map(f => f.value = '');
            this.$modal.show('datasetModal');
        },
        formSuccess(data) {
            this.refreshItems();
            this.$modal.hide('datasetModal');
        },
		checkTermsOfUse() {
			let self = this;
			console.log($('input[name="termsOfUse"]').is(':checked'));
			$('input[name="termsOfUse"]').on('change', (e) => {
				this.validateTermsOfUse($(e.target).is(':checked'));
			});
		},
		validateTermsOfUse(value) {
			let newErrors = {...this.errors};
			if(!!value) {
				if (!this.errors['termsOfUse']) {
					return;
				}
				delete newErrors['termsOfUse'];
				this.errors = newErrors;
			}
			else {
				if (this.errors['termsOfUse']) {
					return;
				}
				newErrors['termsOfUse'] = this.formErrors['termsOfUse'];
				this.errors = newErrors;
			}
		},
        openDeleteModal(id) {
			const draftDatasetFile = this.components.draftDatasetFilesList.items.find(d => d.id === id);
			if (typeof draftDatasetFile === 'undefined') {
				this.openDialog({
					confirmLabel: this.__('common.ok'),
					modalName: 'unknownError',
					message: this.__('common.unknownError'),
					title: this.__('common.error'),
					callback: () => {
						this.$modal.hide('unknownError');
					}
				});
				return;
			}
			this.openDialog({
				cancelLabel: this.__('common.no'),
				modalName: 'delete',
				title: this.deleteDraftDatasetFileLabel,
				message: this.replaceLocaleParams(this.confirmDeleteMessage, {
					title: draftDatasetFile.fileName
				}),
				callback: () => {
					var self = this;
					$.ajax({
						url: this.apiUrl + '/' + id,
						type: 'POST',
						headers: {
							'X-Csrf-Token': pkp.currentUser.csrfToken,
							'X-Http-Method-Override': 'DELETE'
						},
						error: self.ajaxErrorCallback,
						success: function(r) {
							self.setItems(
								self.components.draftDatasetFilesList.items.filter(i => i.id !== id),
							);
							self.$modal.hide('delete');
							self.setFocusIn(self.$el);
						}
					});
				}
			});
		},
    },
	mounted() {
		this.errors = this.formErrors;
	}
});

pkp.controllers['DraftDatasetFilesPage'] = DraftDatasetFilesPage;