# PromoMonster

**Reviews. Reputation. Growth.**

Local businesses ask every customer for a review, reply to what comes back, and
put it to work — without breaking a platform rule. Planning and build
documentation is in [`docs/`](docs/); start with
[`docs/00-execution-plan.md`](docs/00-execution-plan.md).

## Stack

PHP 8.1+ · MySQL / MariaDB · plain CSS. **No Composer, no Node, no build step.**
It deploys by uploading files, so it runs on shared hosting. See
[`DEPLOYMENT.md`](DEPLOYMENT.md).

## The three areas

| Area | URL | Who |
|---|---|---|
| Public site | `/` | Anyone |
| Members | `/members` | Customers, managing their own reviews |
| Superadmin | `/superadmin` | PromoMonster staff |

Both signed-in areas share one credential check but separate guards: staff are
users with `is_admin`, customers are users with a row in `account_users`. A
customer cannot reach `/superadmin` and a staff login cannot reach `/members` —
each is refused at the login form with the session ended, so neither can loop
against its own guard.

## Layout

```
public/          document root — the ONLY web-reachable directory
  index.php      front controller and routes
  .htaccess      rewrites, security headers, no directory listing
  assets/
app/             application code, kept out of the web root
  bootstrap.php  autoloader, session, config
  Support/       Auth, Audit, Router, Database, View, Csrf, Validator, RateLimiter
  Controllers/
  Views/         public pages, members/, superadmin/, auth/
bin/             CLI: create-admin, create-member
database/
  migrate.php       applies pending migrations, tracked so re-runs are safe
  migrations/
  full-schema.sql   every migration in one file, for a FRESH phpMyAdmin import
docs/            business plan and product specs
```

## What's built

The public site is complete and live-ready: home, how it works, features,
pricing, for agencies, the free review audit, privacy and terms. Audit requests
write to `audits`; agency applications write to `waitlist`.

Both signed-in areas exist with real authentication. Their screens carry the
real information architecture, and each states plainly what has not shipped yet
rather than showing an unexplained empty table — sending, monitoring and
replies are Phase 1/2 per the execution plan.

## Superadmin

Create the first admin from the command line. There is deliberately no web
route that can mint one:

```bash
php bin/create-admin.php you@promomonster.com "First" "Last"
```

It prints a one-time temporary password:

```
  Temporary password:  TRP97-WWFZW-YJ9VN-TQWQG
```

Sign in with it and every route diverts to a change-password form until you
pick your own. The temporary one stops working the moment you do, and it is
never stored in plain text — if you lose it before signing in, run the script
again for a new one. Passing `PM_ADMIN_PASSWORD` sets a password directly and
forces no change.

| Screen | Does |
|---|---|
| Overview | Live counts and the latest audit requests |
| Audit requests | The Phase 0 work queue — filter by status, set status, keep notes |
| Agencies | Partner applications from `/agencies` |
| Compliance | Opt-outs on record and 10DLC brand status |
| Activity | Append-only log of every admin action |

Login is throttled at 5 failed attempts per email **and** per IP for 15 minutes.
Locked out during setup? `DELETE FROM login_attempts;` clears it.

## Members

Customers are onboarded by hand during early access:

```bash
php bin/create-member.php owner@business.com "Business Name" "First" "Last"
```

That creates the account, the owner login and a first location in one
transaction.

| Screen | Does |
|---|---|
| Dashboard | Counts and their locations |
| Reviews | Reviews synced from Google, and what needs a reply |
| Requests | Review requests sent and what happened |
| Your playbook | The vertical playbook for their business |
| Settings | Account, plan and team |

## Database

```bash
php database/migrate.php          # tracked, idempotent, safe to re-run
```

No SSH? Import [`database/full-schema.sql`](database/full-schema.sql) through
phpMyAdmin — **but only into a fresh database**. A database that already ran the
old panel schema must go through `migrate.php`, or apply `006` onwards by hand:
`006` removes the panel tables first.

**Requires MySQL 8.0+ or MariaDB 10.6+.** The task-slot claim uses
`SELECT ... FOR UPDATE SKIP LOCKED`; `migrate.php` checks and warns.

## Local development

```bash
cp app/config.example.php app/config.php    # fill in your MySQL credentials
php database/migrate.php
php bin/create-admin.php you@example.com "Your" "Name"
php -S 127.0.0.1:8080 -t public public/index.php
```

## Conventions

- **Every query is a prepared statement.** `Database::run()` takes bound parameters; never interpolate into SQL.
- **Every dynamic value in a template goes through `View::e()`.** No exceptions.
- **Every POST form carries `Csrf::field()`** and its handler calls `Csrf::check()`.
- **Never build review gating.** No sentiment branching, no alternate destinations. The schema deliberately offers nowhere to put it — see [`docs/05-compliance.md`](docs/05-compliance.md) §1.
- Money is stored in integer cents, never floats.
