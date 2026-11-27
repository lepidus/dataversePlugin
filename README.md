**English** | [Português Brasileiro](/docs/README-pt_BR.md) | [Español](/docs/README-es.md)

# Dataverse Plugin

This plugin is the result of a partnership between SciELO Brazil and Lepidus. It enables the integration of Open Journal Systems (OJS) and Open Preprint Systems (OPS) with a Dataverse repository.

This way, authors can submit research data associated with their manuscripts during the submission process to the journal or preprint server. The research data remains available within the editorial workflow (for example, it can be made available during article peer review or preprint moderation) and is linked to the publication in OJS/OPS.

For a better understanding of the installation and use of this plugin, check our institutional video: [Dataverse plugin - Introduction and tutorial](https://app.heygen.com/videos/dataverse-plugin-introduction-and-tutorial-3cede08c38bf4065a4f769c1e35ae71d-en?languageCode=en).

## Compatibility

This plugin is compatible with the following PKP applications:

- OJS and OPS 3.5: plugin versions 4.x (branch `main`).
- OJS and OPS 3.4: plugin versions 3.4.x (branch `stable-3_4_0`).
- OJS and OPS 3.3: plugin versions 2.x (branch `stable-3_3_0`).

Check the latest compatible version for your application on the [Releases page](https://github.com/lepidus/dataversePlugin/releases).

All versions are compatible with Dataverse 5.x and 6.x.

## Requirements for usage

1. **api_key_secret**

The OJS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that (see [this post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

This is required to use the API credentials provided, that are stored encrypted in the OJS database.

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

## Installation

This plugin is available for installation via the [PKP Plugin Gallery](https://docs.pkp.sfu.ca/plugin-inventory/en/). For installation, follow these steps:

1. Access the __Dashboard__ area of your website.
2. Navigate to `Settings` > `Website` > `Plugins` > `Plugin Gallery`.
3. Search for the plugin called `Dataverse Plugin` and click on its name.
4. In the window that opens, click on `Install` and confirm that you want to install the plugin.

By following these steps, the plugin will be installed on your OJS/OPS. After installation, whenever you want to check if a new version is available, just follow the same path and check the plugin's status in the list.

## Repairing an incorrect core version (OJS/OPS 3.4)

Earlier plugin releases could record the Dataverse version as the application's core version.
Updating the plugin removes those incorrect records and restores the latest real OJS/OPS 3.4.0
version in the database, preserving its original installation date. The removed records are written
to the PHP error log.

Administrators of affected sites must then run `php tools/upgrade.php upgrade` from the application
root. This is especially important if OJS/OPS was upgraded after the corruption: restoring the
recorded version allows the core upgrade steps that were skipped to run. The repair does not mark
those steps as completed or create a version record for the current files.

## Deposit recovery and request timeouts

The `dataverse_deposits` table tracks one deposit operation per submission, including its state,
revision, source-file manifest and confirmed upload receipts. If the ingest wait fails before an
upload is sent, the operation returns to `ready`; retrying with the same files reuses the dataset
and skips uploads already confirmed. When a deposited dataset is missing from Dataverse, its local
association is removed and the completed operation is retired, allowing another deposit or association.

A lost response after creation or upload leaves the result unknown (`creating` or `uploading`).
These operations are not automatically reset or repeated. An administrator must reconcile the
operation with the configured Dataverse collection: check its persistent identifier, source manifest,
remote files and receipts (ZIP uploads can produce several remote files). Back up the database and
stop active deposit requests before any manual correction; preserve confirmed receipts and advance
the revision when changing state. Do not reset a record merely because a request timed out.

The following optional settings in `config.inc.php` show the default total request limits in seconds.
Use integer values from 1 to 300. The connection limit is 5 seconds; the separate ingest wait is
30 seconds and is not changed by these settings.

```ini
[dataverse]
read_timeout = 15
create_timeout = 30
upload_timeout = 60
write_timeout = 30
download_timeout = 300
```

## Instructions for use

### Configuration
After installation, it is necessary to enable the plugin. This is done in `Website Settings` > `Plugins` > `Installed Plugins`.

With the plugin enabled, you should expand its options by clicking the arrow next to its name and then accessing its `Settings`.

In the new window, the settings _Dataverse URL_, _API Token_, _Terms of Use_ and _Additional Instructions_ will be displayed.

You have to indicate the full Dataverse URL repository where the research data will be deposited. For example: `https://demo.dataverse.org/dataverse/anotherdemo`

The Terms of Use can be defined for each language configurated in your application. If you have questions about what they are, consult those responsible for the repository.

**Important:** The _API Token_ belongs to a Dataverse user account. For more information on how to obtain the API token, see the [Dataverse User Guide](https://guides.dataverse.org/en/5.13/user/account.html#api-token). Once saved, the token is no longer shown in the settings: leave the field blank to keep it, or enter a new token to replace it. Changing the Dataverse server requires entering the token again.

It is important to mention that the Dataverse user account will be included in the list of contributors of the datasets deposited by the plugin (for more information, see [this discussion](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Therefore, we recommend the creation of a specific user for the journal/preprint server, instead of using a person's personal account, because each deposit will be associated with that account.

After filling in the fields, just confirm the action by clicking `Save`. The plugin will only work after filling in these data.

### Use

A "Research Data" deposit dialog is shown in the "Files" step of the Submission proccess.

Authors, moderators, editors or managers can also edit the dataset, before publication, on the Research Data tab added to the submission's workflow.

In OJS, reviewers can have access to research data files during the review process. Reviewers access to these files can be restricted on Workflow Settings, so they can only see research data when they agree to review the submission.

### Downloading research data files

When Dataverse sends a file download to an external storage (for example, Amazon S3), the plugin only follows the redirect to an HTTPS address whose IP addresses are all public, and connects to the address it checked. Storage served over HTTP or from a private network address cannot be downloaded through the plugin.

If the server reaches the internet through a proxy (`[proxy] https_proxy` in `config.inc.php` or the `https_proxy`/`HTTPS_PROXY`/`all_proxy` environment variables), the proxy resolves the storage host itself, so the connection cannot be pinned to the address the plugin checked, and a host the server cannot resolve is left for the proxy to resolve, unless it is listed in `no_proxy`, which makes the download fail. In that case, the proxy is responsible for refusing internal addresses.

### Upgrading

Each dataset can be associated with only one submission. The upgrade (`php tools/upgrade.php upgrade`) creates a unique index on the persistent ID of the associated datasets. If a dataset is already associated with more than one submission, the index is not created and the persistent IDs are written to the PHP error log: disassociate the dataset from the extra submissions and run the upgrade again.

## Development Instructions:

1. Clone the Dataverse plugin repository
2. To use the plugin into the PKP Application, copy it's folder to the `/plugins/generic` directory and make sure the folder is called "dataverse".
3. And from the root of the PKP Appplication directory, execute this command to update the database, creating the tables used by the plugin:
    * `php tools/upgrade.php upgrade`

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Appplication directory:
```
find plugins/generic/dataverse -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### Integration Tests

Creates a `cypress.env.json` file in root of the PKP Application directory, with the following environment variables:
- `baseUrl`
- `dataverseUrl`
- `dataverseApiToken`
- `dataverseTermsOfUse`

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
npx cypress run --config 'specPattern=plugins/generic/dataverse/cypress/tests/Test*.cy.js'
```

For execute the tests with the Cypress UI, run:
```
npx cypress open --config 'specPattern=plugins/generic/dataverse/cypress/tests/Test*.cy.js'
```
Important: Cypress search for elements with expected strings. The locale of your operating system and PKP Application must be `en` for passing into the tests.

Use a restored test dataset with the plugin migrations applied and the `publicknowledge` context.
Each test prepares its own data. Run specs sequentially because plugin configuration is shared.

Tests create real Dataverse datasets. Cleanup removes only their own submissions and draft datasets;
published datasets and their local submissions are retained, as are records needed to recover failed cases.

Set `dataverseCustomRequiredMetadataFieldsUrl` to a collection requiring Alternative URL,
Description Date and PSRI to run the custom metadata cases; otherwise, those cases are pending.

## Credits
This plugin was sponsored by the Scientific Electronic Library Online (SciELO) and developed by Lepidus Tecnologia.

The development of this plugin seeks to give continuity to the integration between OJS and Dataverse, done previously through the [plugin for OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__
