# HTTP request budgets

The plugin keeps the OJS HTTP client's User-Agent. Dataverse installations may require that User-Agent; replacing it with a crawler identity can prevent access.

Requests have a five-second connection limit. Total request limits depend on the operation: reads 15 seconds, dataset creation 30 seconds, file uploads 60 seconds, and other writes (including deletion) 30 seconds. A timeout reports an unknown remote outcome; the deposit workflow must not repeat a mutation just because the response was lost.

Administrators can configure these limits in OJS `config.inc.php`:

```ini
[dataverse]
read_timeout = 15
create_timeout = 30
upload_timeout = 60
write_timeout = 30
```

Values must be integers between 1 and 300 seconds. Invalid values use the defaults. An explicit request timeout passed by a caller takes precedence. Before increasing a budget, verify PHP-FPM, PHP and proxy request limits. Depositing several files is synchronous: the overall request budget must cover their combined duration, not just one upload. Increasing a timeout cannot guarantee successful delivery or prevent process termination.

Transport logs contain the operation, a failure category (`timeout`, `connection`, `authentication`, or `http`) and a status code. They exclude request URLs, bodies, API keys and raw transport messages. Deposit logs add submission/DOI and stage identifiers to correlate a failure with its durable operation. A transport failure alone does not establish repository downtime.
