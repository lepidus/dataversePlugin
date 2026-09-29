# Research data package validation

The submission validator requires a readable, non-empty README in PDF or plain text format and at least one separate non-empty data candidate. It resolves every draft's temporary file using its file ID and owner ID; a missing temporary file cannot satisfy either requirement and does not cause the draft record to be deleted.

The data check uses the stored bytes, not only the submitted name or file count. A standalone regular file counts when it is readable, non-empty, and its name does not contain `readme`, `leiame`, `leia-me`, or `leame` (case-insensitive). A ZIP archive is detected by its signature even if it has another extension. The ZIP counts only when it has a readable, non-empty regular member whose basename does not contain those README terms. Directories, symlinks and other special entries, empty members, and README members do not count. The outer archive's name does not decide whether its contents count: `README.zip` containing `data.csv` can qualify, while `data.zip` containing only README files cannot.

The validator reads at most the first 512 uncompressed bytes of a ZIP member. It never extracts members or follows archive paths. It limits inspection to 10,000 entries. Invalid ZIPs, files named `.zip` that are not ZIPs, and ZIPs that cannot be opened or read fail closed. Nested ZIPs and other compressed packages do not count as data candidates because their contents are not inspected. TAR, GZIP, RAR, 7z, BZIP2 and XZ files are excluded by extension or signature, including when nested inside a ZIP. A ZIP containing one of those packages may still qualify if it also has a direct non-empty data file.

This is a structural presence check. It does not validate scientific relevance, file format semantics, completeness, checksums, or every byte of an archive member. Unsupported compressed scientific formats need a direct uncompressed data file or a supported ZIP containing direct data files to satisfy the rule. No archive content is uploaded, extracted, or modified by this validation.

## Validation evidence

Validated on 2026-09-29 through the real OJS PHPUnit bootstrap in isolated Docker containers. The focal suite includes the deposit regressions and the seven package-content tests.

| Runtime | Database | Result |
| --- | --- | --- |
| PHP 7.3 | MariaDB 10.11 | 41 tests, 115 assertions |
| PHP 7.4 | PostgreSQL 16 | 41 tests, 115 assertions |
| PHP 8.2 | MariaDB 10.11 | 41 tests, 115 assertions |

The ZIP extension was installed only in the PHP 8.0 laboratory container; production was not modified. An independent review corrected the empty-header race, and the affected suites passed again. Syntax, PSR-12, and compilation of the three changed locales pass. The remote GitLab pipeline remains a separate required gate after push; `validation-package-content.json` records that requirement.
