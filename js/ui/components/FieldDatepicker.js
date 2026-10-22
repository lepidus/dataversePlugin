pkp.Vue.component('field-datepicker', {
    name: 'FieldDatepicker',
    extends: pkp.Vue.component('PkpFieldText'),
    mounted() {
        // The core reads the input value on focus. Calling setDate here would
        // populate its shared popup while this field's wizard step is hidden.
        $(this.$refs.input).datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            disabled: this.isDisabled,
            beforeShow: (input) => {
                $(input).datepicker('widget').addClass('dataverse-datepicker');
                // jQuery UI uses monthNamesShort for the month select.
                return {monthNamesShort: $(input).datepicker('option', 'monthNames')};
            },
            onClose: () => {
                const calendar = $(this.$refs.input).datepicker('widget');
                // onClose fires before the core's closing animation finishes.
                calendar.promise('fx').done(() => {
                    if (!calendar.is(':visible')) {
                        calendar.removeClass('dataverse-datepicker');
                    }
                });
            },
            onSelect: (value) => {
                // jQuery selections do not fire the input event used by Vue's v-model.
                this.currentValue = value;
            },
        });
        // PKP handlers stop bubbling before jQuery UI's document listener.
        document.addEventListener('pointerdown', this.closeOnOutsideClick, true);
    },
    beforeDestroy() {
        document.removeEventListener('pointerdown', this.closeOnOutsideClick, true);
        $(this.$refs.input).datepicker('hide');
        $(this.$refs.input).datepicker('destroy');
    },
    methods: {
        closeOnOutsideClick(event) {
            const input = this.$refs.input;
            const calendar = $(input).datepicker('widget')[0];
            if (event.target !== input && !calendar.contains(event.target)) {
                $(input).datepicker('hide');
            }
        },
    },
    watch: {
        '$root.currentStepId'() {
            $(this.$refs.input).datepicker('hide');
        },
        isDisabled(value) {
            $(this.$refs.input).datepicker('option', 'disabled', value);
        },
    },
});
