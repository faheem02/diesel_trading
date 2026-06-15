# Diesel Management — Cash Book & Bank Book

## Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10+
- Apache / Nginx with mod_rewrite

## Setup
1. Copy the `diesel_cashbook/` folder into your web server's root (`htdocs` / `www`).
2. Open `includes/db.php` and set your DB credentials.
3. Visit `http://localhost/diesel_cashbook/install.php` — this creates the database, table, and sample data.
4. Open `http://localhost/diesel_cashbook/` to use the app.

## File Structure
```
diesel_cashbook/
├── index.php          # Main UI (Cash Book + Bank Book)
├── api.php            # JSON API (list / create / update / delete / get)
├── install.php        # One-time DB setup
├── includes/
│   └── db.php         # DB config & connection
└── README.md
```

## API Endpoints (api.php)
| Action   | Method | Description              |
|----------|--------|--------------------------|
| list     | GET    | Fetch transactions + summary |
| create   | POST   | Add new transaction      |
| update   | POST   | Edit existing transaction|
| delete   | POST   | Remove transaction       |
| get      | GET    | Fetch single transaction |

## Categories (pre-set)
Diesel purchase, Diesel sale, Supplier payment, Customer receipt,
Transport cost, Salary, Maintenance, Tax/duty, Bank deposit,
Bank withdrawal, Other
