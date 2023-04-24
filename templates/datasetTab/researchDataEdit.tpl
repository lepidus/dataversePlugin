<section class="researchDataHeader">
    <div class="researchData__state">
        <strong>{translate key="plugins.generic.dataverse.researchDataState.state"}:</strong>
        <span v-html="researchDataState"></span>
    </div>
    <div class="researchData__stateButton">
        <dropdown label="Editar">
            <pkp-form
                class="researchData__stateForm"
                v-bind="components.researchDataState"
                @set="set"
                @success="refreshSubmission"
            >
        </dropdown>
        <pkp-button>Adicionar dados de pesquisa</pkp-button>
    </div>
</section>