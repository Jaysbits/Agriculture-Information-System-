# AgriSys — Agriculture Information System

A complete web-based Agriculture Information System for the CTU AP3 midterm project.
It manages farmers, farms, crops, plantings and harvests on a normalized PostgreSQL
database hosted on **Supabase**, with authentication, role-based access, live
dashboards, reports and full CRUD.

Built with **PHP 8.1+ (PDO)**, **Bootstrap 5.3**, **Chart.js** and **PostgreSQL 15+**.
No Composer packages, no build step — upload and run.

---

## Table of contents

1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [Requirements](#requirements)
4. [Local setup](#local-setup)
5. [Deploying the database to Supabase](#deploying-the-database-to-supabase)
6. [Demo accounts](#demo-accounts)
7. [Roles and permissions](#roles-and-permissions)
8. [Project structure](#project-structure)
9. [Database overview](#database-overview)
10. [Reports](#reports)
11. [Testing](#testing)
12. [Security notes](#security-notes)
13. [Troubleshooting](#troubleshooting)
14. [Documentation](#documentation)

---

## Features

| Area | What it does |
| --- | --- |
| **Authentication** | Session login with bcrypt password hashes, CSRF tokens on every form, login throttling (5 failures → 60 s lock), sign-out. |
| **Roles** | `admin` and `staff`. Staff can read/create/update; only admins can delete, manage user accounts and edit lookup tables. |
| **Farmers** | Register, edit, view and delete farmers (name, contact number, address, date registered). |
| **Farms** | Farms belong to a farmer and use controlled soil-type and irrigation-type dropdowns. |
| **Crops** | Crop name, variety, category, season and growing period. |
| **Plantings** | Links a farm and a crop, with area planted, seed quantity, status and expected harvest date. |
| **Harvests** | Records quantity, price per kg, quality grade and remark (1–5) against a planting; estimated value is calculated live. |
| **Dashboard** | 5 live summary cards, 3 harvest totals, 2 charts (plantings by status, harvest volume for the last 6 months) plus recent plantings, recent harvests, upcoming harvests and top crops. |
| **Reports** | 6 report types with date-range and optional filters, summary totals, CSV export and print view. |
| **Search / filter / sort / pagination** | Every list has keyword search, relevant dropdown filters, sortable columns and page navigation. |
| **Validation** | Server-side rules on every field (required, length, format, range, date logic, uniqueness, foreign keys) with inline messages. |
| **Referential integrity** | `ON DELETE RESTRICT` foreign keys; deleting an in-use record is blocked with a friendly message, never a raw SQL error. |
| **Delete confirmation** | Bootstrap modal asks for confirmation before any delete. |
| **Audit fields** | Every table has `created_at` / `updated_at`; core tables also record `created_by` / `updated_by`. |
| **Reference data management** | Admin screen to add, rename and remove 7 lookup values used by the dropdowns. |
| **Responsive UI** | Bootstrap 5 layout with a collapsible sidebar; works on phone, tablet and desktop. |
| **Friendly errors** | Database and unexpected errors are logged to `storage/logs/app.log`; users see a plain-language message. |

---

## Technology stack

| Layer | Choice |
| --- | --- |
| Language | PHP 8.1+ with `declare(strict_types=1)` and PDO |
| Database | PostgreSQL 15+ (Supabase) |
| UI | Bootstrap 5.3 via CDN, Bootstrap Icons, custom `app.css` |
| Charts | Chart.js 4 via CDN |
| JavaScript | Small vanilla `app.js` (sidebar, delete modal, print, filters) |
| Server | PHP built-in server (development) or Apache/Nginx (production) |

There are no Composer or npm dependencies. The only PHP extension required is
`pdo_pgsql`.

---

## Requirements

- PHP **8.1 or newer**
- PHP extensions: `pdo_pgsql`, `mbstring`, `openssl` (normally enabled by default)
- A Supabase project (free tier is fine) **or** any PostgreSQL 15+ server
- A web server or the PHP built-in server

Check your PHP setup:

```bash
php -v
php -m | findstr pdo_pgsql     # Windows
php -m | grep pdo_pgsql        # macOS / Linux
```

---

## Local setup

### 1. Get the project

Copy the folder anywhere, for example `C:\xampp\htdocs\agrisys` or
`/var/www/agrisys`.

### 2. Create the environment file

```bash
copy .env.example .env      # Windows
cp .env.example .env        # macOS / Linux
```

Open `.env` and fill in the Supabase connection values (see the next section).

### 3. Create the database

In the Supabase **SQL Editor**, run `database/01_schema.sql`, then
`database/02_seed.sql`. Details are in
[Deploying the database to Supabase](#deploying-the-database-to-supabase).

### 4. Start the application

From the project root:

```bash
php -S localhost:8000 -t public
```

Open <http://localhost:8000> and sign in with a demo account.

> The document root must be `public/`, never the project root, so that `.env`,
> `config/` and `src/` cannot be requested directly.

### Testing on localhost with a local PostgreSQL

You do not need Supabase to try the app. Any PostgreSQL 15+ server works — point
`.env` at it instead:

```dotenv
DB_HOST=localhost
DB_PORT=5432
DB_NAME=agrisys
DB_USER=postgres
DB_PASSWORD=your-local-password
DB_SSLMODE=prefer
```

Then create the database and load the SQL:

```bash
createdb -h localhost -U postgres agrisys
psql -h localhost -U postgres -d agrisys -f database/01_schema.sql
psql -h localhost -U postgres -d agrisys -f database/02_seed.sql
```

> The repository's current `.env` points at a **local test database** so the app
> can be run and verified offline. Replace those values with your Supabase
> credentials before deploying (see the next section).

The bundled test harness (`tests/smoke.ps1`, `tests/crud.ps1`) also accepts
`-BaseUrl`, `-DbPort`, `-DbName`, `-DbUser` and `-DbPassword` so you can point
it at whichever database you are testing.

---

## Deploying the database to Supabase

1. **Create a project** at <https://supabase.com/dashboard>. Wait until the
   database is ready (about 2 minutes).
2. Open **SQL Editor → New query**.
3. Paste the entire contents of `database/01_schema.sql` and click **Run**.
   This creates 13 tables, 2 views, indexes, triggers and constraints.
   *Warning: the script drops existing AgriSys tables first, so only run it on a
   fresh project or when you intend to reset the data.*
4. Open a new query, paste `database/02_seed.sql` and click **Run**. This loads
   the 7 lookup tables, 2 demo accounts and sample records.
5. Open **Project Settings → Database → Connection string** and copy the
   connection details. Use the **Session pooler** (port `5432`) or the direct
   connection:

   | Setting | Where to find it | Example |
   | --- | --- | --- |
   | `DB_HOST` | Session pooler host | `aws-0-ap-southeast-1.pooler.supabase.com` |
   | `DB_PORT` | Session pooler port | `5432` |
   | `DB_NAME` | Database name | `postgres` |
   | `DB_USER` | Pooler user | `postgres.abcdefghijklmnop` |
   | `DB_PASSWORD` | Your database password | *(secret)* |
   | `DB_SSLMODE` | Always TLS on Supabase | `require` |

6. Paste those values into `.env`, for example:

   ```dotenv
   DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
   DB_PORT=5432
   DB_NAME=postgres
   DB_USER=postgres.abcdefghijklmnop
   DB_PASSWORD=your-real-password
   DB_SSLMODE=require

   APP_BASE_URL=
   APP_TIMEZONE=Asia/Manila
   ITEMS_PER_PAGE=10
   ```

7. Load the site. If the connection works you will see the login page. If it
   does not, the page shows a friendly "Database is not configured yet"
   message and the exact cause is written to `storage/logs/app.log`.

### Deploying the PHP files

Upload everything **except** `.env`, `storage/` and `database/` to your host and
point the web root at `public/`. Create `.env` on the server with the production
values. On shared hosting with cPanel, set the document root to
`public` or move the contents of `public/` into `public_html` and keep
`config/`, `src/` and `.env` one level above it.

> **Do not use the transaction pooler (port 6543).** The application sets a
> session variable for audit stamps and uses prepared statements, which need a
> session-scoped connection.

---

## Demo accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | Administrator |
| `staff` | `staff123` | Staff |

**Change these passwords before submitting or deploying.** Sign in as `admin`,
open **User Accounts**, edit each account and set a new password.

---

## Roles and permissions

| Capability | Admin | Staff |
| --- | :-: | :-: |
| View dashboard, lists and record details | ✅ | ✅ |
| Create farmers, farms, crops, plantings, harvests | ✅ | ✅ |
| Update records | ✅ | ✅ |
| Delete records | ✅ | ❌ |
| View / export / print reports | ✅ | ✅ |
| Manage user accounts | ✅ | ❌ |
| Manage lookup values | ✅ | ❌ |

Staff who open an admin page directly are redirected with a "not allowed"
message; delete endpoints also verify the role server-side.

---

## Project structure

```text
.
├── .env.example              Environment template (copy to .env)
├── .gitignore
├── README.md                 This file
├── database/
│   ├── 01_schema.sql         Tables, views, indexes, triggers, constraints
│   └── 02_seed.sql           Lookup values, demo accounts and sample data
├── config/
│   └── config.php            Env loading, error handling, session, constants
├── src/                      Application classes (outside the web root)
│   ├── Database.php          PDO singleton, raw SQL helpers + generic table API
│   ├── Auth.php              Login, logout, throttling, role checks
│   ├── Validator.php         Server-side validation rules
│   ├── helpers.php           e(), url(), flash, CSRF, pagination, formatting
│   └── Models/               MODEL layer — one file per entity (all SQL lives here)
│       ├── Model.php         Base model (generic CRUD, pagination, ordering)
│       ├── Farmer.php        Farmer CRUD + queries
│       ├── Farm.php          Farm CRUD + dropdown options
│       ├── Crop.php          Crop CRUD + categories/seasons/options
│       ├── Planting.php      Planting CRUD + listing, statuses, filter options
│       ├── Harvest.php       Harvest CRUD + listing, grades, remarks
│       ├── AppUser.php       User accounts + last-admin/self-delete guards
│       ├── Lookup.php        Lookup-table registry + add/update/delete
│       ├── Dashboard.php     All dashboard queries
│       └── Report.php        All report queries
├── public/                   Web document root — VIEW layer (HTML only)
│   ├── index.php             Dashboard view
│   ├── login.php / logout.php
│   ├── reports.php           Reports view (6 reports + CSV + print)
│   ├── lookups.php           Admin lookup-table view/controller
│   ├── farmers/              index / form / view / delete
│   ├── farms/
│   ├── crops/
│   ├── plantings/
│   ├── harvests/
│   ├── users/                Admin account management
│   ├── partials/             header / footer / delete modal
│   └── assets/               app.css, app.js
├── docs/
│   └── PROJECT-DOCUMENTATION.md
├── tests/
│   ├── smoke.ps1             28-page smoke test
│   ├── crud.ps1              26-check integration test
│   └── README.md
└── storage/logs/             app.log (created at runtime, not committed)
```

Every module follows the same four-page pattern:

- `index.php` — searchable, filterable, sortable, paginated list
- `form.php` — shared create/edit form (`form.php` or `form.php?id=N`)
- `view.php` — detail page with related records and actions
- `delete.php` — POST-only, admin-only delete endpoint

### Models and views are separated

Database logic never lives in a page. Each entity has a **model** in
`src/Models/` that owns every query for that entity, and the pages in `public/`
are **views/controllers** that validate input, call the model and render HTML.

Models are used as **objects**. `Model.php` is an abstract base class that
provides generic CRUD — `all()`, `find()`, `create()`, `update()`, `delete()`
and `count()` — backed by the table-oriented API on `Database` (`select`,
`find`, `insert`, `update`, `delete`). Entity models extend it:

```php
$farmer = new Farmer();
$farmer->all(['address=ilike.%Cebu%'], ['order' => 'last_name']);
$farmer->find(3);
$farmer->create($data);
$farmer->update(3, $data);
$farmer->delete(3);
```

The filter strings use the same PostgREST-style syntax as Supabase:
`column=eq.value`, `column=ilike.%x%`, `column=in.(1,2,3)`, `column=is.null`
and `or=(a.ilike.%x%,b.ilike.%x%)`.

- A model exposes `find()`, `save()`, `delete()` and its own list/lookup helpers.
- `save()` returns `['ok' => bool, 'id' => ?int, 'errors' => [], 'dbError' => ?string]`.
- `delete()` returns `['ok' => bool, 'deleted' => bool, 'error' => ?string]`.
- Pages handle the flash message and redirect; the model never redirects.

Each file's header comment states its role — `MODEL —`, `VIEW —`,
`VIEW / CONTROLLER —` or `CONTROLLER —` — so the layer is obvious at a glance.
Dashboard and report SQL also live in models (`Dashboard.php`, `Report.php`),
so `src/Models/` is the single place that talks to the database.

---

## Database overview

13 tables, split into **authentication**, **lookup/reference** and **core** groups.
The full data dictionary and normalization proof are in
[`docs/PROJECT-DOCUMENTATION.md`](docs/PROJECT-DOCUMENTATION.md).

**Core tables**

- `farmer` → `farm` → `planting` → `harvest`
- `crop` is referenced by `planting`

**Lookup tables** (the label lives here; core tables store only the numeric id)

- `soil_type`, `irrigation_type` → used by `farm`
- `crop_category`, `season` → used by `crop`
- `planting_status` → used by `planting`
- `quality_grade`, `harvest_remark` → used by `harvest`

**Authentication**

- `app_user` (bcrypt password hash, role, active flag, last login)

**Highlights**

- `created_at` / `updated_at` on all tables, maintained by a trigger
- `created_by` / `updated_by` on all core tables, filled from the session
- `v_planting_detail` and `v_harvest_detail` join views power lists, dashboard
  and reports
- `ON DELETE RESTRICT` protects referenced records
- Triggers enforce: planted area ≤ farm size, farm size ≥ already-planted area,
  harvest date ≥ planting date, expected harvest ≥ planting date
- Harvest remark ids `1..5` match the values required by the project spec

---

## Reports

All reports accept a date range and support **CSV export** and **print**.

1. **Farmers and their farms** — registration date range, farm counts, area and
   harvest totals per farmer.
2. **Crops planted per farm** — optional farm filter, plantings, area and
   harvested quantity per crop.
3. **Planting records by status** — optional status filter, counts and area.
4. **Harvest records** — optional grade filter, quantity, value and remarks.
5. **Total harvested quantity** — quantity by crop and by farmer.
6. **Estimated harvest value** — value by crop and by farmer.

CSV files include a UTF-8 BOM so Excel opens them correctly. The print view
hides navigation and keeps the summary cards and tables.

---

## Testing

The project was tested on PHP 8.2 against PostgreSQL 18 and Supabase-compatible
SQL.

- **Lint:** `php -l` passes on all 46 PHP files.
- **Page smoke test:** 28 URLs (dashboard, every list/form/detail, all reports,
  CSV export, admin pages) return HTTP 200 with no PHP warnings.
- **Integration test:** 26 checks covering validation, duplicate rejection,
  area/date rules, create/update/delete flows, referential-integrity blocking,
  lookup CRUD, CSV export, staff restrictions, CSRF-protected login and wrong
  password rejection — all pass.

The detailed test matrix and results are in
[`docs/PROJECT-DOCUMENTATION.md`](docs/PROJECT-DOCUMENTATION.md#13-testing).
The reproducible harness lives in [`tests/`](tests/README.md): `smoke.ps1`
(28 pages) and `crud.ps1` (26 integration checks).

---

## Security notes

- Passwords are stored with `password_hash()` (bcrypt) and checked with
  `password_verify()`; plain-text passwords are never stored.
- Every POST form includes a CSRF token; tokens are compared with
  `hash_equals()`.
- Login sessions use `HttpOnly`, `SameSite=Lax` cookies and regenerate the
  session id after login. Cookies switch to `Secure` automatically on HTTPS.
- All SQL uses PDO prepared statements, including filters and sorts (sort
  directions and column names are whitelisted).
- All output is escaped with `e()` (`htmlspecialchars`).
- Error details go to the log; users only see friendly messages.
- Delete endpoints are POST-only and admin-only.
- The application root (`config/`, `src/`, `.env`, `storage/`) sits outside the
  document root. Apache users also get `public/.htaccess` hardening headers.

---

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| "Database is not configured yet" | `.env` is missing or placeholders are still in place. Copy `.env.example` and fill it in. |
| "could not find driver" | Enable `pdo_pgsql` in `php.ini` and restart PHP/Apache. |
| "Connection refused" or timeout | Wrong host/port, Supabase project paused, or the pooler host was mistyped. |
| SSL error | Set `DB_SSLMODE=require` for Supabase. |
| Login says "Too many failed attempts" | Wait 60 seconds; the throttle locks the session after 5 failures. |
| Deleted record but page still shows it | The database rejected the delete because other records reference it; the message explains what to remove first. |
| Blank page / 500 | Check `storage/logs/app.log`; the log has the exact error. |

---

## Documentation

- [`docs/PROJECT-DOCUMENTATION.md`](docs/PROJECT-DOCUMENTATION.md) — objectives,
  requirements traceability, ERD, UNF→1NF→2NF→3NF normalization, data dictionary,
  constraints, testing results, user guide and deployment steps.
- `database/01_schema.sql` — commented schema.
- `database/02_seed.sql` — lookup values and demo data.
