## What’s Happening Now
- Your dashboard KPIs (like Total Revenue “Current Month”) are based on posted journal entries and revenue/expense accounts.
- Right now, the seed data only posts journal entries on dates like `DATE_SUB(CURDATE(), INTERVAL 10 DAY)` and `15 DAY`, which can land in the previous month (example: if today is Feb 1, those become January). That’s why “Current Month” shows ₱0.00 even though January has points.

## What Data Must Exist (So Dashboard Isn’t Zero)
- **Chart of Accounts** with `account_type = 'Revenue'` and `account_type = 'Expense'` (dashboard queries use these).
- **Posted journal entries** (`journal_entries.status='Posted'`) with matching `journal_entry_lines` for revenue/expense accounts, including at least one entry dated **within the current month**.
- For other KPIs: AR invoices (Pending/Partially Paid), AP invoices (Approved), pending disbursements (Pending).

## Plan (Edits Only After You Confirm)
### 1) Improve the existing seed file to contain “real” finance department data
Update [hospital_financial_seed.sql](file:///c:/Users/kheeel/Herd/hospital-fin-system/database/hospital_financial_seed.sql) to:
- Add **6–8 months of realistic hospital finance journal activity** (OPD/IPD revenue, lab, pharmacy sales; payroll, supplies, utilities, maintenance).
- Ensure at least **one Posted revenue journal entry uses `entry_date = CURDATE()`** (guarantees “Current Month Total Revenue” is not ₱0.00).
- Add a matching **current-month expense entry** so Revenue vs Expenses chart shows both series.
- Expand AR/AP/disbursement/budget entries with realistic amounts and statuses so each KPI card shows meaningful values.

### 2) Make seed inserts deterministic and safe
- Replace hardcoded numeric foreign keys (like `supplier_id = 1`) with subqueries using unique codes (e.g., supplier_code, account_code, department_code).
- Replace `TRUNCATE chart_of_accounts` with `INSERT ... ON DUPLICATE KEY UPDATE` (your schema already has a UNIQUE key on `account_code`).
- Keep everything compatible with the existing schema in [updated/hospital-fin-system.sql](file:///c:/Users/kheeel/Herd/hospital-fin-system/database/updated/hospital-fin-system.sql).

### 3) Optionally bundle seed into the main schema dump
If you want “one import and it’s ready”, append the improved INSERT blocks to the end of:
- [updated/hospital-fin-system.sql](file:///c:/Users/kheeel/Herd/hospital-fin-system/database/updated/hospital-fin-system.sql)
So importing that file alone creates schema + demo finance data.

## Verification (After Edits)
- Run the dashboard queries used in [admin-dashboard.php](file:///c:/Users/kheeel/Herd/hospital-fin-system/admin/admin-dashboard.php#L10-L77) and confirm:
  - Total Revenue (Current Month) > 0
  - Revenue vs Expenses chart has values for multiple months
  - AR/AP/Pending Disbursements totals are non-zero
- Quick sanity checks on counts:
  - `journal_entries` posted count > 0 and includes current month
  - `chart_of_accounts` includes Revenue and Expense accounts

## Deliverable
- “Realistic hospital finance department demo dataset” (COA + AP/AR + Collections + Disbursements + Budget + GL) that makes the dashboard and reports look populated and realistic immediately after import.