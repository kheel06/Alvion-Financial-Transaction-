## What You’re Seeing

* The “SEED-JE-REV-00” text is coming from `journal_entries.journal_number`.

* The General Ledger page shows that field directly in the “Recent Journal Entries” panel: [admin-general\_ledger.php](file:///c:/Users/kheeel/Herd/hospital-fin-system/admin/modules/admin-general_ledger.php#L180-L221).

* Right now, the only places that contain `SEED-` are the SQL files:

  * [hospital\_financial\_seed.sql](file:///c:/Users/kheeel/Herd/hospital-fin-system/database/hospital_financial_seed.sql)

  * [updated/hospital-fin-system.sql](file:///c:/Users/kheeel/Herd/hospital-fin-system/database/updated/hospital-fin-system.sql)

## Goal

* Remove all “SEED-\*” labels so the system looks like a real hospital finance dataset (journal numbers match the system’s real generator format).

## Plan

### 1) Replace “SEED-\*” journal numbers in SQL files

* Update both SQL files to stop inserting `SEED-JE-*`.

* Use the system’s real journal number format (matches `generateJournalNumber()`):

  * `JE-YYYYMMDD-###` (example: `JE-20260201-001`).

* Keep the same realistic transactions (revenues/expenses) — only remove the “seed-looking” labels.

### 2) Clean up the existing database records (so the UI stops showing SEED immediately)

* Add a small SQL “cleanup” script that renames existing `journal_entries.journal_number` values where `journal_number LIKE 'SEED-%'` to a real format.

* Safe approach (keeps uniqueness):

  * Set `journal_number = CONCAT('JE-', DATE_FORMAT(entry_date,'%Y%m%d'), '-', LPAD(id, 3, '0'))` or a similar deterministic pattern based on `id`.

* Run it once so the General Ledger “Recent Journal Entries” list stops showing SEED labels.

### 3) Verify

* Re-check the “Recent Journal Entries” panel: it should display real-looking `JE-...` numbers.

* Re-check dashboard: revenue/expense totals remain non-zero.

## Deliverable

* No more “SEED-\*” labels in the UI or SQL files, while keeping realistic hospital finance transactions (Revenue/Expense/AP/AR/Collections/Disbursement/ বাজgets).

