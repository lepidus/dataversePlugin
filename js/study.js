function formatYear(date) {
  return date.substring(0, 4)
}

function formatCitation(data) {
  const {
    publisher, 
    persistentUrl,
    publicationDate,
    latestVersion: { metadataBlocks: { citation: { fields } } } 
  } = data

  let citation = {
    publisher,
    persistentUrl,
    year: formatYear(publicationDate)
  }
  fields.forEach(field => {
    field.typeName === "title" && (citation.title = field.value)
    if(field.typeName === "author") {
      citation.authors = field.value.map(({ authorName }) => authorName.value)
    }
  })
  return citation
}

async function getDeposit() {
  const response = await fetch(appDataverse.editUri, {
    headers: {
      'X-Dataverse-key': appDataverse.apiToken
    }
  })
  const { data } = await response.json()
  return data
}

async function insertCitationInTemplate() {
  const deposit = await getDeposit()
  const { authors, year, title, persistentUrl, publisher } = formatCitation(deposit)

  const citationSection = document.getElementById('data_citation')
  citationSection.querySelector('p').innerHTML = `
    ${authors.join('; ')}, ${year}, "${title}",  
    <a href="${persistentUrl}">${persistentUrl}</a>, 
    ${publisher}
  `
}

insertCitationInTemplate()