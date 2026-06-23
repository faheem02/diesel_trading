# Diesel Trading — Purchase Management System

Plain PHP/MySQL + SB Admin 2 (Bootstrap 4). Runs under XAMPP.

## Setup

1. Import `database/full_schema.sql` into MySQL (creates DB `diesel_trading`).
2. Visit `/setup_admin.php` once → creates user **admin** / **admin123**.
3. Login at `/auth/login.php`.

**DB:** `includes/db.php` — MySQL root, no password, database `diesel_trading`.

**Base URL:** `includes/config.php` — auto-detects project root path (works offline XAMPP `/diesel_trading/` or online `/demos/diesel/`). Used by `header.php`, `footer.php`, and all redirects. Do not hardcode paths.

## Schema & migrations

- `database/purchases_schema.sql` — authoritative schema.
- `database/migration_*.sql` — incremental changes. Apply in filename order.
- `database/full_schema.sql` — one-shot combined dump (use for fresh installs).

## Page conventions

Every page starts with `session_start()`, sets `$active_page`, requires `includes/db.php`, includes `header.php`/`footer.php`. Path depth: `../../` from `modules/purchases/`, `../../../` from `modules/suppliers/reports/` (and other 3-level-deep pages).

Nav highlighting in `includes/header.php:20-27` — add new pages to the appropriate `in_array(...)` there. Edit pages (`suppliers/edit.php`, `customers/edit.php`) reuse the list `$active_page`.

| Sidebar heading | `$active_page` values (from header.php:20-27) |
|---|---|
| Dashboard | `dashboard` |
| Purchases | `purchase_add`, `purchase_list`, `purchase_return`, `purchase_return_list`, `purchase_adjustment` |
| Suppliers | `supplier_add`, `supplier_list`, `supplier_ledger`, `supplier_payment`, `supplier_outstanding`, `supplier_payment_history` |
| Diesel Stock | `tank_list`, `stock_in`, `stock_in_list`, `sale_add`, `sale_list`, `stock_adjustment`, `adjustment_list`, `stock_report_current`, `stock_report_tank_wise`, `stock_report_daily`, `stock_report_ledger` |
| Customers | `customer_add`, `customer_list`, `customer_ledger`, `customer_payment`, `customer_recovery` |
| Sales (heading: "Sales") | `sale_entry`, `sale_list`, `sale_return`, `sale_return_list`, `sales_outstanding` |
| Expenses (heading: "Expenses"; var: `$tanker_active`) | `tanker_list`, `tanker_expense_add`, `tanker_expense_list`, `expense_add`, `expense_list` |
| Cash & Bank (heading: "Cash & Bank") | `cashbook`, `bankbook`, `accounts_manage`, `general_ledger` |

**Note:** `purchase_return`, `purchase_return_list`, `purchase_adjustment`, and `accounts_manage` are in the sidebar arrays but no page files exist for them yet.

## Two sale systems

There are two separate sale entry points under different sidebar sections — do not conflate them:

| File | `$active_page` | Sidebar | Audience |
|---|---|---|---|
| `modules/diesel_stock/sales.php` | `sale_add` | Diesel Stock | Quick over-the-counter sale, direct stock deduction |
| `modules/sales/add.php` | `sale_entry` | Sales Management | Customer sale entry, posts to `customer_ledger` |

## Ledger (double-entry)

Debit = payment received (supplier or from customer). Credit = purchase (supplier) or sale (customer). Balance = SUM(debit) - SUM(credit). `suppliers.balance` / `customers.balance` are denormalized.

**Supplier ledger** is handled via inline SQL in `modules/purchases/add.php`, `modules/suppliers/payment.php`, `modules/suppliers/add.php`. `includes/ledger.php` defines `postToLedger()` but it is **dead code** — never called. Don't expect it to work.

**Customer ledger** is handled via inline SQL in `modules/sales/add.php`, `modules/diesel_stock/sales.php`, `modules/customers/payment.php`, `modules/customers/add.php`.

Payment directions: `to_supplier` (debit, we pay them) / `from_supplier` (credit, they pay us). For customers: `from_customer` (debit, they pay us) / `to_customer` (credit, we pay them).

## Coding conventions

- **DB:** mysqli, no ORM. Raw SQL. Some pages use prepared statements, others interpolate — match the style of the file you edit.
- **Password:** stored in plaintext (legacy — do not replicate).
- **No build step** — straight PHP served by Apache. npm deps (`startbootstrap-sb-admin-2`) unused; SB Admin 2 assets in `assets/sb-admin2/` (gitignored).
- `purchases.invoice_no` has a UNIQUE constraint — duplicate gives MySQL error 1062, caught as "Invoice number already exists."

## Standalone mini-apps

| Path | Notes |
|---|---|
| `modules/diesel_cashbook/` | Bootstrap 5, own DB config (`includes/db.php`), not in sidebar. See `README.md`. |
| `modules/cashbook/index.php` | Bootstrap 5 standalone, missing its own `includes/db.php` — likely broken. |

## Module index

| Path | Files |
|---|---|
| `modules/purchases/` | add, list |
| `modules/suppliers/` | add, edit, list, ledger, payment |
| `modules/suppliers/reports/` | outstanding, payment_history |
| `modules/diesel_stock/` | tanks, stock_in, stock_in_list, sales, sales_list, adjustments, adjustments_list |
| `modules/diesel_stock/reports/` | current_stock, tank_wise_stock, daily_movement, stock_ledger |
| `modules/customers/` | add, edit, list, ledger, payment |
| `modules/customers/reports/` | recovery |
| `modules/sales/` | add, list, returns, returns_list |
| `modules/sales/reports/` | outstanding |
| `modules/tankers/` | list, expenses_add, expenses_list, expenses_delete |
| `modules/expenses/` | add, list, delete |
| `modules/accounts/` | cashbook, bankbook, general_ledger, general_ledger_print |
