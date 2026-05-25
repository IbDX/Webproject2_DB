# SecureBank Backend

This repository contains the backend API for a small secure-banking demo application.

Frontend repo: [c:\AppServ\www\Webproject2](c:/AppServ/www/Webproject2)

**What is here**
- `index.php` — API entry point.
- `.htaccess` — routing for the API.
- `config/database.php` — database connection helpers and environment mapping.
- `database_schema.sql` — SQL schema (import into MySQL/MariaDB).
- `src/` — controllers, middleware, models, and utilities.
- `public/crud_handler.php` — demo CRUD endpoint used for examples.

**API base**
- Frontend should call: `/Webproject2_DB/index.php/api`

**Requirements**
- PHP 7.4 or newer (PHP 8 recommended).
- `pdo_mysql` and `json` extensions enabled.
- MySQL / MariaDB server (5.7+ for JSON support recommended).

**Local setup**
1. Place this repo at `C:\AppServ\www\Webproject2_DB`.
2. Create a database and import the schema:

```powershell
mysql -u <db_user> -p <db_name> < database_schema.sql
```

3. Configure your DB credentials either by setting environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, or by editing `config/database.php` for local development.
4. Ensure the frontend is configured to call the API base URL above.

**Notes on the schema**
- The schema now includes `alias_name` on the `users` table (used for profile aliases and beneficiary lookup).
- `accounts.account_number` is used as an external identifier for beneficiaries; beneficiaries store the account number rather than a direct FK to support external transfers.
- Transactions include a `metadata` JSON column for convenience of storing related account numbers and user IDs.

**Security & cookies**
- When integrating with GitHub Pages or other cross-origin frontends, set `FRONTEND_ORIGIN` appropriately.
- For cross-site cookies, the backend expects:
	- `SESSION_COOKIE_SAMESITE=None`
	- `SESSION_COOKIE_SECURE=1`

If you'd like, I can also add example environment files or a Docker setup next.