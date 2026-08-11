# AGENTS.md

This file provides guidance to coding agents (Claude Code and others) when working with code in this repository.

## What This Repository Is

The **Dataverse plugin** — a PKP generic plugin for OJS and OPS that integrates the editorial workflow with a
[Dataverse](https://dataverse.org/) repository. Authors deposit research data alongside their manuscript
during submission; the dataset stays reachable through the whole workflow (peer review, editorial decisions)
and is linked to the published article/preprint.

It is developed by Lepidus Tecnologia and sponsored by SciELO. The canonical remote is Lepidus' GitLab;
GitHub is a mirror used for releases and the PKP Plugin Gallery.

The plugin is not runnable on its own: it must sit inside a host PKP application at
`plugins/generic/dataverse` (the directory **must** be named `dataverse`). Compatible application versions
are declared in `version.xml` and the README — check those rather than assuming.

## Commands

All commands run from the **host application root**, not from the plugin directory.

```bash
# Create/refresh the plugin's DB tables
php tools/upgrade.php upgrade

# All plugin unit tests
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml \
  plugins/generic/dataverse/tests

# A single test class
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml \
  plugins/generic/dataverse/tests/factories/JsonDatasetFactoryTest.php

# Cypress (headless / interactive)
npx cypress run  --config specPattern=plugins/generic/dataverse/cypress/tests
npx cypress open --config specPattern=plugins/generic/dataverse/cypress/tests

# One Cypress spec
npx cypress run --config specPattern=plugins/generic/dataverse/cypress/tests/Test1_submissionWizard.cy.js
```

Prerequisites for the E2E suite:

- `cypress.env.json` at the application root. The keys the specs actually read are `dataverseUrl`,
  `dataverseApiToken` and `dataverseTermsOfUse`, plus the host application's own `baseUrl` and
  `contextTitles` (used to branch between OJS and OPS). The specs hit a **real Dataverse instance**
  (e.g. `https://demo.dataverse.org/dataverse/<alias>`) — there is no mock or fixture server.
- `dataverseCustomRequiredMetadataFieldsUrl` is optional but load-bearing: it must point at a collection whose
  required metadata fields are customized. Without it `Test7_customRequiredMetadataFields` **silently
  self-skips** (it logs a skip message and passes), so a green run does not mean that feature was covered.
  `README.md` also lists `dataverseAdditionalInstructions`, but no spec reads it.
- Specs are numbered and stateful: `Test0_pluginConfiguration` configures the plugin, later specs depend on it.
- Application and OS locale must be `en` — assertions match English UI strings.

`config.inc.php` must define `security.api_key_secret`; without it the settings form renders
`emptySecretKey.tpl` and the plugin cannot be configured.

## Feature Surface

What the plugin actually does, in workflow order:

1. **Configuration** (`DataverseSettingsForm`, modal from the plugin list): Dataverse collection URL, API
   token, localized terms of use, localized additional instructions, and — OJS only — *when* the dataset gets
   published (`DATASET_PUBLISH_SUBMISSION_ACCEPTED` vs `DATASET_PUBLISH_SUBMISSION_PUBLISHED`). Saving
   validates the credentials against the live server.
2. **Submission wizard**: a "Research Data" upload section on the Files step (draft dataset files), dataset
   metadata fields on the Details/Editors step (subject, license, language, relation type, plus any fields the
   target collection marks as required), a data availability statement, and a summary of all of it on the
   Review step. Each of these adds its own `Submission::validateSubmit` rules.
3. **Deposit**: on the `SubmissionSubmitted` event, `DatasetDepositOnSubmission` creates the dataset in
   Dataverse (as a draft), uploads the draft files, and records a `DataverseStudy` linking the submission to
   the new persistent ID. It bails out silently unless three conditions hold: the publication's
   `dataStatementTypes` includes `DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED`, the assembled `Dataset` has at
   least one file, and it has a subject. A submission that "should have deposited but didn't" almost always
   failed one of these, not the API call.
4. **Workflow "Research Data" tab**: view/edit dataset metadata, add and delete files, delete the dataset, or
   associate the submission with a dataset that already exists in the repository.
5. **Data availability statement tab**: a publication-level statement whose type is one of the
   `DataStatementService::DATA_STATEMENT_TYPE_*` constants (in manuscript / available in a repository /
   submitted to this Dataverse / available on demand / publicly unavailable), with URLs and reasons as needed.
6. **Peer review**: editors pick which data files reviewers may see when sending to external review
   (`SelectDataFilesForReviewForm`, stored on the submission as `selectedDataFilesForReview`); reviewers see
   those files in the review steps, optionally only after accepting the review.
7. **Editorial decisions** (`ProcessDataverseDecisionsActions` + decision-step forms): *external review* adds
   the file-selection step, *accept* can publish the dataset, *decline* offers to delete it.
8. **Publication**: on `Publication::publish` the dataset is published (when configured that way), and the
   public article/preprint page shows the dataset citation. Crossref deposits get a `relations` program with
   an `inter_work_relation` node (`relationship-type="isSupplementedBy"`), whose `identifier-type` is `doi`
   for datasets deposited by the plugin and `url` for externally hosted ones.
9. **Report**: a CSV report of submissions with research data, exposed as a report sub-plugin.
10. **Token expiration warning**: a scheduled task that emails site administrators, journal/server managers and
    the context contact address at fixed intervals (4, 3, 2 and 1 weeks, then 1 day) before the API token expires.

## Architecture

### Registration is gated

`DataversePlugin::register()` always registers `DataverseConfigurationDAO`, but wires the dispatchers and the
report sub-plugin only after passing three gates, in order:

1. `Application::isUnderMaintenance()` — returns early, nothing else is registered;
2. a non-null request context — site-level pages (admin area, site index) get no plugin behaviour;
3. `hasConfiguration($contextId)` — no Dataverse URL/token saved for that context, no hooks.

Any of the three explains a "the plugin does nothing" report; check them before suspecting the hooks
themselves. Gate 2 in particular means plugin code must not assume a context exists.

### Dispatchers are the hook layer

Every hook registration lives in `classes/dispatchers/`. Each class extends `DataverseDispatcher`, whose
constructor calls the abstract `registerHooks()` — instantiating the class *is* registering it. The list of
dispatchers is hard-coded in `DataversePlugin::loadDispatcherClasses()`, so a new dispatcher must be added
there. Roughly one dispatcher per surface:

| Dispatcher | Surface |
| --- | --- |
| `DraftDatasetFilesDispatcher` | Wizard Files step — research data uploads before deposit |
| `DatasetMetadataDispatcher` | Wizard dataset metadata fields |
| `DataStatementDispatcher` | Data availability statement: publication schema, wizard, public page |
| `DataStatementTabDispatcher` | Data statement tab in the workflow |
| `DatasetTabDispatcher` | "Research Data" tab in the workflow |
| `DatasetReviewDispatcher` | Research data shown to reviewers in the review steps |
| `DatasetInformationDispatcher` | Dataset citation on the public article/preprint page |
| `CrossrefDispatcher` | Dataset relation injected into Crossref export XML (`CrossrefXmlEditor`) |
| `DataverseEventsDispatcher` | Everything else: submission schema, API routing, decision steps, publish/delete deposit, component handlers, scheduled task |

`DataverseDispatcher` also provides `getApiUrl()`, used to hand plugin API endpoints to the JS.

### Dataverse API layer

`dataverseAPI/` is the only code that talks to Dataverse. `DataverseClient` is a thin factory for three action
classes — `DataverseCollectionActions`, `DatasetActions`, `DatasetFileActions` — each with an interface in
`actions/interfaces/` and all extending `DataverseActions`, which resolves server URL, API token and
collection alias from `DataverseConfigurationDAO` for the current context, uses the application's Guzzle
client, and caches expensive lookups (required metadata, licenses, root collection name) through
`CacheManager`. It builds both **Native API** (`/api/…`) and **SWORD v2**
(`/dvn/api/data-deposit/v1.1/swordv2/…`) URIs.

Failures throw `DataverseException`. Callers are expected to catch it and degrade gracefully — an unreachable
Dataverse should produce a notice or a disabled tab, never a fatal error. `packagers/NativeAPIDatasetPackager`
serializes a `Dataset` into the JSON the Native API expects; `search/DataverseSearchBuilder` builds search queries.

### Entities, factories, repositories

- `classes/entities/` — plain `DataObject`s (`Dataset`, `DatasetFile`, `DatasetAuthor`, `DatasetContact`,
  `DatasetIdentifier`, `DatasetRelatedPublication`, `DataverseCollection`, `DataverseResponse`). These mirror
  Dataverse's model and are **not** persisted locally.
- `classes/factories/` — build a `Dataset` from a source. `JsonDatasetFactory` from a Dataverse API response,
  `SubmissionDatasetFactory` from a submission (its constructor takes the `Submission` only; the publication is
  passed separately to `getDatasetRelatedPublication()`, and the draft-file repository is swappable via
  `setDraftDatasetFileRepo()` for tests). Both extend `DatasetFactory` and implement `sanitizeProps()`;
  `getDataset()` is final. New dataset sources should be new factories.
- `classes/dataverseStudy/` and `classes/draftDatasetFile/` — the only two locally persisted entities, each
  with `DAO` + `Repository`, reached through `classes/facades/Repo` (extends the application `Repo`, adding
  `Repo::dataverseStudy()` and `Repo::draftDatasetFile()`). Import the plugin's `Repo` facade whenever you
  touch a plugin repository — since it extends the application facade it covers core repositories too, so it
  is the safe default. (`NotifyDataverseTokenExpiration` imports `APP\facades\Repo` directly because it only
  uses core repositories.)

`DataverseStudy` is the local link between a submission and its deposited dataset (`persistentId`,
`persistentUri`, SWORD edit/statement URIs). `Repo::dataverseStudy()->getBySubmissionId()` returning null is
the canonical "this submission has no dataset" test, used all over the dispatchers.

`DraftDatasetFile` is a file uploaded during the wizard but not yet deposited; its properties are described by
`schemas/draftDatasetFile.json`, loaded via the `Schema::get::draftDatasetFile` hook.

Despite the name, `DraftDatasetFilesValidator` does **not** validate that schema. It holds two wizard business
rules: `galleyContainsResearchData()` (compares galley files against dataset files by size + MD5, to warn when
research data was also uploaded as a manuscript file) and `datasetHasReadmeFile()` (looks for a PDF or plain
text file whose name contains a readme keyword — `readme`, `leiame`, `leia-me`, `leame` — and prunes draft
records whose temporary file has vanished). Schema/field validation belongs in the schema JSON and the
component form classes, not here.

Both tables (`dataverse_studies`, `draft_dataset_files`) are created by
`classes/migrations/DataverseMigration`. `upgrade.xml` lists migrations applied to existing installs.

### Services

`classes/services/` (`DatasetService`, `DatasetFileService`, `DataStatementService`) orchestrate API calls,
local persistence and side effects. The abstract `DataverseService` base gives them `registerEventLog()` and
`registerAndNotifyError()` — an error becomes a trivial notification for the user, a submission event log
entry, and an `error_log('Dataverse API error: …')` line. Keep that trio consistent when adding operations.

### Plugin REST API

Three handlers under `api/v1/`: `datasets`, `dataverse`, `draftDatasetFiles`. They are not registered through
the application's normal API routing — `DataverseEventsDispatcher::setupDataverseAPIHandlers()` intercepts the
request, matches the path, and installs the plugin handler. The JS calls these endpoints with URLs produced by
`DataverseDispatcher::getApiUrl()`. Role restrictions are declared per endpoint inside each handler (reviewers,
for example, may download dataset files but nothing else).

### Front end

No build step and no `package.json`. `js/` holds plain scripts registered by dispatchers via
`$templateMgr->addJavaScript()`; they extend the globals the host application exposes
(`pkp.controllers.WorkflowPage`, `pkp.Vue.component(...)`, `pkp.controllers.Container.components.PkpForm`) and
call the plugin API with jQuery `$.ajax`. Smarty templates live in `templates/`, stylesheets in `styles/`,
and the PHP-side form / list-panel definitions in `classes/components/` (`forms/`, `listPanel/`) — a UI change
usually touches a component class, a template, and a JS file together.

### Report sub-plugin

`report/DataverseReportPlugin` is a `ReportPlugin` registered from the main plugin's `register()`. It builds a
CSV of submissions with research data through `DataverseReportService` and `DataverseReportQueryBuilder`.

### Scheduled task

`classes/tasks/NotifyDataverseTokenExpiration`, declared in `scheduledTasks.xml`, runs daily and emails when
today matches exactly 4, 3, 2 or 1 weeks — or 1 day — before the token expiry date reported by Dataverse.
Recipients are site administrators, the context's default manager user group, and the context contact address,
de-duplicated by email.

`emailTemplates.xml` registers `DATAVERSE_TOKEN_EXPIRATION` (and `DATASET_DELETE_NOTIFICATION`), but the task
only takes the **subject** from that template — the body is rendered directly from the
`emails.dataverseTokenExpiration.body` locale string with `contextName`, `dataverseName` and
`keyExpirationDate` parameters. Editing the installed email template in the UI will not change the body.

### Secrets

The API token is stored encrypted in the application database. `classes/DataEncryption` derives an
`aes-256-cbc` key by SHA-256'ing `security.api_key_secret` from `config.inc.php` and uses Laravel's
`Encrypter`. Never log, echo, or write a decrypted token to a template.

## Tests

`tests/` mirrors the source layout (`tests/factories/`, `tests/dataverseAPI/actions/`, `tests/dispatchers/`, …)
and runs under the PKP PHPUnit harness. The action classes take `(?DataverseConfiguration, ?Client)` — in that
order — precisely so tests can construct them without a request context: pass a hand-built
`DataverseConfiguration` first, and where a test exercises HTTP, a Guzzle `Client` backed by a `MockHandler`
second. Tests that only cover pure helpers pass a bare `Client` and never issue a request. Elsewhere
`DataverseClient` and the action classes are replaced with PHPUnit doubles. Response payloads and Crossref XML
live in `tests/fixtures/`, including `expected/` files for XML comparisons.

Since 3.5 nothing may be persisted against a hard-coded context id: `submissions` and `plugin_settings` have
foreign keys to the context table. Tests that touch the database create their own context through the
`CreatesTestContext` trait (`tests/helpers/`) and drop it in `tearDown`. The suite must pass on **both** OJS
and OPS, so tests never name an application-specific class directly — resolve the context DAO through
`Application::getContextDAO()` (or branch on `Application::get()->getName() === 'ojs2'`) instead of
`JournalDAO`/`Journal`. Running the suite in OPS only requires the plugin directory to be reachable at
`plugins/generic/dataverse` of the OPS installation (a symlink works) and `tools/upgrade.php upgrade` to have
created the plugin tables there.

`cypress/` holds the E2E suite plus plugin-specific commands in `cypress/support/commands.js`
(`findSubmission`, `waitDatasetTabLoading`, `waitDataStatementTabLoading`, …) that wrap the PKP base commands.

## Release mechanics

Pushing a `v*` tag triggers `.github/workflows/generate-package.yml`, which **fails** unless:

- `version/application` in `version.xml` is `dataverse`;
- the tag, with the leading `v` stripped, is a **prefix of** `version/release` (the check is
  `[[ $release != $tag* ]]`). Tag `v3.4.5` passes for release `3.4.5.2`; tag `v3.4.5.2.1` fails;
- `version/date` equals the day the workflow runs.

Update `version.xml` (release **and** date) in the commit you tag. The generated tarball excludes `tests/`
and `cypress/`. GitLab CI pulls shared job templates from Lepidus' `modelosparaintegracaocontinua` project.

## Conventions

- Namespaces are rooted at `APP\plugins\generic\dataverse\…` and mirror the directory layout.
- Locale files are gettext `.po` under `locale/<locale>/` (`locale.po`, `emails.po`) with keys prefixed
  `plugins.generic.dataverse.`. Add every new string to all shipped locales.
- User-facing behaviour changes should update `README.md` and its translations in `docs/`.
- The plugin runs on both OJS and OPS. Journal-only behaviour branches on
  `Application::get()->getName() == 'ojs2'` (see `DataverseSettingsForm` and the publish-timing setting), and
  article/preprint variants are registered side by side (`Templates::Article::Details` and
  `Templates::Preprint::Details`, `articlecrossrefxmlfilter::execute` and `preprintcrossrefxmlfilter::execute`).
  Anything added for one application should be considered for the other.
