import {reactive} from 'vue';

const state = reactive({
    selectedTypes: [],
});

export function setSelectedDataStatementTypes(types) {
    state.selectedTypes = Array.isArray(types) ? types.map(Number) : [];
}

export function hasDataStatementType(type) {
    return state.selectedTypes.includes(Number(type));
}

export default state;
