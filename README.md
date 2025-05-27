**English** | [Português Brasileiro](/docs/README-pt_BR.md) | [Español](/docs/README-es.md)

# Dataverse Plugin

This plugin is the result of a partnership between SciELO Brazil and Lepidus. It enables the integration of Open Journal Systems (OJS) and Open Preprint Systems (OPS) with a Dataverse repository.
This way, authors can submit research data associated with their manuscripts during the submission process to the journal or preprint server. The research data remains available within the editorial workflow (for example, it can be made available during article peer review or preprint moderation) and is linked to the publication in OJS/OPS.

## Compatibility

This plugin is compatible with the following PKP applications:

- OPS and OJS versions 3.3 and 3.4.

Check the latest compatible version for your application on the [Releases page](https://github.com/lepidus/dataversePlugin/releases).

All versions are compatible with Dataverse 5.x and 6.x.

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/dataversePlugin/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation

This plugin is available for installation via the [PKP Plugin Gallery](https://docs.pkp.sfu.ca/plugin-inventory/en/). For installation, follow these steps:

1. Access the __Dashboard__ area of your website.
2. Navigate to `Settings` > `Website` > `Plugins` > `Plugin Gallery`.
3. Search for the plugin called `Dataverse Plugin` and click on its name.
4. In the window that opens, click on `Install` and confirm that you want to install the plugin.

By following these steps, the plugin will be installed on your OJS/OPS. After installation, whenever you want to check if a new version is available, just follow the same path and check the plugin's status in the list.

## Instructions for use

### Configuration
After installation, it is necessary to enable the plugin. This is done in `Website Settings` > `Plugins` > `Installed Plugins`.

With the plugin enabled, you should expand its options by clicking the arrow next to its name and then accessing its `Settings`.

In the new window, the settings _Dataverse URL_, _API Token_, _Terms of Use_ and _Additional Instructions_ will be displayed.

You have to indicate the full Dataverse URL repository where the research data will be deposited. For example: `https://demo.dataverse.org/dataverse/anotherdemo`

The Terms of Use can be defined for each language configurated in your application. If you have questions about what they are, consult those responsible for the repository.

**Important:** The _API Token_ belongs to a Dataverse user account. For more information on how to obtain the API token, see the [Dataverse User Guide](https://guides.dataverse.org/en/5.13/user/account.html#api-token).

It is important to mention that the Dataverse user account will be included in the list of contributors of the datasets deposited by the plugin (for more information, see [this discussion](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Therefore, we recommend the creation of a specific user for the journal/preprint server, instead of using a person's personal account, because each deposit will be associated with that account.

After filling in the fields, just confirm the action by clicking `Save`. The plugin will only work after filling in these data.

### Use

A "Research Data" deposit dialog is shown in the "Files" step of the Submission proccess.

Authors, moderators, editors or managers can also edit the dataset, before publication, on the Research Data tab added to the submission's workflow.

In OJS, reviewers can have access to research data files during the review process. Reviewers access to these files can be restricted on Workflow Settings, so they can only see research data when they agree to review the submission.

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
npx cypress run --config specPattern=plugins/generic/dataverse/cypress/tests
```

For execute the tests with the Cypress UI, run:
```
npx cypress open --config specPattern=plugins/generic/dataverse/cypress/tests
```
Important: Cypress search for elements with expected strings. The locale of your operating system and PKP Application must be `en` for passing into the tests.

## Credits
This plugin was sponsored by the Scientific Electronic Library Online (SciELO) and developed by Lepidus Tecnologia.

The development of this plugin seeks to give continuity to the integration between OJS and Dataverse, done previously through the [plugin for OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__
