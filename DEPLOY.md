# Deploying to cPanel hosting with GitHub

Stack: static HTML frontend + PHP/MySQL API. No Node.js needed — works on any cPanel shared plan.

```
index.html      main bonus page
adminka.html    admin panel  -> /adminka
api/            PHP backend
  config.php    DB credentials (created ON THE SERVER, never in GitHub)
  db.php        connection + auto-creates tables
  claims.php    GET list claims / POST save claim / DELETE
  bonuses.php   GET list / POST add / DELETE
.cpanel.yml     GitHub -> public_html deployment rules
.htaccess       HTTPS, /adminka clean URL, blocks config.php
```

---

## 1. Create the database

cPanel → **MySQL Databases**

1. Create database: `bonusdb` → real name becomes `youruser_bonusdb`
2. Create user: `bonususer` → real name becomes `youruser_bonususer`, set a strong password
3. **Add User To Database** → select both → tick **ALL PRIVILEGES**

Write down the three real values — you need them in step 3.

## 2. Connect GitHub to cPanel

cPanel → **Git™ Version Control** → **Create**

| Field | Value |
|---|---|
| Clone a Repository | ON |
| Clone URL | `https://github.com/panosyantaron-rgb/bonus-request-page.git` |
| Repository Path | `/home/youruser/repositories/bonus-request-page` |
| Repository Name | `bonus-request-page` |

Private repo → use `https://<username>:<personal-access-token>@github.com/...` as the clone URL.
Token: GitHub → Settings → Developer settings → Personal access tokens → Fine-grained → **Contents: Read**.

**Before the first deploy**, edit `.cpanel.yml` in the repo and replace `cpaneluser` with your real cPanel username on both lines, then commit and push.

## 3. Create config.php on the server

cPanel → **File Manager** → `public_html/api/`

Copy `config.example.php` → rename the copy to `config.php` → edit it with the values from step 1:

```php
define('DB_NAME', 'youruser_bonusdb');
define('DB_USER', 'youruser_bonususer');
define('DB_PASS', 'the password you set');
define('ADMIN_PASS', 'change_this_before_going_live');
```

This file is gitignored on purpose — deploys never overwrite it, and your password never reaches GitHub.

## 4. Deploy

cPanel → Git Version Control → your repo → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**

Tables are created automatically on the first API request, and the 9 default bonuses are seeded once.

## 5. Verify

| Check | Expected |
|---|---|
| `yourdomain.com/api/bonuses.php` | JSON list of 9 bonuses |
| `yourdomain.com/api/claims.php` | `{"claims":[],"total":0,"uniqueUsers":0}` |
| `yourdomain.com/?username=test&id=123` | Bonus page loads |
| Select a bonus → Claim | Success message |
| `yourdomain.com/adminka` | Login `admin` / your password |
| Admin → Recent Claims | The test claim, permanently |

`{"error":"Database connection failed..."}` means the credentials in `api/config.php` are wrong — recheck step 1, including that the user was added to the database with ALL PRIVILEGES.

---

## Updating the site from now on

```bash
git add .
git commit -m "your change"
git push
```

Then cPanel → Git Version Control → **Update from Remote** → **Deploy HEAD Commit**.

To make that automatic, add a GitHub webhook (repo → Settings → Webhooks) pointing at cPanel's deploy endpoint, so every push deploys itself.

## Before going live

- Change `ADMIN_PASS` in `api/config.php`
- Confirm `yourdomain.com/api/config.php` returns 403 (the `.htaccess` rule)
- Keep the Netlify site up until the new domain is verified working
