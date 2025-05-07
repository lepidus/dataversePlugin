var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: 'DataverseWorkflowPage',
    data() {
        return {
            dataversePluginApiUrl: null,
            datasetPluginApiUrl: null,
            rootDataverseName: '',
            dataverseName: '',
            dataverseLicenses: [],
            dataset: null,
            datasetCitation: '',
            datasetInReview: false,
            fileFormErrors: [],
            hasDepositedDataset: false,
            datasetIsLoading: true,
            isLoading: false,
            latestGetRequest: '',
            flagMounted: false,
            assignToIssueUrl: '',
			issueApiUrl: '',
			sectionWordLimits: {},
			selectIssueLabel: ''
        };
    },
    computed: {
        isFilesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },

        datasetIsPublished: function () {
            if (this.datasetIsLoading || this.dataset == null) {
                return false;
            }

            return this.dataset.versionState === 'RELEASED';
        },

        researchDataCount: function () {
            if (this.dataset) {
                return this.components.datasetFiles.items.length.toString();
            }
            return '0';
        },

        researchDataState: function () {
            const states = {
                'inManuscript': this.__('plugins.generic.dataverse.researchDataState.inManuscript.description'),
                'repoAvailable': this.__(
                    'plugins.generic.dataverse.researchDataState.repoAvailable.description',
                    { 'researchDataUrl': this.workingPublication.researchDataUrl }
                ).replace('{$researchDataUrl}', this.workingPublication.researchDataUrl),
                'onDemand': this.__(
                    'plugins.generic.dataverse.researchDataState.onDemand.description'
                ),
                'private': this.__(
                    'plugins.generic.dataverse.researchDataState.private.description',
                    { 'researchDataReason': this.workingPublication.researchDataReason }
                )
            };

            return states[this.workingPublication.researchDataState] || this.__('plugins.generic.dataverse.researchData.noResearchData');
        }
    },
    methods: {
        /**
		 * Open a modal to select an issue if the user has opted to
		 * schedule for publication before assigning to an issue
		 */
		openAssignToIssue() {
			const sourceUrl = this.assignToIssueUrl.replace(
				'__publicationId__',
				this.workingPublication.id
			);

			const opts = {
				title: this.selectIssueLabel,
				url: sourceUrl,
				closeOnFormSuccessId: pkp.const.FORM_ASSIGN_TO_ISSUE
			};

			$(
				'<div id="' +
					$.pkp.classes.Helper.uuid() +
					'" ' +
					'class="pkp_modal pkpModalWrapper" tabIndex="-1"></div>'
			).pkpHandler('$.pkp.controllers.modal.AjaxModalHandler', opts);
		},
        checkTermsOfUse() {
            $('input[name="termsOfUse"]').on('change', (e) => {
                this.validateTermsOfUse($(e.target).is(':checked'));
            });
        },

        fileFormSuccess(data) {
            this.refreshDatasetFiles();
            this.$modal.hide('fileForm');
        },

        getFileDownloadUrl(item) {
            return (
                this.components.datasetFileForm.action +
                `?fileId=${item.id}&filename=${item.fileName}`
            );
        },

        openAddFileModal() {
            this.components.datasetFileForm.fields.map((f) => (f.value = ''));

            this.components.datasetFileForm.errors = this.fileFormErrors;

            this.$modal.show('fileForm');
        },

        openDeleteDatasetModal() {
            if (this.canSendEmail) {
                this.$modal.show('deleteDataset');
            } else {
                self = this;
                this.openDialog({
                    cancelLabel: this.__('common.no'),
                    modalName: 'delete',
                    title: this.deleteDatasetLabel,
                    message: this.confirmDeleteDatasetMessage,
                    callback: () => {
                        $.ajax({
                            url: this.components.datasetMetadata.action,
                            type: 'POST',
                            headers: {
                                'X-Csrf-Token': pkp.currentUser.csrfToken,
                                'X-Http-Method-Override': 'DELETE',
                            },
                            error: this.ajaxErrorCallback,
                            success: function (r) {
                                self.$modal.hide('delete');
                                location.reload();
                            },
                        });
                    },
                });
            }
        },

        openDeleteFileModal(id) {
            const datasetFile = this.components.datasetFiles.items.find(
                (d) => d.id === id
            );
            if (typeof datasetFile === 'undefined') {
                this.openDialog({
                    confirmLabel: this.__('common.ok'),
                    modalName: 'unknownError',
                    message: this.__('common.unknownError'),
                    title: this.__('common.error'),
                    callback: () => {
                        this.$modal.hide('unknownError');
                    },
                });
                return;
            }
            this.openDialog({
                cancelLabel: this.__('common.no'),
                modalName: 'delete',
                title: this.deleteDatasetFileLabel,
                message: this.replaceLocaleParams(this.confirmDeleteMessage, {
                    title: datasetFile.fileName,
                }),
                callback: () => {
                    var self = this;
                    $.ajax({
                        url:
                            this.components.datasetFiles.apiUrl +
                            '&fileId=' +
                            id +
                            '&filename=' +
                            datasetFile.fileName,
                        type: 'POST',
                        headers: {
                            'X-Csrf-Token': pkp.currentUser.csrfToken,
                            'X-Http-Method-Override': 'DELETE',
                        },
                        error: self.ajaxErrorCallback,
                        success: function (r) {
                            self.refreshDatasetFiles();
                            self.$modal.hide('delete');
                            self.setFocusIn(self.$el);
                        },
                    });
                },
            });
        },

        openPublishDatasetModal() {
            self = this;
            this.openDialog({
                cancelLabel: this.__('common.no'),
                modalName: 'publish',
                title: this.publishDatasetLabel,
                message: this.confirmPublishDatasetMessage,
                callback: () => {
                    $.ajax({
                        url: this.components.datasetMetadata.action + '/publish',
                        type: 'POST',
                        headers: {
                            'X-Csrf-Token': pkp.currentUser.csrfToken,
                            'X-Http-Method-Override': 'PUT',
                        },
                        error: this.ajaxErrorCallback,
                        success: function (r) {
                            setTimeout(() => {
                                self.dataset = r;
                            }, 2500);
                            self.$modal.hide('publish');
                        },
                    });
                },
            });
        },

        getRootDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/rootDataverseName',
				type: 'GET',
				success: function (r) {
					self.rootDataverseName = r.rootDataverseName;
                    self.confirmPublishDatasetMessage = self.confirmPublishDatasetMessage.replace('{$serverName}', self.rootDataverseName);
				},
			});
        },

        getDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/dataverseName',
				type: 'GET',
				success: function (r) {
					self.dataverseName = r.dataverseName;
                    
                    if (self.hasDepositedDataset) {
                        let deleteDatasetForm = self.components.deleteDataset;
                        let deleteMessageField = deleteDatasetForm.fields[1];
                        deleteMessageField.value = deleteMessageField.value.replace('{$dataverseName}', self.dataverseName);
                    }

                    let datasetFileForm = self.components.datasetFileForm;
                    let termsOfUseFieldOption = datasetFileForm.fields[1].options[0];
                    termsOfUseFieldOption.label = termsOfUseFieldOption.label.replace('{$dataverseName}', self.dataverseName);
				},
			});
        },

        getDataverseLicenses() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/licenses',
				type: 'GET',
				success: function (r) {
                    let datasetMetadataForm = self.components.datasetMetadata;

                    for (let license of r.licenses) {
                        self.dataverseLicenses.push({'label': license.name, 'value': license.name});
                    }

                    datasetMetadataForm.fields[4].options = self.dataverseLicenses;
				},
			});
        },

        refreshDataset() {
            const self = this;
            this.datasetIsLoading = true;
            $.ajax({
                url: this.components.datasetMetadata.action,
                type: 'GET',
                success(r) {
                    self.dataset = r;
                    self.datasetIsLoading = false;
                }
            });
        },

        refreshDatasetFiles() {
            var self = this;
            this.components.datasetFiles.isLoading = true;
            this.latestGetRequest = $.pkp.classes.Helper.uuid();

            $.ajax({
                url: this.components.datasetFiles.apiUrl,
                type: 'GET',
                _uuid: this.latestGetRequest,
                error: function (r) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.ajaxErrorCallback(r);
                },
                success: function (r) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.setDatasetFiles(r.items);
                },
                complete() {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.components.datasetFiles.isLoading = false;
                },
            });
        },

        updateDatasetMetadataForm(dataset) {
            let form = { ...this.components.datasetMetadata };

            for (let field of form.fields) {
                let datasetFieldName = field.name.replace(/^dataset/, '').toLowerCase();
                
                if (this.dataset.hasOwnProperty(datasetFieldName)) {
                    field.value = this.dataset[datasetFieldName];
    
                    if (datasetFieldName == 'keywords') {
                        let selectedKeywords = [];
                        for (let keyword of this.dataset[datasetFieldName]) {
                            selectedKeywords.push({'label': keyword, 'value': keyword});
                        }
                        field.selected = selectedKeywords;
                    }
                }
            }
            form.canSubmit =
                this.canEditPublication &&
                dataset.versionState !== 'RELEASED'

            this.components.datasetMetadata = {};
            this.components.datasetMetadata = form;
        },

        updateDatasetCitation() {
            if (!this.datasetPluginApiUrl) {
                return;
            }
            var self = this;
            $.ajax({
                url: self.datasetPluginApiUrl+'/citation',
                type: 'GET',
                error: self.ajaxErrorCallback,
                success: (r) => {
                    self.datasetCitation = r.citation;
                },
            });
        },

        getDatasetInReview(dataset) {
            if (!this.datasetPluginApiUrl) {
                return;
            }
            var self = this;
            $.ajax({
                url: self.datasetPluginApiUrl+'/inReview?datasetId='+this.dataset.datasetId,
                type: 'GET',
                error: self.ajaxErrorCallback,
                success: (r) => {
                    self.datasetInReview = r.inReview;
                },
            });
        },

        setDatasetFiles(items) {
            this.components.datasetFiles.items = items;
        },

        validateTermsOfUse(value) {
            let newErrors = { ...this.components.datasetFileForm.errors };
            if (!!value) {
                if (!this.components.datasetFileForm.errors['termsOfUse']) {
                    return;
                }
                delete newErrors['termsOfUse'];
                this.components.datasetFileForm.errors = newErrors;
            } else {
                if (this.components.datasetFileForm.errors['termsOfUse']) {
                    return;
                }
                newErrors['termsOfUse'] = this.fileFormErrors['termsOfUse'];
                this.components.datasetFileForm.errors = newErrors;
            }
        },
    },
    created() {
        pkp.eventBus.$on('form-success', (formId, data) => {
            if (formId === 'datasetMetadata') {
                this.dataset = {};
                this.dataset = data;
            }
            if (formId === 'researchDataState') {
                this.workingPublication = {};
                this.workingPublication = data;
            }
            if (formId === pkp.const.FORM_PUBLISH) {
                if (!this.dataset) {
                    return;
                }
                setTimeout(() => {
                    this.refreshDataset();
                }, 2500);
            }
        });

        this.fileFormErrors = this.components.datasetFileForm.errors;
    },
    mounted() {
        setTimeout(() => {
            this.flagMounted = true;
        }, 2500);
        /**
		 * Open the publish modal when a publication has been
		 * assigned to an issue
		 */
		pkp.eventBus.$on('form-success', (formId, data) => {
			if (
				!pkp.const.FORM_ASSIGN_TO_ISSUE ||
				formId !== pkp.const.FORM_ASSIGN_TO_ISSUE
			) {
				return;
			}
			this.workingPublication = data;
			if (this.workingPublication.issueId) {
				this.$nextTick(() => this.openPublish());
			}
		});

		/**
		 * Open the assign to issue modal when a global publish
		 * event is fired
		 */
		pkp.eventBus.$on('schedule:publication', this.openAssignToIssue);
    },
    watch: {
        workingPublication(newVal, oldVal) {
			// Update the abstract word count when the section changes
			if (
				newVal.sectionId !== oldVal.sectionId &&
				this.components[pkp.const.FORM_TITLE_ABSTRACT]
			) {
				const wordLimit = this.sectionWordLimits[newVal.sectionId] || 0;
				let form = {...this.components[pkp.const.FORM_TITLE_ABSTRACT]};
				form.fields = form.fields.map(field => {
					if (field.name === 'abstract') {
						field.wordLimit = wordLimit;
					}
					return field;
				});
				this.components[pkp.const.FORM_TITLE_ABSTRACT] = {};
				this.components[pkp.const.FORM_TITLE_ABSTRACT] = form;
			}

			if (newVal.issueId !== oldVal.issueId) {
				// Update the issue selection in the form when it has changed
				// through the schedule for publication form
				let form = this.components[pkp.const.FORM_ISSUE_ENTRY];
				form.fields = form.fields.map(field => {
					if (field.name === 'issueId') {
						field.value = newVal.issueId;
					}
					return field;
				});

				// Update the issue volume and number used in the DOI
				// field when the issue changes
				if (!newVal.issueId) {
					this.publicationFormIds.forEach(formId => {
						if (formId !== pkp.const.FORM_PUBLICATION_IDENTIFIERS) {
							return;
						}
						let form = {...this.components[formId]};
						form.fields = form.fields.map(field => {
							if (field.name === 'pub-id::doi') {
								field.issueNumber = '';
								field.issueVolume = '';
								if (!this.workingPublication.datePublished) {
									field.year = '';
								}
							}
							return field;
						});
						this.components[formId] = {};
						this.components[formId] = form;
					});
				} else {
					var self = this;
					$.ajax({
						url: this.issueApiUrl.replace('__issueId__', newVal.issueId),
						type: 'GET',
						error(r) {
							self.ajaxErrorCallback(r);
						},
						success(r) {
							self.publicationFormIds.forEach(formId => {
								if (formId !== pkp.const.FORM_PUBLICATION_IDENTIFIERS) {
									return;
								}
								let form = {...self.components[formId]};
								form.fields = form.fields.map(field => {
									if (field.name === 'pub-id::doi') {
										field.issueNumber = r.number || '';
										field.issueVolume = r.volume || '';
										if (!self.workingPublication.datePublished) {
											field.year = r.year || '';
										}
									}
									return field;
								});
								self.components[formId] = {};
								self.components[formId] = form;
							});
						}
					});
				}
			}
		},
        dataset(newVal, oldVal) {
            this.updateDatasetMetadataForm(newVal);
            this.updateDatasetCitation();
            this.getDatasetInReview(newVal);
        },
        flagMounted(newVal, oldVal) {
            this.getDataverseName();
            this.getDataverseLicenses();

            if (this.hasDepositedDataset) {
                this.getRootDataverseName();
                this.refreshDataset();
                this.refreshDatasetFiles();
            }
        }
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
