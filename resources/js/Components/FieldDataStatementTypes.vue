<script>
import {setSelectedDataStatementTypes} from '../dataStatementStore';

export default {
    name: 'FieldDataStatementTypes',
    extends: pkp.registry.getComponent('PkpFieldOptions'),
    watch: {
        value: {
            immediate: true,
            handler(newValue) {
                setSelectedDataStatementTypes(newValue);
            },
        },
    },
};
</script>
