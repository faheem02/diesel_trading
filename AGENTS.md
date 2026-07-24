# Diesel Trading — Purchase Management System

Plain PHP/MySQL + SB Admin 2 (Bootstrap 4). XAMPP. No framework, no ORM.

## Setup

1. Import `database/diesel_trading_fixed_full.sql` (MariaDB dump, UTF-16 — use phpMyAdmin import) → DB `diesel_trading_fixed`.
2. Visit `/setup_admin.php` once → creates user **admin** / **admin123**.
3. Login at `/auth/login.php`.

**DB:** `includes/db.php` — MySQL root, no password, database `diesel_trading_fixed`.

**Base URL:** `includes/config.php` — auto-detects from `$_SERVER['DOCUMENT_ROOT']`. Use `$base_url` everywhere; never hardcode paths.

**Schema files:** `diesel_trading_fixed_full.sql` / `schema_diesel_trading_fixed.sql` are the current dumps. `full_schema.sql`, `purchases_schema.sql` and `migration_*.sql` are the legacy lineage of the old `diesel_trading` DB — reference only, do NOT apply to `diesel_trading_fixed`.

## No tests / no CI

No test files, no CI, no build step. Verify with `C:\xampp\php\php.exe -l <file>` plus exercising the page in a browser. `php`/`mysql` are NOT on PATH — use `C:\xampp\php\php.exe` and `C:\xampp\mysql\bin\mysql.exe -u root diesel_trading_fixed`. `assets/sb-admin2/` is gitignored but present locally.

## Single-tank workflow (critical)

The app assumes exactly ONE tank. Tank pickers are hidden inputs; every stock movement (purchase, sale, opening stock, adjustment) posts to the default tank.

- `includes/tank_helper.php` → `resolve_default_tank($conn)`: returns all tanks ordered by id; **auto-creates "Main Tank" if the table is empty**. Every transaction page must use it (`$single_tank = $tanks_list[0]`).
- NEVER hardcode `tank_id = 1` as a fallback. After a data reset tank 1 may not exist: purchases then silently skip the stock update (purchase invisible in stock report), and `stock_ledger` inserts crash on FK `stock_ledger_ibfk_1`.
- Report pages (read-only) must NOT call the helper — they fall back to the first existing tank or show their "select a tank" empty state.
- `stock_report.php` orders `opening_balance` first within each date (opening is the stock starting point).
- `customer_sales.customer_id` must be NULL for walk-in customers (FK `customer_sales_ibfk_1`, ON DELETE SET NULL) — inserting `0` fails. mysqli `bind_param` with an `"i"`-typed PHP `null` inserts NULL correctly.

## Page conventions

Every page: `session_start()`, `$active_page = '...'`, `require_once '../../includes/db.php'`, `include '../../includes/header.php'`, `include '../../includes/footer.php'`. Path depth: `../../` from `modules/<module>/`, `../../../` from 3-deep dirs like `modules/diesel_stock/reports/` and `modules/customers/reports/`.

Nav highlighting in `includes/header.php:9-16` — `$active_page` checked against per-section `in_array(...)` sets. Edit pages (`purchases/edit.php`, `suppliers/edit.php`, `customers/edit.php`) reuse the list-page `$active_page`.

### Sidebar links vs. registered `$active_page` values

| Sidebar | Has sidebar link | Registered `$active_page` values without sidebar links |
|---|---|---|
| Purchases | `purchase_add`, `purchase_list` | `purchase_return`, `purchase_return_list`, `purchase_adjustment` — **no page files exist** |
| Suppliers | all 4 values have links | — |
| Diesel Stock | `opening_stock`, `stock_adjustment`, `stock_report_ledger`, `stock_report` | `tank_list` — `tanks.php` exists and works by direct URL; the owner **deliberately removed** its sidebar link, do not re-add. `stock_in_list` (`stock_in_list.php`), `adjustment_list` (`adjustments_list.php`), `stock_report_daily` (`reports/daily_movement.php`) — exist, direct URL. `stock_in`, `sale_add`, `sale_list` — redirect stubs, see below. `stock_report_current` — **no page file exists**. |
| Customers | all 5 values have links | — |
| Sales Management | `sale_entry`, `sale_list` | `sale_return`, `sale_return_list` — **no page files exist** |
| Expenses (var: `$tanker_active`) | `expense_add`, `expense_list` | `tanker_list`, `tanker_expense_add`, `tanker_expense_list` — files exist in `modules/tankers/`, direct URL |
| Accounts (var: `$accounts_active`) | `cashbook` | `accounts_manage`, `general_ledger` — **no page files exist**. Only `modules/accounts/cashbook.php` exists. |
| General Report (var: `$general_report_active`) | `general_report` → `general/parties.php`, `general_payable` → `add_payable.php`, `general_receivable` → `add_receivable.php` | `party_ledger.php` / `party_summary.php` reuse `general_report` |

### Redirect stubs in `modules/diesel_stock/`

These files redirect immediately; the code below the redirect is dead — edit the target file instead:

- `sales.php` → `modules/sales/add.php`
- `sales_list.php` → `modules/sales/list.php`
- `stock_in.php` → `opening_stock.php`

## One active sale system

Only `modules/sales/add.php` (`sale_entry`) is live → writes `customer_sales`, `customer_ledger`, and stock OUT. The legacy `sales` table is only referenced by dead code under the `diesel_stock/sales.php` redirect — nothing active writes to it.

## Two expense systems

| Directory | `$active_page` | Purpose |
|---|---|---|
| `modules/tankers/` | `tanker_expense_add`, `tanker_expense_list`, `tanker_list` | Tanker-specific expenses |
| `modules/expenses/` | `expense_add`, `expense_list` | General expenses |

Both are under the "Expenses" sidebar heading (variable `$tanker_active`).

## Ledger (inline SQL, no shared function)

All ledger operations use inline SQL. `includes/ledger.php` (`postToLedger()`) is **dead code** — still `require_once`'d by `suppliers/ledger.php` and `suppliers/payment.php`, but never actually called.

**supplier_ledger:** `credit` = purchase / opening balance (supplier gave goods/credit), `debit` = payment to supplier (reduces debt). Balance = SUM(credit) - SUM(debit). Handled in `modules/purchases/add.php`, `modules/suppliers/payment.php`, `modules/suppliers/add.php`.

**customer_ledger:** `debit` = sale (they owe us), `credit` = payment from customer (reduces debt). Balance = SUM(debit) - SUM(credit). Handled in `modules/sales/add.php`, `modules/customers/payment.php`, `modules/customers/add.php`.

`suppliers.balance` / `customers.balance` are denormalized.

Payment direction convention: `to_supplier` (debit, we pay them) / `from_supplier` (credit, they pay us). For customers: `from_customer` (debit, they pay us) / `to_customer` (credit, we pay them).

## Coding conventions

- **DB:** mysqli, no ORM. Raw SQL. Some pages use prepared statements, others interpolate — match the style of the file you edit.
- **Password:** plaintext (legacy — do not replicate).
- `purchases.invoice_no` and `customer_sales.invoice_no` have UNIQUE constraints — MySQL error 1062 caught as "Invoice number already exists."

## Standalone mini-apps

| Path | Notes |
|---|---|
| `modules/diesel_cashbook/` | Bootstrap 5, own DB (`diesel_management`, not `diesel_trading_fixed`), own `install.php`, not in sidebar. See its `README.md`. |
| `modules/cashbook/index.php` | Bootstrap 5, but missing own `includes/db.php` — broken. |
