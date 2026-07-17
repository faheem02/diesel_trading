# Diesel Trading — Purchase Management System

Plain PHP/MySQL + SB Admin 2 (Bootstrap 4). XAMPP. No framework, no ORM.

## Setup

1. Import `database/full_schema.sql` (creates DB `diesel_trading`).
2. Visit `/setup_admin.php` once → creates user **admin** / **admin123**.
3. Login at `/auth/login.php`.

**DB:** `includes/db.php` — MySQL root, no password, database `diesel_trading`.

**Base URL:** `includes/config.php` — auto-detects from `$_SERVER['DOCUMENT_ROOT']`. Use `$base_url` everywhere; never hardcode paths.

## Schema & migrations

- `database/full_schema.sql` — combined dump for fresh installs.
- `database/migration_*.sql` — incremental. Apply in this exact order:
  1. `purchases_schema.sql` — base: users, suppliers, purchases, purchase_tankers, purchase_returns, purchase_adjustments
  2. `migration_suppliers.sql` — ALTER suppliers ADD contact_person, ntn_cnic, opening_balance
  3. `migration_customers.sql` — customers, customer_ledger
  4. `migration_tankers.sql` — tankers
  5. `migration_tanker_expenses.sql` — tanker_expenses
  6. `migration_stock_management.sql` — tanks, sales, stock_adjustments, stock_ledger
  7. `migration_ledger.sql` — supplier_ledger
  8. `migration_expenses.sql` — expenses
  9. `migration_sales_management.sql` — customer_sales
  10. `migration_bank_accounts.sql` — bank_accounts + FK on customer_ledger
  11. `migration_bank_accounts_add_number.sql` — ALTER bank_accounts ADD account_number
  12. `migration_bank_accounts_add_bank_name.sql` — ALTER bank_accounts ADD bank_name
  13. `migration_cashbook.sql` — ALTER supplier_ledger, customer_ledger, expenses for bank tracking
  14. `migration_customers_add_sales_link.sql` — ALTER sales ADD customer_id FK
  15. `migration_sale_return_stock.sql` — ALTER sale_returns ADD tank_id, extend stock_ledger ENUM

## No tests / no CI

No test files, no CI config, no build step. Straight PHP served by Apache. npm deps unused on disk; `assets/sb-admin2/` is gitignored but present locally.

## Page conventions

Every page: `session_start()`, `$active_page = '...'`, `require_once '../../includes/db.php'`, `include '../../includes/header.php'`, `include '../../includes/footer.php'`. Path depth: `../../` from `modules/<module>/`, `../../../` from 3-deep dirs like `modules/diesel_stock/reports/` and `modules/customers/reports/` (Exception: `modules/accounts/cashbook.php` requires `config.php`, not `db.php`).

Nav highlighting in `includes/header.php:9-15` — `$active_page` is checked against per-section `in_array(...)` sets. Edit pages (`purchases/edit.php`, `suppliers/edit.php`, `customers/edit.php`) reuse the list-page `$active_page`.

### Sidebar links vs. registered `$active_page` values

The `in_array` checks in `header.php:9-15` register more values than have sidebar links. This matters: pages can exist and work without a sidebar link.

| Sidebar | Has sidebar link | Registered `$active_page` values without sidebar links |
|---|---|---|
| Purchases | `purchase_add`, `purchase_list` | `purchase_return`, `purchase_return_list`, `purchase_adjustment` — **no page files exist yet** |
| Suppliers | all 4 values have links | — |
| Diesel Stock | `tank_list`, `stock_adjustment`, `stock_report_ledger`, `stock_report` | `stock_in`, `stock_in_list`, `sale_add`, `sale_list`, `adjustment_list` — files exist, accessed by direct URL. `stock_report_daily` = `modules/diesel_stock/reports/daily_movement.php`. `stock_report_current` is registered but **no page file exists yet**. |
| Customers | all 5 values have links | — |
| Sales Management | `sale_entry`, `sale_list` | `sale_return`, `sale_return_list` — registered but **no page files exist yet** (the `sales_outstanding` value referenced in old docs does not exist). |
| Expenses (var: `$tanker_active`) | `expense_add`, `expense_list` | `tanker_list`, `tanker_expense_add`, `tanker_expense_list` — files exist (`modules/tankers/list.php`, `modules/tankers/expenses_add.php`, `modules/tankers/expenses_list.php`), accessed by direct URL. |
| Cash & Bank (var: `$accounts_active`) | `cashbook` | `accounts_manage`, `general_ledger` — registered but **no page files exist yet** (the `bankbook` value referenced in old docs does not exist). Only `modules/accounts/cashbook.php` exists. |
| General (var: `$general_report_active`) | `general_report`, `general_payable`, `general_receivable` | `party_ledger.php` reuses `general_report`. `add_payable.php`/`add_receivable.php` post to a party ledger in `modules/general/`. |

### `sale_list` collision

Both `modules/diesel_stock/sales_list.php` and `modules/sales/list.php` set `$active_page = 'sale_list'`. They query different tables (`sales` vs `customer_sales`). This is a known quirk — both highlight the Sales Management sidebar section.

## Two sale systems — do not conflate

| File | `$active_page` | Target table | Use |
|---|---|---|---|
| `modules/diesel_stock/sales.php` | `sale_add` | `sales` | Quick over-the-counter, direct stock deduction |
| `modules/sales/add.php` | `sale_entry` | `customer_sales` | Customer sale, posts to `customer_ledger`, multiple tankers per sale |

## Two expense systems

| Directory | `$active_page` | Purpose |
|---|---|---|
| `modules/tankers/` | `tanker_expense_add`, `tanker_expense_list`, `tanker_list` | Tanker-specific expenses |
| `modules/expenses/` | `expense_add`, `expense_list` | General expenses |

Both are under the "Expenses" sidebar heading (variable `$tanker_active`).

## Ledger (inline SQL, no shared function)

All ledger operations use inline SQL. `includes/ledger.php` (`postToLedger()`) is **dead code** — still `require_once`'d by `suppliers/ledger.php` and `suppliers/payment.php`, but `postToLedger()` is never actually called by any page.

**supplier_ledger:** `credit` = purchase / opening balance (supplier gave goods/credit), `debit` = payment to supplier (reduces debt). Balance = SUM(credit) - SUM(debit). Handled in `modules/purchases/add.php`, `modules/suppliers/payment.php`, `modules/suppliers/add.php`.

**customer_ledger:** `debit` = sale (they owe us), `credit` = payment from customer (reduces debt). Balance = SUM(debit) - SUM(credit). Handled in `modules/sales/add.php`, `modules/diesel_stock/sales.php`, `modules/customers/payment.php`, `modules/customers/add.php`.

`suppliers.balance` / `customers.balance` are denormalized.

Payment direction convention: `to_supplier` (debit, we pay them) / `from_supplier` (credit, they pay us). For customers: `from_customer` (debit, they pay us) / `to_customer` (credit, we pay them).

## Coding conventions

- **DB:** mysqli, no ORM. Raw SQL. Some pages use prepared statements, others interpolate — match the style of the file you edit.
- **Password:** plaintext (legacy — do not replicate).
- `purchases.invoice_no` has UNIQUE constraint — MySQL error 1062 caught as "Invoice number already exists."

## Standalone mini-apps

| Path | Notes |
|---|---|
| `modules/diesel_cashbook/` | Bootstrap 5, own DB (`diesel_management`, not `diesel_trading`), own `install.php`, not in sidebar. See `README.md`. |
| `modules/cashbook/index.php` | Bootstrap 5, but missing own `includes/db.php` — broken. |
