# Dataverse Plugin

We are implementing this plugin for OPS and OJS 3.3 (or higher) for SciELO Brasil.

It is a work in progress, the current version is a MVP for OPS and OJS.

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.3.0
* OJS 3.3.0

Using PHP between 7.3 and 8.1.

## Requirements for usage

1. **api_key_secret**

The OJS/OPS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that (see [this post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

This is required to use the API credentials provided, that are stored encrypted in the OJS/OPS database.

## Data exchanged with Dataverse

The plugin only connects to the Dataverse repository configured in its settings, using the API token saved there. Nothing is sent before a Dataverse URL and an API token are saved.

When research data is deposited, and whenever the dataset metadata or files are changed in the workflow, the plugin sends to Dataverse:

- the research data files uploaded for the submission;
- the dataset metadata taken from the submission: title, abstract, keywords, the subject, license and language chosen for the dataset, and any other metadata the Dataverse collection requires;
- the names, affiliations and ORCID iDs of the submission's authors;
- the name, email address and affiliation of the dataset contact, who is the submission's primary contact (or the submitting user, when there is none);
- the depositor, made of the submitting user's name and the journal or server name;
- the citation of the article or preprint, as the dataset's related publication.

Depending on the plugin settings and on editorial decisions, the plugin also asks Dataverse to publish or delete the dataset. A published dataset is made public by Dataverse according to that repository's own policies.

From Dataverse, the plugin receives the dataset metadata, citation and files, shown in the workflow, to reviewers and on the public article or preprint page; the collection's name, licenses and required metadata fields; and the API token's expiration date, checked daily to warn administrators before the token expires.

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/dataversePlugin/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation

### Instructions

1. Install the plugin dependencies.
2. Enter the administration area of ​​your application and navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __dataverse.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Instructions for use

### Configuration
After installation, it is necessary to enable the plugin. This is done in `Website Settings` > `Plugins` > `Installed Plugins`.

With the plugin enabled, you should expand its options by clicking the arrow next to its name and then accessing its `Settings`.

In the new window, the  _Dataverse_ (Dataverse URL), _API Token_ and _Terms of Use_ will be displayed.

You have to indicate the full Dataverse URL repository where the research data will be deposited, for example: https://demo.dataverse.org/dataverse/anotherdemo

The Terms of Use can be defined for each language configurated in your application. If you have questions about what they are, consult those responsible for the repository.

**Important:** The _API Token_ belongs to a Dataverse user account. For more information on how to obtain the API token, see the [Dataverse User Guide](https://guides.dataverse.org/en/5.13/user/account.html#api-token). Once saved, the token is no longer shown in the settings: leave the field blank to keep it, or enter a new token to replace it. Changing the Dataverse server requires entering the token again.

It is important to mention that the Dataverse user account will be included in the list of contributors of the datasets deposited by the plugin (for more [information, see this discussion](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Therefore, we recommend the creation of a specific user for the journal/preprint server, instead of using a person's personal account, because each deposit will be associated with that account.

After filling in the fields, just confirm the action by clicking `Save`. The plugin will only work after filling in these data.

### Use

A "Research Data" deposit dialog is shown in step 2 of the Submission proccess.

Authors, moderators, editors or managers can also edit the dataset, before publication, on the Research Data tab of the submission.

In OJS, reviewers can have access to research data files during the review process. Reviewers access to these files can be restricted on Workflow Settings, so they can only see research data when they agree to review the submission.

### Research data downloads

When Dataverse redirects a file download to its storage (for example, S3), the plugin only follows the redirect over HTTPS and to public network addresses. Downloads from storage served over HTTP or from a private network address fail, and the plugin reports the research data repository as unavailable. In that case, disable the download redirect of that store in Dataverse (`dataverse.files.<id>.download-redirect=false`), so Dataverse serves the files itself.

If the application uses a proxy (`https_proxy` in the `[proxy]` section of `config.inc.php`, or the `https_proxy` or `all_proxy` environment variables), the plugin still resolves and checks the storage host when the server can resolve it. When it cannot, the proxy resolves the host: the plugin still requires HTTPS and refuses private addresses written in the redirect, but it cannot check the addresses the proxy resolves, so restrict the destinations the proxy can reach. A host the server cannot resolve that is listed in `no_proxy` is refused, since it would not go through the proxy.

### Datasets associated with more than one submission

A dataset can only be associated with one submission. When updating the plugin, if a dataset is already associated with more than one submission, the update logs the line `Dataverse: these datasets are associated with more than one submission` with their persistent IDs and does not create the database index that enforces this rule. Keep each of those datasets in a single submission, disassociating it from the others on the Research Data tab, and then run `php tools/upgrade.php upgrade` again.

## Development Instructions:

1. Clone the Dataverse plugin repository
2. To use the plugin into the PKP Application, copy it's folder to the /plugins/generic directory and make sure the folder is called "dataverse".
3. And from the root of the PKP Appplication directory, execute this command to update the database, creating the tables used by the plugin:
    * php tools/upgrade.php upgrade

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Appplication directory:
```
find plugins/generic/dataverse -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### Integration Tests

Creates a `cypress.env.json` file in root of the PKP Application directory, with the following environment variables:
- baseUrl
- dataverseUrl
- dataverseApiToken
- dataverseTermsOfUse

**Example**:

```json
{
    "baseUrl": "http://localhost:8000",
    "dataverseUrl": "https://demo.dataverse.org/dataverse/myDataverseAlias",
    "dataverseApiToken": "abcd-abcd-abcd-abcd-abcdefghijkl",
    "dataverseTermsOfUse": "https://dataverse.org/best-practices/harvard-dataverse-general-terms-use",
    "dataverseAdditionalInstructions": "Additional instructions about research data submission:"
}
```

Next, to execute the Cypress tests run the following command from root of the PKP Appplication directory:
```
npx cypress run --config integrationFolder=plugins/generic/dataverse/cypress/tests
```

For execute the tests with the Cypress UI, run:
```
npx cypress open --config integrationFolder=plugins/generic/dataverse/cypress/tests
```
Important: Cypress search for elements with expected strings. The locale of your operating system and PKP Application must be `en_US` for passing into the tests.

# License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2021-2026 Lepidus Tecnologia__

__Copyright (c) 2021-2026 SciELO__
