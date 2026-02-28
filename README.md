# HUMS (PHP + MySQL)

Hathazari Upazila Management System is now implemented with:
- PHP 8+
- MySQL/MariaDB
- Session-based login auth
- Role-based access (`admin`, `viewer`, `operator`)
- Frontend in `public/`
- JSON API in `api/`

## Folder Layout

- `public/index.html` UI
- `public/js/app.js` frontend logic
- `api/*` backend endpoints
- `config.php` DB config
- `sql/schema.sql` schema + seed data

## cPanel Deployment (Shared Hosting)

1. Create MySQL database/user in cPanel.
2. Import `sql/schema.sql` using phpMyAdmin.
3. Upload project files to `public_html` (or your domain docroot).
4. Edit `config.php` with your DB credentials.
5. Open your domain URL.

## Default Login

- `admin / admin123`
- `viewer / viewer1234`

## API Endpoints

- `POST /api/auth/login.php`
- `GET /api/auth/me.php`
- `POST /api/auth/logout.php`
- `GET|POST /api/beneficiaries/index.php`
- `POST /api/beneficiaries/item.php?id={id}&op=update|delete`
- `GET|POST /api/institutions/index.php`
- `POST /api/institutions/item.php?id={id}&op=update|delete`
- `GET|POST /api/users/index.php`
- `POST /api/users/item.php?id={id}&op=delete`
- `GET /api/unions/index.php`
- `POST /api/duplicate/check.php`
- `GET /api/duplicate/list.php`

