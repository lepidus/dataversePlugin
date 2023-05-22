const template = pkp.Vue.compile(`
    <div class="pkpFormField pkpFormField--autosuggest pkpFormField--url">
        <div class="pkpFormField__heading">
            <form-field-label
                :controlId="controlId"
                :label="label"
                :localeLabel="localeLabel"
                :isRequired="isRequired"
                :requiredLabel="__('common.required')"
                :multilingualLabel="multilingualLabel"
            />
            <tooltip v-if="tooltip" aria-hidden="true" :tooltip="tooltip" label="" />
            <span
                v-if="tooltip"
                class="-screenReader"
                :id="describedByTooltipId"
                v-html="tooltip"
            />
            <help-button
                v-if="helpTopic"
                :id="describedByHelpId"
                :topic="helpTopic"
                :section="helpSection"
                :label="__('help.help')"
            />
        </div>
        <div
            v-if="isPrimaryLocale && description"
            class="pkpFormField__description"
            v-html="description"
            :id="describedByDescriptionId"
        />
        <div
            class="pkpFormField__control pkpFormField--autosuggest__control"
            :class="{
                'pkpFormField__control--hasMultilingualIndicator':
                    isMultilingual && locales.length > 1
            }"
        >
            <div
                v-if="currentPosition === 'inline'"
                class="pkpFormField--autosuggest__values pkpFormField--autosuggest__values--inline"
                :id="describedBySelectedId"
                ref="values"
            >
                <span class="-screenReader">{{ selectedLabel }}</span>
                <span v-if="!currentValue.length" class="-screenReader">
                    {{ __('common.none') }}
                </span>
                <pkp-badge v-else v-for="item in currentSelected" :key="item.value">
                    <a :href="item.label" target="_new">{{ item.label }}</a>
                    <button
                        class="pkpFormField--autosuggest__valueButton"
                        @click.stop.prevent="deselect(item)"
                    >
                        <icon icon="times" />
                        <span class="-screenReader">
                            {{ deselectLabel.replace('{$item}', item.label) }}
                        </span>
                    </button>
                </pkp-badge>
            </div>
            <vue-autosuggest
                v-model="inputValue"
                ref="autosuggest"
                class="pkpFormField--autosuggest__autosuggest"
                v-bind="autosuggestOptions"
                @selected="selectSuggestion"
            />
            <div
                v-if="currentPosition === 'below'"
                class="pkpFormField--autosuggest__values pkpFormField--autosuggest__values--below"
                :id="describedBySelectedId"
                ref="values"
            >
                <span class="-screenReader">{{ selectedLabel }}</span>
                <span v-if="!currentValue.length" class="-screenReader">
                    {{ __('common.none') }}
                </span>
                <pkp-badge v-else v-for="item in currentSelected" :key="item.value">
                    <a v-if="isValidUrl(item.label)" :href="item.label" target="_new">{{ item.label }}</a>
                    <span v-else>{{ item.label }}</span>
                    <button
                        class="pkpFormField--autosuggest__valueButton"
                        @click.stop.prevent="deselect(item)"
                    >
                        <icon icon="times" />
                        <span class="-screenReader">
                            {{ deselectLabel.replace('{$item}', item.label) }}
                        </span>
                    </button>
                </pkp-badge>
            </div>
            <multilingual-progress
                v-if="isMultilingual && locales.length > 1"
                :id="multilingualProgressId"
                :count="multilingualFieldsCompleted"
                :total="locales.length"
            />
            <field-error
                v-if="errors && errors.length"
                :id="describedByErrorId"
                :messages="errors"
            />
        </div>
    </div>
`);

const FieldControlledVocab =
    pkp.controllers.Container.components.PkpForm.components.FormPage.components
        .FormGroup.components.FieldControlledVocab;

pkp.Vue.component('field-controlled-vocab-url', {
    name: 'FieldControlledVocabUrl',
    extends: FieldControlledVocab,
    methods: {
        isValidUrl: function (string) {
            try {
                new URL(string);
                return true;
            } catch (err) {
                return false;
            }
        },
        select(item) {
			if (!item) {
				if (!this.inputValue || !this.suggestions.length) {
					return;
				}
				item = this.suggestions[0];
			}
            if (this.isValidUrl(item.value)) {
                this.currentSelected.push(item);
            } else {
                this.setErrors([this.__('validator.active_url')]);
            }
			this.inputValue = '';
			this.$nextTick(() => {
				this.$nextTick(() => {
					this.$nextTick(() =>
						this.$el.querySelector('#' + this.controlId).focus()
					);
				});
			});
		},
        setErrors: function(errors) {
			this.$emit('set-errors', this.name, errors, this.localeKey);
		},
    },
    render: function (h) {
        return template.render.call(this, h);
    },
});
