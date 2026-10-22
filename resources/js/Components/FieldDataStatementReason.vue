<script>
import {conditionalField} from '../conditionalField';
import {hasDataStatementType} from '../dataStatementStore';

export default {
    name: 'FieldDataStatementReason',
    ...conditionalField('PkpFieldText', () =>
        hasDataStatementType(pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE),
    ),
};
</script>
