<script>
import {conditionalField} from '../conditionalField';
import {hasDataStatementType} from '../dataStatementStore';

export default {
    name: 'FieldControlledVocabUrl',
    ...conditionalField('PkpFieldBaseAutosuggest', () =>
        hasDataStatementType(pkp.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE),
    ),
    props: {
        allowCustom: {
            type: Boolean,
            default: true,
        },
    },
    methods: {
        /**
         * There is no vocabulary to suggest from: the author types the URLs.
         */
        getSuggestions() {
            this.suggestions = [];
        },

        setSuggestions() {
            this.suggestions = [];
        },

        selectSuggestion(suggestion) {
            const value = suggestion ? suggestion.value : this.inputValue;
            if (!value) {
                return;
            }
            this.select({value: value, label: value});
        },

        select(item) {
            if (!item || !item.value) {
                return;
            }
            if (!this.isValidUrl(item.value)) {
                this.$emit(
                    'set-errors',
                    this.name,
                    [this.t('validator.active_url')],
                    this.localeKey,
                );
                return;
            }
            this.setSelected([...this.currentSelected, item]);
            this.inputValue = '';
        },

        isValidUrl(value) {
            try {
                new URL(value);
                return true;
            } catch (error) {
                return false;
            }
        },
    },
};
</script>
