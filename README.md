# Dataverse Plugin

This plugin deposits publications on the Dataverse associated with the publishing server.

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.3 (or higher)
* OJS 3.3 (or higher)

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/dataversePlugin/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation dependencies 
* [php-zip](https://www.php.net/manual/pt_BR/zip.installation.php)

## Development dependencies
* [php-zip](https://www.php.net/manual/pt_BR/zip.installation.php)


## Installation

1. Install the 'php-zip' dependency.
2. Enter the administration area of ​​your application and navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __dataverse.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Instructions for use

After installation, it is necessary to enable the plugin. This is done in `Website Settings` > `Plugins` > `Installed Plugins`.

With the plugin enabled, you should expand its options by clicking the arrow next to its name and then accessing its `Settings`. In the window that opens, the _Dataverse URL_ (Root Dataverse URL), _Dataverse_ (Dataverse URL) and _API Token_ will be displayed. After filling in the fields, just confirm the action by clicking `Save`. The plugin will only work after filling in these data.

## Installation for development
1. Install the development dependencies.
2. Clone the [repository](https://github.com/lepidus/titlePageForPreprint)
3. Switch branch, if needed.
4. Make a symbolic link for `plugins/generic`
4. Update the database to complete the plugin installation with the following command: `php tools/upgrade.php upgrade`


# License

Since this plugin uses the CPDF library, make sure to check [its license](https://github.com/coherentgraphics/cpdf-binaries/blob/master/LICENSE) in order to know if your organization can use it.

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2020-2021 Lepidus Tecnologia__

__Copyright (c) 2020-2021 SciELO__