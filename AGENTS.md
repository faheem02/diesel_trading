# Diesel Trading — Purchase Management System

Plain PHP/MySQL + SB Admin 2 (Bootstrap 4). Runs under XAMPP.

## Setup

1. Import `database/full_schema.sql` into MySQL (creates DB `diesel_trading`).
2. Visit `http://localhost/diesel_trading/setup_admin.php` once → creates user **admin** / **admin123**.
3. Login at `/auth/login.php`.

## DB config

`config/db.php` — MySQL root, no password, database `diesel_trading`.

## Schema & migrations

- `database/purchases_schema.sql` — authoritative schema (users, suppliers, purchases, purchase_tankers, purchase_returns, purchase_adjustments, supplier_ledger).
- `database/migration_*.sql` — incremental changes adding tables/columns (tanks, sales, stock_ledger, customers, customer_ledger, customer_sales, tankers, tanker_expenses, expenses, bank_accounts, etc.). Apply in filename order.
- `database/full_schema.sql` — one-shot combined dump (use for fresh installs).

## Sidebar & page conventions

Every page sets `$active_page` before including header. Active page values used in `includes/header.php:20-25` for nav highlighting — add new pages to the appropriate `in_array(...)` there.

Sidebar sections (all have corresponding active page arrays):
- **Purchases**: `purchase_add`, `purchase_list`, `purchase_return`, `purchase_return_list`, `purchase_adjustment`
- **Suppliers**: `supplier_add`, `supplier_list`, `supplier_ledger`, `supplier_payment`, `supplier_outstanding`, `supplier_statement`, `supplier_payment_history`
- **Diesel Stock**: `tank_list`, `stock_in`, `stock_in_list`, `sale_add`, `sale_list`, `stock_adjustment`, `adjustment_list`, `stock_report_current`, `stock_report_tank_wise`, `stock_report_daily`, `stock_report_ledger`, `rpt_stock_summary`
- **Customers**: `customer_add`, `customer_list`, `customer_ledger`, `customer_payment`, `customer_recovery`
- **Sales Management**: `sale_entry`, `sale_list`
- **Tanker Management**: `tanker_list`, `tanker_expense_add`, `tanker_expense_list`
- **Accounts**: `cashbook`, `bankbook`, `accounts_manage`, `general_ledger`
- **Reports**: `rpt_purchase`, `rpt_supplier_tanker_purchase`, `rpt_sales_daily_monthly`, `rpt_sales_customer_vehicle`

## Coding conventions

- **Session auth**: Every page starts with `session_start()`, sets `$active_page`, requires `config/db.php`, includes header/footer.
- **Path depth**: `../../` from `modules/purchases/`, `../../../` from `modules/suppliers/reports/`.
- **DB**: mysqli, no ORM. Raw SQL with prepared statements. `require_once '../../config/db.php'` → `$conn`.
- **Password**: stored in plaintext (legacy — do NOT replicate elsewhere).
- **No build step** — straight PHP served by Apache. npm deps (`startbootstrap-sb-admin-2`) unused; SB Admin 2 assets in `assets/sb-admin2/` (gitignored).
- **Payments**: Two directions — `to_supplier` (debit, we pay them) and `from_supplier` (credit, they pay us). For customers: `from_customer` (debit, they pay us) and `to_customer` (credit, we pay them).

## Ledger (double-entry)

Debit = payment received by supplier (or from customer). Credit = purchase from supplier (or sale to customer). Balance = SUM(debit) - SUM(credit). `suppliers.balance` / `customers.balance` are denormalized. See `includes/ledger.php` (`postToLedger()` helper) or inline raw SQL patterns in `modules/purchases/add.php:133-157` and `modules/customers/payment.php:9-20`.

## Waste calculation

`waste_kg = (qty / 35) * 50`, `net_qty = qty - (waste_kg / 1000)`. Applied per-tanker-row in both PHP (`modules/purchases/add.php:38-39`) and JS (`modules/purchases/add.php:378-379`).

## SQL quirks

- `purchases.invoice_no` has a UNIQUE constraint — duplicate gives MySQL error 1062, caught as "Invoice number already exists."
- `modules/purchases/add.php:134` uses `$net_purchase_cost` which is undefined — should be `$total_net`. This affects the ledger description debit value when a payment is made alongside the purchase.

## All modules (none are empty stubs)

| Module path | Files |
|---|---|
| `modules/purchases/` | add, list, returns, returns_list, adjustments |
| `modules/suppliers/` | add, edit, list, ledger, payment |
| `modules/suppliers/reports/` | outstanding, payment_history |
| `modules/diesel_stock/` | tanks, stock_in, stock_in_list, sales, sales_list, adjustments, adjustments_list |
| `modules/diesel_stock/reports/` | current_stock, tank_wise_stock, daily_movement, stock_ledger |
| `modules/customers/` | add, edit, list, ledger, payment |
| `modules/customers/reports/` | recovery |
| `modules/sales/` | add, list |
| `modules/tankers/` | list, expenses_add, expenses_list, expenses_delete |
| `modules/accounts/` | cashbook, bankbook, general_ledger |
| `modules/reports/` | purchase_report, supplier_tanker_purchase, sales_daily_monthly, sales_customer_vehicle, stock_summary, trial_balance, profit_loss, balance_sheet |
| `modules/expenses/` | add, list, delete |
| `modules/diesel_cashbook/` | standalone mini-app (index, api, install, includes/db.php) — not in sidebar |
