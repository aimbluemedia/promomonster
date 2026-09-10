# Deploying PromoMonster

PHP 8.1+ and MySQL 5.7+ / MariaDB 10.3+. No Composer, no Node, no build step —
this deploys by uploading files, which is why it suits shared hosting.

## 1. Upload the files

The **document root must be `public/`**, not the project root. Nothing else
should be reachable over the web.

### Preferred layout (app code outside the web root)

On Hostinger your account looks like `~/domains/promomonster.com/public_html`.
Put the app one level above it:

```
~/domains/promomonster.com/
├── app/                 <- upload app/ here
├── database/            <- upload database/ here
└── public_html/         <- upload the CONTENTS of public/ here
    ├── index.php
    ├── .htaccess
    └── assets/
```

`public/index.php` loads `../app/bootstrap.php`, so this works with no changes.

### Fallback layout (everything inside public_html)

If you can't put files above the web root, upload the whole project into
`public_html` and point the domain at `public_html/public`. The `.htaccess`
files in `app/` and `database/` deny direct access as a second line of defence,
but keeping app code out of the web root is still better.

**Make sure hidden files are uploaded.** FileZilla and hPanel's file manager
both hide dotfiles by default, and a missing `.htaccess` is the single most
common cause of a broken deploy.

## 2. Create the database

In hPanel → **Databases → MySQL Databases**, create a database and a user, and
note the host (usually `localhost`).

## 3. Configure

Copy `app/config.example.php` to `app/config.php` and fill in the credentials:

```php
'db' => [
    'host'     => 'localhost',
    'database' => 'u123456_promomonster',
    'username' => 'u123456_pm',
    'password' => 'the-password-you-set',
],
'debug' => false,   // keep false in production
```

`app/config.php` is gitignored. Never commit real credentials.

## 4. Create the tables

With SSH:

```bash
php database/migrate.php
```

Without SSH: open **phpMyAdmin**, select your database, go to the **SQL** tab,
and paste the contents of `database/migrations/001_create_waitlist.sql`. It's
plain SQL with no placeholders.

## 5. Check it

```bash
curl -o /dev/null -w "%{http_code}\n" https://promomonster.com/          # 200
curl -o /dev/null -w "%{http_code}\n" https://promomonster.com/business  # 200
curl -o /dev/null -w "%{http_code}\n" https://promomonster.com/nope      # 404
```

Then submit the form on `/earn` and confirm a row appears in the `waitlist`
table.

---

## Troubleshooting

### 403 Forbidden

Apache found no `index.php` where it was looking, and directory listing is off.

- The document root is pointing at the project root instead of `public/`.
- Or `public/.htaccess` didn't upload — check that hidden files are visible in your FTP client.
- Or file permissions are wrong. Directories should be `755`, files `644`.

### 500 Internal Server Error

Set `'debug' => true` in `app/config.php` temporarily and reload — the real
error will print. Set it back to `false` afterwards. Common causes: wrong
database credentials, or a PHP version below 8.1.

### Pages work but every link 404s

`mod_rewrite` isn't active, so only `/` resolves. On Hostinger it's on by
default; if you're elsewhere, enable it and make sure `AllowOverride All` is
set for the directory.

### "Configuration missing"

You haven't created `app/config.php` yet. See step 3.

### Hero image not showing

The page looks for `assets/img/hero.png` **relative to your document root** —
the same folder that holds `index.php` and `assets/`.

- Preferred layout (document root is `public_html`): put it at `public_html/assets/img/hero.png`
- Fallback layout (whole project in `public_html`): put it at `public_html/public/assets/img/hero.png`

Confirm it is reachable directly at `https://your-domain/assets/img/hero.png`.
If that 404s, the file is in the wrong folder. If it loads but the page still
shows the placeholder, check the filename is exactly `hero.png` — lowercase,
no `.PNG`, no trailing space, no `hero (1).png`. Linux servers are
case-sensitive where your computer may not be.

`.jpg` and `.webp` also work.

### Styles missing

`assets/` didn't upload, or it landed in the wrong place. `app.css` must be
reachable at `https://your-domain/assets/css/app.css`.

---

## Creating your admin login

After the migrations run, over SSH:

```bash
php bin/create-admin.php you@promomonster.com "First" "Last"
```

It prints a one-time temporary password. Sign in at `/superadmin/login` and
you will be required to choose your own before anything else opens.

No SSH? Generate a hash locally with
`php -r 'echo password_hash("your-password", PASSWORD_DEFAULT);'` and insert
the row through phpMyAdmin:

```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status, email_verified_at)
VALUES ('you@promomonster.com', '<paste the hash>', 'First', 'Last', 1, 'active', NOW());
```

Then sign in at `/admin/login`. The admin area is `noindex, nofollow` and every
route redirects to the login form when signed out.

## Local development

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open http://127.0.0.1:8080. You still need a local MySQL and an
`app/config.php` pointing at it.

The `public/index.php` router hands real files back to the built-in server, so
CSS and images load in development the same way Apache serves them in
production.
