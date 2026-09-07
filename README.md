# PromoMonster

**Real People. Real Answers.**

A consumer research panel. Businesses find out what real people think of their
website, content and ad creative; panel members get paid for honest opinions.

Planning and build documentation is in [`docs/`](docs/) — start with
[`docs/00-execution-plan.md`](docs/00-execution-plan.md).

## Stack

PHP 8.1+ · MySQL / MariaDB · plain CSS. **No Composer, no Node, no build step.**
It deploys by uploading files, so it runs on the shared hosting you already
have. See [`DEPLOYMENT.md`](DEPLOYMENT.md).

## Layout

```
public/          document root — the ONLY web-reachable directory
  index.php      front controller
  .htaccess      rewrites, security headers, no directory listing
  assets/css/
app/             application code, kept out of the web root
  bootstrap.php  autoloader, session, config
  Support/       Router, Database, View, Csrf, Validator, RateLimiter, Request
  Controllers/
  Views/
database/
  migrate.php       applies pending migrations, tracked so re-runs are safe
  migrations/       001 = Phase 0; 002-005 = Phase 1 platform schema
  full-schema.sql   every migration in one file, for phpMyAdmin import
docs/            business plan and product specs
```

## What's built

Phase 0 per the execution plan — the public site and waitlist capture:

- Home, `/business`, `/earn`, `/services/content`, `/services/social`
- Waitlist capture writing to MySQL, with CSRF protection, server-side
  validation, a honeypot, and a database-backed per-IP rate limiter
- One signup per email per side; a repeat submission is treated as success
  rather than shown as an error

The members area, admin area and study tooling are **Phase 1**, gated behind 20
hand-sold studies — see [`docs/00-execution-plan.md`](docs/00-execution-plan.md) §3.

## Database

Two ways to build it:

```bash
php database/migrate.php          # tracked, idempotent, safe to re-run
```

or, with no SSH access, import [`database/full-schema.sql`](database/full-schema.sql)
through phpMyAdmin's SQL tab.

`001` is all Phase 0 needs. `002`–`005` are the Phase 1 platform schema — users,
campaigns, task slots, the double-entry ledger, payouts, fraud and audit — and
are harmless to create ahead of time.

**Requires MySQL 8.0+ or MariaDB 10.6+.** The task-slot claim uses
`SELECT ... FOR UPDATE SKIP LOCKED`; without it, two panel members can be handed
the same slot. `migrate.php` checks the server version and warns.

## Local development

```bash
cp app/config.example.php app/config.php    # fill in your MySQL credentials
php database/migrate.php
php -S 127.0.0.1:8080 -t public public/index.php
```

## Conventions

- **Every query is a prepared statement.** `Database::run()` takes bound parameters; never interpolate into SQL.
- **Every dynamic value in a template goes through `View::e()`.** No exceptions.
- **Every POST form carries `Csrf::field()`** and its handler calls `Csrf::check()`.
- Money, when it arrives in Phase 1, is stored in integer cents — never floats. See [`docs/04-data-model.md`](docs/04-data-model.md).
