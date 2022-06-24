async function getStudy() {
  const response = await fetch(appDataverse.editUri, {
    headers: {
      'X-Dataverse-key': appDataverse.apiToken
    }
  })
  const { data } = await response.json()
  return data
}

function getCitation(fields) {
  const publication = fields.find(({ typeName }) => typeName === 'publication')
  return publication.value[0].publicationCitation.value
} 
async function insertCitationInTemplate() {
  const citationSection = document.getElementById('data_citation')
  const citationParagraph = citationSection.querySelector('p')

  citationParagraph.textContent = "loading..."
  try {
    const study = await getStudy()
    const { latestVersion: { metadataBlocks: { citation: { fields } } } } = study
    const citation = getCitation(fields)
    citationParagraph.innerHTML = citation
  } catch (error) {
    citationParagraph.innerHTML = appDataverse.errorMessage
  }
}

insertCitationInTemplate()