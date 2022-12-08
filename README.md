# Dataverse Plugin

We are implementing this plugin for OPS and OJS 3.3 (or higher) for SciELO Brasil.

It is a work in progress, the current version is a MVP for OPS only.

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.3 (or higher)

Using PHP 7.3 or higher, but below (<) PHP 8.0. 

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/dataversePlugin/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation dependencies 
* [php-zip](https://www.php.net/manual/pt_BR/zip.installation.php)

## Development dependencies
* [php-zip](https://www.php.net/manual/pt_BR/zip.installation.php)

## Development Instructions:

1. Clone the Dataverse plugin repository
2. Open a terminal inside the repository directory and run the following commands:
     * git submodule init
     * git submodule update

To clone [SWORD v2 PHP API library](https://github.com/swordapp/swordappv2-php-library/) submodule.

3. To use the plugin into the PKP Application, copy it's folder to the /plugins/generic directory and make sure the folder is called "dataverse".
4. And from the root of the PKP Appplication directory, execute this command to update the database, creating the tables used by the plugin:
    * php tools/upgrade.php upgrade
## Installation

1. Install the 'php-zip' dependency.
2. Enter the administration area of ​​your application and navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __dataverse.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Instructions for use

### Configuration
After installation, it is necessary to enable the plugin. This is done in `Website Settings` > `Plugins` > `Installed Plugins`.

With the plugin enabled, you should expand its options by clicking the arrow next to its name and then accessing its `Settings`. 

In the new window, the  _Dataverse_ (Dataverse URL), _API Token_ and _Terms of Use_ will be displayed. You have to indicate the full Dataverse URL, for example: https://demo.dataverse.org/dataverse/anotherdemo

The Terms of Use can be defined for each languange configurated in your application.

Important: The _API Token_ belongs to a Dataverse account. This account will appear as the Depositor of each dataset submited to Dataverse.

After filling in the fields, just confirm the action by clicking `Save`. The plugin will only work after filling in these data.

### Use
A "Research Data" deposit dialog is shown in step 2 of the Submission proccess.

Authors, moderators, editors or managers can also edit the dataset, before publication, on the Research Data tab of the submission.

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Appplication directory:
```
find plugins/generic/dataverse -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### Integration Tests

Creates a cypress.env.json file in root of the PKP Application directory, with the following environment variables:
- baseUrl
- serverName
- serverPath
- adminUser
- adminPassword
- dataverseServer
- dataverseURI
- dataverseAPIToken
- dataverseTermsOfUse

**Example**:

```json
{
    "baseUrl": "http://localhost:8000",
    "serverName": "My Preprint Server",
    "serverPath": "myPreprintServer",
    "adminUser": "admin",
    "adminPassword": "admin",
    "dataverseServerName": "Demo Dataverse",
    "dataverseURI": "https://demo.dataverse.org/dataverse/myDataverseAlias",
    "dataverseAPIToken": "abcd-abcd-abcd-abcd-abcdefghijkl",
    "dataverseTermsOfUse": "https://dataverse.org/best-practices/harvard-dataverse-general-terms-use"
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

__Copyright (c) 2021-2022 Lepidus Tecnologia__

__Copyright (c) 2021-2022 SciELO__
