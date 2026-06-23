# Diesel Trading — Purchase Management System

Plain PHP/MySQL + SB Admin 2 (Bootstrap 4). Runs under XAMPP.

## Setup

1. Import `database/full_schema.sql` into MySQL (creates DB `diesel_trading`).
2. Visit `http://localhost/diesel_trading/setup_admin.php` once → creates user **admin** / **admin123**.
3. Login at `/auth/login.php`.

## DB config

`includes/db.php` — MySQL root, no password, database `diesel_trading`.

## Schema & migrations

- `database/purchases_schema.sql` — authoritative schema (users, suppliers, purchases, purchase_tankers, purchase_returns, purchase_adjustments, supplier_ledger).
- `database/migration_*.sql` — incremental changes adding tables/columns (includes `migration_sale_return_stock.sql` — adds `tank_id` to `sale_returns` and extends `stock_ledger.reference_type` ENUM). Apply in filename order.
- `database/full_schema.sql` — one-shot combined dump (use for fresh installs).

## Sidebar & page conventions

Every page sets `$active_page` before including header. Active page values used in `includes/header.php:20-27` for nav highlighting — add new pages to the appropriate `in_array(...)` there.

Sidebar sections and their active page arrays (from `includes/header.php:20-27`):
- **Dashboard**: `dashboard`
- **Purchases**: `purchase_add`, `purchase_list`, `purchase_return`, `purchase_return_list`, `purchase_adjustment`
- **Suppliers**: `supplier_add`, `supplier_list`, `supplier_ledger`, `supplier_payment`, `supplier_outstanding`, `supplier_payment_history`
- **Diesel Stock**: `tank_list`, `stock_in`, `stock_in_list`, `sale_add`, `sale_list`, `stock_adjustment`, `adjustment_list`, `stock_report_current`, `stock_report_tank_wise`, `stock_report_daily`, `stock_report_ledger`
- **Customers**: `customer_add`, `customer_list`, `customer_ledger`, `customer_payment`, `customer_recovery`
- **Sales Management** (sidebar heading: "Sales"): `sale_entry`, `sale_list`, `sale_return`, `sale_return_list`, `sales_outstanding`
- **Expenses** (sidebar heading: "Expenses"; variable: `$tanker_active`): `tanker_list`, `tanker_expense_add`, `tanker_expense_list`, `expense_add`, `expense_list`
- **Accounts** (sidebar heading: "Cash & Bank"): `cashbook`, `bankbook`, `accounts_manage`, `general_ledger`

Edit pages (`suppliers/edit.php`, `customers/edit.php`) reuse the list page `$active_page` (`supplier_list`, `customer_list`).

## Coding conventions

- **Session auth**: Every page starts with `session_start()`, sets `$active_page`, requires `includes/db.php`, includes header/footer.
- **Path depth**: `../../` from `modules/purchases/`, `../../../` from `modules/suppliers/reports/`.
- **DB**: mysqli, no ORM. Raw SQL with prepared statements (but some legacy code uses interpolated SQL instead of prepared statements — match existing style in the file you edit).
- **Password**: stored in plaintext (legacy — do NOT replicate elsewhere).
- **No build step** — straight PHP served by Apache. npm deps (`startbootstrap-sb-admin-2`) unused; SB Admin 2 assets in `assets/sb-admin2/` (gitignored).
- **Payments**: Two directions — `to_supplier` (debit, we pay them) and `from_supplier` (credit, they pay us). For customers: `from_customer` (debit, they pay us) and `to_customer` (credit, we pay them).

## Ledger (double-entry)

Debit = payment received by supplier (or from customer). Credit = purchase from supplier (or sale to customer). Balance = SUM(debit) - SUM(credit). `suppliers.balance` / `customers.balance` are denormalized. See `includes/ledger.php` (`postToLedger()` helper) or inline raw SQL patterns in `modules/purchases/add.php:118-144` and `modules/customers/payment.php:9-20`.

## SQL quirks

- `purchases.invoice_no` has a UNIQUE constraint — duplicate gives MySQL error 1062, caught as "Invoice number already exists."

## All modules

| Module path | Files |
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
| `modules/accounts/` | cashbook, bankbook, general_ledger |
| `modules/diesel_cashbook/` | standalone mini-app (Bootstrap 5, own DB config) — not in sidebar |
| `modules/cashbook/` | Bootstrap 5 standalone page (may be broken — missing `includes/db.php`) |
