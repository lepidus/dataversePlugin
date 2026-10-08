# Deposit recovery validation

Target: OJS 3.4.x. Core revision used: `f4292be3f117fb84fc9b321c10bba7ac9671d1d7`. Validation performed on 2026-09-29 in an isolated Docker network with fresh databases; no production records or repository files were changed.

| Runtime | Database | Focal suite |
| --- | --- | --- |
| PHP 8.0 | MariaDB 10.11 | 34 tests, 71 assertions passed |
| PHP 8.2 | MariaDB 10.11 | 34 tests, 71 assertions passed |
| PHP 8.2 | PostgreSQL 16 | 34 tests, 71 assertions passed |

The suite contains `tests/deposit`, `DraftDatasetFilesValidatorTest`, `DataverseActionsTest`, `DatasetFileActionsTest`, and `SubmissionDatasetFactoryTest`. It runs through the core PHPUnit bootstrap and vendor PHPUnit. Both install migrations are applied before the tests; the database fixtures provide journal and user 1. The plugin locale directory is registered for translation tests.

Coverage includes one creation claim, rejection of stale revisions, retirement followed by a new intent, unknown create/upload results, ZIP receipts with multiple remote IDs, retry after local finalization failure, changed file selection, missing temporary sources, metadata preview, and README-only selections. Database tests exercise the real migrations and persistence on both database families. Transport tests use Guzzle's mock handler; they do not contact Dataverse.

PSR-12, syntax and compilation of changed `locale.po` files pass. An independent review was completed and its findings were corrected and covered by regressions. The validation contract is `validation-deposits.json`; the GitLab pipeline is a separate required gate after push. A complete browser/HTTP submission against a live Dataverse was not performed. Remote outcome reconciliation remains an administrative procedure described in `deposit-recovery.md`.
