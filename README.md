# DALOY

Duty Allocation & Logistics Operations for Your Workforce — a native PHP nursing workforce management system for the Schools Division Office of Aurora.

## Run locally

Requirements: Apache 2.4, PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, and MariaDB 10.4+ / MySQL 8+. The supplied XAMPP environment meets these requirements. No Composer, npm, external fonts, CDN, or third-party runtime packages are required.

1. Start **Apache** and **MySQL** in XAMPP.
2. Place this project in `C:\xampp\htdocs\daloy`.
3. Copy `config.example.php` to `config.local.php`. Set the database connection. The local default is the `daloy` database on `127.0.0.1`, with XAMPP's root account.
4. From the project directory, run:

   ```powershell
   C:\xampp\php\php.exe bin\install.php
   ```

5. Open **http://localhost/daloy/**. Use the administrator email and randomly generated temporary password printed by the installer. The first sign-in requires a password change.

First sign-in opens a dedicated **Finish account setup** screen. Enter the temporary password you just used, choose a different new password, confirm it, and click **Save password & open dashboard**. The workspace navigation appears after that step; links no longer silently display the Profile page. Your dashboard then provides direct links for adding districts, locations, and personnel.

The installer creates the database and tables, eight Aurora municipalities, configurable duty types and deployment reasons, and a standard day shift. It does not invent official school, district, or employee records. Running it again does not overwrite an existing installation.

To customize the initial administrator:

```powershell
C:\xampp\php\php.exe bin\install.php --email=administrator@example.gov.ph --name="DALOY Administrator"
```

An optional `DALOY_ADMIN_PASSWORD` environment variable supplies the temporary password (12–72 bytes). Otherwise the installer generates one. Passwords are not written to source code or logs by the application.

For a **separate demonstration installation**, point the configuration at a new database and add `--demo`. This creates eight fictional nurses, a supervisor, demonstration locations and duty records. Every sample person/location is marked “Demo”; the workspace displays a demonstration notice. The installer prints the demo credentials. Do not mix this optional dataset with official records.

## First-use workflow

1. **Administrator → Configuration:** add your actual districts/areas and review municipalities, duty types, reasons, and shifts.
2. **Schools & locations:** add schools, offices, and venues. Optionally set their daily coverage targets.
3. **User accounts:** create the Nurse Head and nurse accounts, home assignments, employee numbers, and relevant competencies. Deliver temporary passwords through an approved channel.
4. **Nurse → My schedule:** encode authorized duty. Select a shift or enter custom/overnight times. The form shows live conflicts; the server checks them again when saving.
5. **Head → Activities & events:** create a staffing requirement, destination, interval, reason, required count, and optional competency.
6. **Deployment center:** select the requirement. Review eligible and unavailable nurses, including conflict reasons. Review the assignment details and confirm an individual deployment.
7. **Nurse → Deployment center:** read instructions and acknowledge the assignment. The supervisor can see the recorded acknowledgment time.
8. **Leave & unavailability:** nurses submit requests; the head approves or rejects them. Approved leave affects deployment eligibility. Existing overlapping assignments must be cancelled/reassigned before leave approval.
9. **Reports:** filter and export operational records to Excel, PDF, or a printable view.

## Included modules

| Module | Working behavior |
| --- | --- |
| Authentication | PHP sessions, password hashing, forced temporary-password changes, session regeneration, inactivity timeout, login throttling, logout |
| Role access | Nurse-owned records; head workforce/assignment oversight; administrator account and configuration management |
| Personnel | Employee number, designation, district/location, employment, contact, specialization, competencies, profile photo, active/inactive status |
| Organization | Districts, municipalities, school/office/venue directory, configurable duty categories, deployment reasons, shifts |
| Scheduling | Create/edit/cancel duty, custom and overnight time intervals, live/server conflict validation, date and organizational filters |
| Calendar | Day/week/month, date navigation, nurse/district/location/municipality/duty/status filters, date roster |
| Dashboard | Computed workforce counts, daily roster, coverage targets, upcoming staffing needs, potentially available personnel |
| Availability | Explicit available/off-duty/unavailable intervals, derived duty/deployment/approved-leave status |
| Leave | Submission, protected attachment, head review, approval/rejection history, scheduling impact |
| Activities | Multi-day requirements, required nurse count, organizer, destination, reason, instructions, competency requirement |
| Deployment | Candidate explanations, manual authorization, capacity checks, notifications, acknowledgment, cancellation with reason, retained history |
| Notifications | Personal in-app inbox, unread count, related-record links, mark all as read |
| Reports | Daily/weekly/monthly rosters, individual history, school/district assignments, deployment/activity personnel, availability, leave, conflicts, audit |
| Exports | Actual `.xlsx` ZIP/XML files, paginated `.pdf` documents, browser print / PDF |
| Administration | Account creation/reset/deactivation, catalogs, workspace notice, organization name, audit trail |
| Interface | Native responsive CSS, mobile quick navigation, light/dark preference, keyboard focus indicators, empty/error states |
| Recovery | Consistent database and referenced-attachment backup, checksum-validated restore into an empty database |

## Operational rules

- All times use **Asia/Manila**. Intervals include their start and exclude their end: 08:00–12:00 and 12:00–17:00 do not conflict. Overnight duty uses the following calendar date for the end time.
- A nurse is a potential candidate only when their account is active, no blocking record overlaps the **entire requested interval**, and any specified competency is recorded. Competency matching is a case-insensitive substring search in administrator-managed specialization/skills, not certification verification.
- “Available” means no recorded blocker. It is not a claim that a nurse has confirmed availability. An explicit available record cannot override duty, deployment, approved leave, or an unavailable/off-duty record.
- Day-level dashboard status summarizes the selected interval. A person with part-day duty is counted as scheduled for that day. “On Duty” refers to an assignment currently in progress.
- Coverage is a count of distinct nurses assigned at any point during the selected day. It is not proof of continuous shift coverage; review exact intervals in the calendar.
- Deployment requirements and activities use a shared module. An assignment covers the requirement's full interval. For separate shifts or different dates, create separate requirements.
- All workforce mutations acquire a short database write lock before checking conflicts and staffing capacity. Concurrent requests cannot both pass a stale availability/capacity check.
- Schedules may be edited and their old/new values are audited. Deployments are never overwritten. Cancel and reissue to change a deployment; cancellation preserves original instructions, authorization, and acknowledgment. A requirement with any deployment history cannot be rewritten.
- Changing an account's role is blocked when it has nursing history. Deactivation retains historical records; existing duties are not silently cancelled.
- Catalog records are deactivated rather than deleted. Historical reports remain accessible. Renaming personnel/catalog entries updates the display name used by reports; the audit trail records the previous value.
- Notifications are **in-app**. No email or SMS integration is required or configured.
- Reports cap each source at 5,000 rows; narrow the date interval for larger histories. The interface paginates display rows. Excel preserves Unicode and stores user content as strings, preventing formula execution. The portable direct PDF export transliterates characters supported by its standard font; use Excel or browser Print → Save as PDF for full Unicode fidelity.
- Failed conflicting writes are rejected and are not retained as duty records. The conflict report detects any overlapping records already present (for example, following an external import).
- Supporting uploads accept PDF/JPEG/PNG up to 5 MB; profile photos accept JPEG/PNG and are re-encoded to a maximum 512px width. Attachments require an authenticated owner or manager. Uploads and session files are denied direct HTTP access.

## Tests

Start MariaDB, then run:

```powershell
C:\xampp\php\php.exe tests\run.php
```

The suite creates randomly named `daloy_test_*` databases using the configured database account. It tests domain behavior, permissions, conflict boundaries, overnight schedules, leave workflow, deployments/acknowledgment/history, XLSX/PDF, backup/restore, actual HTTP pages and forms, CSRF, XSS escaping, account activation, and throttling. It starts a temporary PHP server and removes its databases, sessions, and cookies afterward. It does not insert test data into the application database. Test execution requires local database create/drop privileges and the PHP curl extension.

For optional Chrome interaction and visual checks:

```powershell
C:\xampp\php\php.exe tests\run.php --browser
```

Set `DALOY_CHROME` if Chrome is not installed at `C:/Program Files/Google/Chrome/Application/chrome.exe`. Screenshots are saved to `storage/preview/`. The test uses an isolated Chrome profile and only the local test application. It checks desktop/mobile layout overflow, theme switching, mobile navigation, live Fetch conflict feedback, and overnight shift presets.

Lint PHP with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

## Backup and recovery

Create a backup:

```powershell
C:\xampp\php\php.exe bin\backup.php
# Or specify an existing, access-controlled destination directory:
C:\xampp\php\php.exe bin\backup.php C:\Backups\daloy-2026-09-29.zip
```

The default is a uniquely named ZIP in protected `storage/backups/`. It contains a transactionally consistent database snapshot (including password hashes and audit records) and every referenced upload, with SHA-256 checksums. It does not include server credentials, active sessions, browser test data, or application source. Keep the source version and `config.local.php` separately in your controlled recovery inventory. Backup ZIPs contain personal data: encrypt and restrict the off-server storage location. Set retention and schedules according to SDO policy; a scheduler is not automatically installed.

To restore:

1. Put the application in a separate recovery directory / host, with matching source version.
2. Create a **new, empty** MariaDB/MySQL database. Point its `config.local.php` to that database. Do not run the installer first.
3. Run `php bin/restore.php path/to/backup.zip`.
4. Sign in with a backed-up account. Check record counts, calendar dates, deployment acknowledgments, and an attachment before directing users to the recovery installation.

Restore refuses to overwrite any existing tables and validates attachment checksums before importing. A failed restore can leave empty schema tables or unreferenced attachment files; use a newly created empty recovery database for the next attempt. The test suite verifies restore against every table and confirms overwrite refusal.

## Apache / cPanel deployment

- Deploy the project under Apache with `.htaccess` enabled (`AllowOverride All`). It blocks private application directories, configuration, storage, SQL, and hidden files. Do not use PHP's built-in server for public hosting; it is used only by isolated local tests.
- Use HTTPS and set `secure_cookies` to `true` in `config.local.php`.
- Create a dedicated database account. Install with schema privileges, then restrict the runtime account to required CRUD privileges on the DALOY database. Do not use the XAMPP root defaults on a public server.
- Give PHP write access to `storage/`; keep application source and configuration non-writable by the web process. `storage/sessions/` and `storage/uploads/` are created on demand.
- Keep `display_errors` off in production and monitor the PHP error log. The application also disables displayed errors for web requests.
- Verify direct requests to `.git/config`, `config.local.php`, `app/`, `bin/`, `database/`, `tests/`, and `storage/` are denied. Never deploy without equivalent protections on a non-Apache server.
- Account/session timeout defaults to 30 minutes and is configurable. Any password reset/change invalidates existing sessions. An inactive account is rejected on its next request.
- Do not ship demo datasets or temporary credentials into an official installation. Have the Nurse Head verify organizational records and scheduling conventions during user acceptance testing.
- Static files are local and small; enable Apache compression/caching through the hosting configuration if desired. Dynamic authenticated pages are `no-store`.

## Code map

- `index.php`: session authentication, POST action dispatch, role-based pages.
- `api.php`: authenticated live conflict and deployment-candidate data.
- `download.php`, `export.php`: protected files and reports.
- `app/Service.php`: authorization, validation, conflicts, transactional workflows, notifications, audit.
- `app/Database.php`: PDO queries and serialized workforce writes.
- `app/pages/`: functional server-rendered screens.
- `app/Reports.php`, `app/Backup.php`: native exports and recovery.
- `assets/`: native JavaScript and responsive CSS.
- `database/schema.sql`: indexed relational schema.
- `bin/`: CLI installation, backup, and recovery.
- `tests/`: isolated integration and optional real-browser checks.

The implementation intentionally consolidates some recommended entities: roles are validated values on users; nurse profiles are stored on nurse accounts; schools are typed duty locations; activity requirements and deployment requests are activity records; acknowledgments are retained with their deployment. These preserve the specified relationships and workflows without duplicate records.
# Aurora school directory

New installations include 243 directory entries from DepEd's Aurora basic-education masterlist and National Inventory Dashboard, retrieved September 30, 2026. The snapshot covers 267 distinct source IDs, including private-school records. Identical names and addresses are grouped; former names/IDs may remain in the source. This is not a verified list of currently operating institutions or a tertiary-school registry.

For an existing installation, run `C:\xampp\php\php.exe bin/import-aurora-schools.php`. The import is transactional and repeatable, preserves local edits and existing venues, and leaves coverage targets at zero. “Active” controls scheduling availability, not verified operating status. Missing municipalities remain unspecified. School IDs are searchable in each location's address/description.

The checked-in snapshot and source links are in `database/aurora-schools.json`; no external request is needed to browse the directory.

## Multiple schools per nurse

In **User accounts → Edit account**, retain the home school / office and use **Additional assigned schools** to search and select multiple public elementary or high schools. The personnel directory and availability reports include all assignments. Create separate duty schedules for visits to each school; multiple duties on the same day are supported when their times do not overlap.

Existing installations must run `C:\xampp\php\php.exe bin/migrate-nurse-schools.php` once before using this version. It adds the `nurse_locations` table without changing home assignments. New installs include this table automatically. Backups include additional assignments; older backups restore with their original home assignments.

