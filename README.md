# SecureBank Backend

This repository contains the backend only.

Frontend repo: [c:\AppServ\www\Webproject2](c:/AppServ/www/Webproject2)

## What is here
- `index.php` is the API entry point.
- `.htaccess` routes API requests.
- `config/database.php` holds database configuration.
- `database_schema.sql` contains the schema.
- `src/` contains controllers, middleware, models, and utilities.
- `public/crud_handler.php` serves the demo CRUD endpoint.

## API base
The frontend should call:
- `/Webproject2_DB/index.php/api`

## Local use
1. Place this repo at `C:\AppServ\www\Webproject2_DB`.
2. Import `database_schema.sql` into MySQL.
3. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in your environment, or update `config/database.php` for local development.
4. Open the frontend repo separately from `C:\AppServ\www\Webproject2`.

## GitHub Pages integration
Set `FRONTEND_ORIGIN` to your Pages origin, for example `https://username.github.io` or your custom Pages domain.
For local development with Live Server or similar, the backend also accepts `http://localhost:*` and `http://127.0.0.1:*` origins.

For cross-site session cookies, the backend expects:
- `SESSION_COOKIE_SAMESITE=None`
- `SESSION_COOKIE_SECURE=1`

## Notes
- This repo is API-only and no longer serves the frontend shell.
- Session-based authentication remains in the backend.