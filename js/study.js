async function getDatasetData() {
  const response = await fetch(appDataverse.datasetApiUrl)
  const { data } = await response.json()
  return data
}

async function insertCitationInTemplate() {
  const citationSection = document.getElementById('datasetData')
  const citationParagraph = citationSection.querySelector('p')

  citationParagraph.textContent = "loading..."
  try {
    const datasetData = await getDatasetData()
    citationParagraph.innerHTML = datasetData.citation
  } catch (error) {
    citationParagraph.innerHTML = appDataverse.errorMessage
  }
}

insertCitationInTemplate()