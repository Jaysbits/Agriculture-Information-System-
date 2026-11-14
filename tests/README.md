# Automated tests

Two PowerShell scripts exercise the running application. They are optional
development aids; the application itself has no test dependencies.

| Script | What it checks |
| --- | --- |
| `smoke.ps1` | Logs in and requests 28 pages (dashboard, CRUD lists/forms/details, all reports, CSV, admin pages) plus logout, expecting HTTP 200 and no PHP error text. |
| `crud.ps1` | Runs 26 integration checks: validation, duplicates, area/date rules, create/update/delete flows, referential-integrity blocking, lookup CRUD, CSV export, staff restrictions and wrong-password rejection. It queries PostgreSQL with `psql` to verify what actually reached the database. |

## Prerequisites

- Windows PowerShell 5.1+ (or PowerShell 7)
- The application running (for example `php -S localhost:8000 -t public`)
- `database/02_seed.sql` loaded; the tests assume the demo data and accounts
- `psql` available for `crud.ps1` (PostgreSQL client tools)

## Running

Start the app, then from the project root:

```powershell
# Page smoke test
powershell -ExecutionPolicy Bypass -File tests\smoke.ps1 -BaseUrl http://localhost:8000

# Full integration test (local PostgreSQL)
powershell -ExecutionPolicy Bypass -File tests\crud.ps1 `
  -BaseUrl http://localhost:8000 `
  -PsqlPath "C:\Program Files\PostgreSQL\18\bin\psql.exe" `
  -DbHost localhost -DbPort 5432 -DbName postgres -DbUser postgres -DbPassword your-password
```

For Supabase, pass the session-pooler host/port and `-DbSslMode require`.
`psql` must be installed locally for `crud.ps1`.

All parameters have defaults that match a local setup (`http://localhost:8000`,
`localhost:5432`, database `postgres`, demo `admin`/`staff` accounts).

> `crud.ps1` creates and then removes test records (contact `09990001111`, farm
> `Test Plot`, crop `Ampalaya`, lookup `Test Soil`). Run it on a development or
> demo database, not on live production data.
